<?php

namespace App\Services\Integration;

use App\Helpers\Envoisms;
use App\Jobs\EnvoiMessageJob;
use App\Models\Agence;
use App\Models\Integration\Canal;
use App\Models\Integration\Envoi;
use App\Models\Integration\ModeleMessage;
use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Envois e-mail, SMS et WhatsApp avec la configuration enregistrée à l'écran (table canaux), appliquée
 * au prochain envoi sans redémarrage : canal de l'agence s'il est actif, sinon celui de la plateforme.
 * Mode sandbox : l'envoi est journalisé mais pas transmis. Chaque tentative est tracée dans envois.
 */
class NotificationService
{
    public const CANAUX = ['EMAIL' => 'E-mail', 'SMS' => 'SMS', 'WHATSAPP' => 'WhatsApp'];

    /**
     * Notification d'un événement selon le modèle de l'agence (canaux, textes), envoyée en file d'attente.
     *
     * @param array{email?: ?string, telephone?: ?string, nom?: ?string} $destinataire
     * @param int[] $documents documents GED joints à l'e-mail (reçu, quittance… signés QR)
     * @return array<string, string> canal => EN_FILE | IGNORE
     */
    public static function notifier(string $codeModele, array $destinataire, array $variables = [], ?Model $objet = null, array $documents = []): array
    {
        $modele = self::modele($codeModele);
        if (!$modele || !$modele->actif) {
            return [];
        }
        $variables += ['agence' => self::nomAgence(), 'nom' => $destinataire['nom'] ?? ''];
        $remplacer = fn (?string $texte) => self::remplacer((string) $texte, $variables);

        $resultats = [];
        foreach ((array) $modele->canaux as $canal) {
            $adresse = $canal === 'EMAIL' ? ($destinataire['email'] ?? null) : ($destinataire['telephone'] ?? null);
            if (!$adresse) {
                $resultats[$canal] = 'IGNORE';
                continue;
            }
            $corps = $canal === 'EMAIL' ? nl2br(e($remplacer($modele->corps_email))) : $remplacer($modele->corps_sms);
            self::enFile($canal, $adresse, $corps, $remplacer($modele->sujet), $destinataire['nom'] ?? null, $codeModele, $objet, $canal === 'EMAIL' ? $documents : []);
            $resultats[$canal] = 'EN_FILE';
        }

        return $resultats;
    }

    /** Envoi différé (php artisan queue:work --queue=notifications), exécuté dans le contexte de l'agence courante. */
    public static function enFile(string $canal, string $destinataire, string $message, ?string $sujet = null, ?string $nom = null, ?string $modele = null, ?Model $objet = null, array $documents = []): void
    {
        EnvoiMessageJob::dispatch($canal, $destinataire, $message, $sujet, $nom, $modele,
            app(AgenceCourante::class)->id(), $objet?->getMorphClass(), $objet?->getKey(), Auth::id(), array_values(array_filter($documents)))
            ->onQueue(config('rbac.notifications.queue', 'notifications'))
            ->afterCommit();   // pas d'envoi si la transaction métier est annulée
    }

    /** Envoi immédiat sur un canal ; retourne ENVOYE, SIMULE, ECHEC ou IGNORE. */
    public static function envoyer(string $canal, string $destinataire, string $message, ?string $sujet = null, ?string $nom = null, ?string $modele = null, array $objet = [], ?Canal $config = null, array $documents = []): string
    {
        return match ($canal) {
            'EMAIL' => self::email($destinataire, (string) ($sujet ?: self::nomAgence()), $message, $nom, $modele, $objet, $config, $documents),
            'SMS' => self::sms($destinataire, $message, $modele, $objet, $config),
            'WHATSAPP' => self::whatsapp($destinataire, $message, $modele, $objet, $config),
            default => 'IGNORE',
        };
    }

    // ------------------------------------------------------------------ e-mail

    public static function email(string $a, string $sujet, string $html, ?string $nom = null, ?string $modele = null, array $objet = [], ?Canal $canal = null, array $documents = []): string
    {
        $canal ??= Canal::actif('EMAIL');
        $contenu = view('notifications.email', ['contenu' => $html, 'sujet' => $sujet, 'nom' => $nom, 'gabarit' => self::gabarit()])->render();

        // pièces jointes : documents archivés dans la GED (le fichier envoyé est l'original signé QR)
        $ged = app(\App\Services\Ged\GedService::class);
        $pieces = \App\Models\Document::whereIn('id', $documents)->get()->map(fn ($d) => [$ged->contenu($d), $d->nom_original, $d->mime])->filter(fn ($p) => $p[0] !== null);
        $joindre = function ($m) use ($pieces) {
            foreach ($pieces as [$octets, $nomFichier, $mime]) {
                $m->attachData($octets, $nomFichier, ['mime' => $mime]);
            }

            return $m;
        };

        return self::executer('EMAIL', $canal, $a, $sujet, $modele, $objet, function () use ($canal, $a, $sujet, $contenu, $nom, $joindre) {
            if (!$canal) {                                         // aucun canal configuré : mailer du .env
                Mail::html($contenu, fn ($m) => $joindre($m->to($a, $nom)->subject($sujet)->from(config('mail.from.address'), self::nomAgence())));

                return null;
            }
            if ($canal->fournisseur === 'LOG') {
                return self::journaliser('EMAIL', $a, $sujet);
            }
            $mailer = 'canal_' . $canal->id;
            config(["mail.mailers.$mailer" => [
                'transport' => 'smtp',
                'scheme' => $canal->valeur('chiffrement') === 'ssl' ? 'smtps' : 'smtp',
                'host' => $canal->valeur('host'), 'port' => (int) $canal->valeur('port', 465),
                'username' => $canal->valeur('username'), 'password' => $canal->valeur('password'), 'timeout' => 20,
            ]]);
            app('mail.manager')->purge($mailer);   // configuration relue à chaque envoi : modifiée à l'écran sans redémarrage
            Mail::mailer($mailer)->html($contenu, fn ($m) => $joindre($m->to($a, $nom)->subject($sujet)
                ->from($canal->valeur('from_address', $canal->valeur('username')), $canal->valeur('from_name', self::nomAgence()))));

            return null;
        });
    }

    // ------------------------------------------------------------------ SMS / WhatsApp

    public static function sms(string $telephone, string $message, ?string $modele = null, array $objet = [], ?Canal $canal = null): string
    {
        $canal ??= Canal::actif('SMS');
        $numero = self::numero($telephone, (string) ($canal?->valeur('indicatif') ?? config('rbac.sms.indicatif', '225')));

        return self::executer('SMS', $canal, $numero, null, $modele, $objet, function () use ($canal, $numero, $message) {
            return match ($canal?->fournisseur) {
                null => Envoisms::viaConfiguration($numero, $message),   // aucun canal à l'écran : passerelle du .env
                'LOG' => self::journaliser('SMS', $numero, $message),
                'ORANGE_CI' => self::smsOrange($canal, $numero, $message),
                'TWILIO' => self::reponse(Http::asForm()->withBasicAuth($canal->valeur('account_sid'), $canal->valeur('auth_token'))
                    ->post('https://api.twilio.com/2010-04-01/Accounts/' . $canal->valeur('account_sid') . '/Messages.json',
                        ['To' => '+' . $numero, 'From' => $canal->valeur('expediteur'), 'Body' => $message]), 'sid'),
                'INFOBIP' => self::reponse(Http::withHeaders(['Authorization' => 'App ' . $canal->valeur('api_key')])
                    ->post(rtrim((string) $canal->valeur('base_url'), '/') . '/sms/2/text/advanced',
                        ['messages' => [['destinations' => [['to' => $numero]], 'from' => $canal->valeur('expediteur'), 'text' => $message]]]), 'messages.0.messageId'),
                'SYMTEL' => self::reponse(Http::asForm()->post($canal->valeur('url'), ['user' => $canal->valeur('user'), 'code' => $canal->valeur('code'),
                    'title' => $canal->valeur('expediteur'), 'phone' => $numero, 'content' => $message]), null),
                default => self::reponse(Http::withToken((string) $canal->valeur('token'))->post($canal->valeur('url'), [
                    $canal->valeur('champ_telephone', 'to') => $numero, $canal->valeur('champ_message', 'message') => $message,
                    $canal->valeur('champ_expediteur', 'from') => $canal->valeur('expediteur')]), 'id'),
            };
        });
    }

    public static function whatsapp(string $telephone, string $message, ?string $modele = null, array $objet = [], ?Canal $canal = null): string
    {
        $canal ??= Canal::actif('WHATSAPP');
        if (!$canal) {
            return 'IGNORE';
        }
        $numero = self::numero($telephone, (string) $canal->valeur('indicatif', '225'));

        return self::executer('WHATSAPP', $canal, $numero, null, $modele, $objet, fn () => match ($canal->fournisseur) {
            'LOG' => self::journaliser('WHATSAPP', $numero, $message),
            'TWILIO' => self::reponse(Http::asForm()->withBasicAuth($canal->valeur('account_sid'), $canal->valeur('auth_token'))
                ->post('https://api.twilio.com/2010-04-01/Accounts/' . $canal->valeur('account_sid') . '/Messages.json',
                    ['To' => 'whatsapp:+' . $numero, 'From' => $canal->valeur('expediteur'), 'Body' => $message]), 'sid'),
            default => self::reponse(Http::withToken((string) $canal->valeur('access_token'))
                ->post('https://graph.facebook.com/' . $canal->valeur('version', 'v20.0') . '/' . $canal->valeur('phone_number_id') . '/messages',
                    ['messaging_product' => 'whatsapp', 'to' => $numero, 'type' => 'text', 'text' => ['body' => $message]]), 'messages.0.id'),
        });
    }

    // ------------------------------------------------------------------ test depuis l'écran

    public static function tester(Canal $canal, string $destinataire): string
    {
        $message = 'Test de configuration ' . $canal->code . ' — ' . self::nomAgence() . ' — ' . now()->format('d/m/Y H:i');
        $statut = $canal->code === 'EMAIL'
            ? self::email($destinataire, 'Test d\'envoi', '<p>' . e($message) . '</p><p>Si vous lisez ce message, la configuration est correcte.</p>', null, 'TEST', [], $canal)
            : self::envoyer($canal->code, $destinataire, $message, null, null, 'TEST', [], $canal);
        $dernier = Envoi::where('canal', $canal->code)->latest('id')->first();
        $canal->forceFill(['dernier_test_at' => now(), 'dernier_test_statut' => in_array($statut, ['ENVOYE', 'SIMULE'], true) ? 'SUCCES' : 'ECHEC',
            'dernier_test_message' => $statut === 'ECHEC' ? $dernier?->erreur : $statut])->saveQuietly();

        return $statut;
    }

    // ------------------------------------------------------------------ modèles

    /** Modèle de l'agence courante, créé à partir de config/notifications.php s'il n'existe pas encore. */
    public static function modele(string $code): ?ModeleMessage
    {
        if (!app(AgenceCourante::class)->existe()) {
            return null;
        }

        return ModeleMessage::where('code', $code)->first() ?? (($defaut = config("notifications.$code")) ? self::creerModele($code, $defaut) : null);
    }

    public static function initialiserModeles(): void
    {
        foreach (config('notifications') as $code => $defaut) {
            ModeleMessage::where('code', $code)->exists() || self::creerModele($code, $defaut);
        }
    }

    protected static function creerModele(string $code, array $defaut): ModeleMessage
    {
        [$libelle, $canaux, $sujet, $email, $sms, $variables] = $defaut;

        return ModeleMessage::create(['code' => $code, 'libelle' => $libelle, 'canaux' => $canaux, 'sujet' => $sujet,
            'corps_email' => $email, 'corps_sms' => $sms, 'variables' => $variables . ', {agence}']);
    }

    public static function remplacer(string $texte, array $variables): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $variables) ? (string) $variables[$m[1]] : $m[0], $texte);
    }

    // ------------------------------------------------------------------ outils

    protected static function executer(string $code, ?Canal $canal, string $destinataire, ?string $sujet, ?string $modele, array $objet, callable $envoi): string
    {
        $debut = microtime(true);
        $statut = 'ENVOYE';
        $erreur = $reference = null;
        if ($canal?->sandbox) {
            $statut = 'SIMULE';
            Log::info("[$code sandbox] " . self::masquer($destinataire) . ' : ' . ($sujet ?? ''));
        } else {
            try {
                $reference = $envoi();
            } catch (\Throwable $e) {
                $statut = 'ECHEC';
                $erreur = Str::limit($e->getMessage(), 1000);
                Log::warning("Envoi $code échoué : " . $erreur);
            }
        }
        Envoi::create([
            'agence_id' => app(AgenceCourante::class)->id(), 'canal' => $code, 'fournisseur' => $canal?->fournisseur ?? 'ENV',
            'destinataire' => self::masquer($destinataire), 'sujet' => $sujet ? Str::limit($sujet, 250) : null, 'modele' => $modele,
            'statut' => $statut, 'erreur' => $erreur, 'reference_externe' => is_string($reference) ? Str::limit($reference, 250) : null,
            'duree_ms' => (int) ((microtime(true) - $debut) * 1000), 'objet_type' => $objet['type'] ?? null, 'objet_id' => $objet['id'] ?? null,
            'user_id' => $objet['user_id'] ?? Auth::id(), 'envoye_at' => now(),
        ]);

        return $statut;
    }

    /** Fournisseur « journal uniquement » : le message est écrit dans les logs de l'application. */
    protected static function journaliser(string $code, string $destinataire, string $texte): null
    {
        Log::info("[$code] " . self::masquer($destinataire) . ' : ' . $texte);

        return null;
    }

    protected static function reponse(\Illuminate\Http\Client\Response $r, ?string $cleReference): ?string
    {
        if ($r->failed()) {
            throw new RuntimeException('HTTP ' . $r->status() . ' : ' . Str::limit($r->body(), 300));
        }

        return $cleReference ? (string) data_get($r->json(), $cleReference) : null;
    }

    protected static function smsOrange(Canal $canal, string $numero, string $message): ?string
    {
        $jeton = Cache::remember('orange.sms.' . $canal->id . '.' . md5((string) $canal->valeur('client_id')), 3000, function () use ($canal) {
            $r = Http::asForm()->withBasicAuth($canal->valeur('client_id'), $canal->valeur('client_secret'))
                ->post('https://api.orange.com/oauth/v3/token', ['grant_type' => 'client_credentials']);
            if ($r->failed()) {
                throw new RuntimeException('Authentification Orange refusée : ' . Str::limit($r->body(), 200));
            }

            return (string) $r->json('access_token');
        });
        $expediteur = (string) $canal->valeur('expediteur', 'tel:+2250000');
        $corps = ['outboundSMSMessageRequest' => array_filter(['address' => 'tel:+' . $numero, 'senderAddress' => $expediteur,
            'senderName' => $canal->valeur('nom_expediteur'), 'outboundSMSTextMessage' => ['message' => $message]])];

        return self::reponse(Http::withToken($jeton)->post('https://api.orange.com/smsmessaging/v1/outbound/' . rawurlencode($expediteur) . '/requests', $corps),
            'outboundSMSMessageRequest.resourceURL');
    }

    public static function numero(string $telephone, string $indicatif = '225'): string
    {
        $tel = preg_replace('/\D+/', '', $telephone);

        return str_starts_with($tel, $indicatif) && strlen($tel) > 10 ? $tel : $indicatif . $tel;
    }

    public static function masquer(string $valeur): string
    {
        if (str_contains($valeur, '@')) {
            [$u, $d] = explode('@', $valeur, 2);

            return mb_substr($u, 0, 2) . str_repeat('•', max(1, mb_strlen($u) - 2)) . '@' . $d;
        }

        return strlen($valeur) > 6 ? substr($valeur, 0, 5) . str_repeat('•', strlen($valeur) - 7) . substr($valeur, -2) : $valeur;
    }

    /** Gabarit des e-mails : logo et couleur de l'agence, en-tête, signature et pied de page du canal e-mail. */
    public static function gabarit(): array
    {
        $agence = self::agence();
        $p = (array) (Canal::configuration('EMAIL')?->parametres ?? []);

        return [
            'couleur' => $p['couleur'] ?? ($agence?->couleur ?: '#0d6efd'), 'logo' => $p['logo_url'] ?? $agence?->logo_url,
            'entete' => $p['entete'] ?? self::nomAgence(), 'signature' => $p['signature'] ?? null,
            'pied' => $p['pied_page'] ?? trim(($agence?->adresse ? $agence->adresse . ' — ' : '') . 'Message automatique : merci de ne pas répondre.'),
        ];
    }

    protected static function agence(): ?Agence
    {
        return app(AgenceCourante::class)->agence();
    }

    public static function nomAgence(): string
    {
        return self::agence()?->raison_sociale ?? (string) config('rbac.app_name', config('app.name'));
    }
}
