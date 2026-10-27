<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Integration\Canal;
use App\Models\Integration\Envoi;
use App\Models\Integration\ModeleMessage;
use App\Services\Integration\CatalogueCanaux;
use App\Services\Integration\NotificationService;
use App\Support\AgenceCourante;
use Illuminate\Http\Request;

/**
 * Configuration des canaux d'envoi (e-mail, SMS, WhatsApp), du gabarit d'e-mail et des messages, repris du SIH.
 * Dans une agence : canaux propres à l'agence (sinon ceux de la plateforme s'appliquent).
 * Hors agence (super administrateur) : canaux de la plateforme. Secrets chiffrés, jamais réaffichés.
 */
class IntegrationController extends Controller
{
    public function index(Request $request, AgenceCourante $agence)
    {
        $canaux = $plateforme = [];
        foreach (CatalogueCanaux::CANAUX as $code => $def) {
            $canaux[$code] = Canal::configuration($code) ?? new Canal(['code' => $code, 'fournisseur' => array_key_first($def['fournisseurs'])]);
            $plateforme[$code] = $agence->existe() ? Canal::where('code', $code)->whereNull('agence_id')->where('actif', true)->exists() : false;
        }
        if ($agence->existe()) {
            NotificationService::initialiserModeles();
        }

        return view('admin.integrations', [
            'onglet' => $request->input('onglet', 'EMAIL'), 'canaux' => $canaux, 'plateforme' => $plateforme, 'catalogue' => CatalogueCanaux::CANAUX,
            'enAgence' => $agence->existe(), 'gabarit' => NotificationService::gabarit(),
            'modeles' => $agence->existe() ? ModeleMessage::orderBy('libelle')->get() : collect(),
            'envois' => Envoi::query()->when($request->input('canal'), fn ($q, $c) => $q->where('canal', $c))
                ->when($request->input('statut'), fn ($q, $s) => $q->where('statut', $s))->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function enregistrer(Request $request, AgenceCourante $agence, string $code)
    {
        $def = CatalogueCanaux::definition($code);
        $fournisseur = $request->validate(['fournisseur' => 'required|in:' . implode(',', array_keys($def['fournisseurs']))])['fournisseur'];
        $champs = CatalogueCanaux::champs($code, $fournisseur);
        $regles = [];
        foreach ($champs as $nom => [, $type]) {
            $regles["valeurs.$nom"] = match ($type) {
                'number' => 'nullable|integer|between:1,65535', 'email' => 'nullable|email|max:255', 'url' => 'nullable|url|max:500', default => 'nullable|string|max:500',
            };
        }
        $donnees = $request->validate($regles + ['actif' => 'nullable|boolean', 'sandbox' => 'nullable|boolean']);

        $canal = Canal::configuration($code) ?? new Canal(['code' => $code, 'agence_id' => $agence->id()]);
        $parametres = array_intersect_key((array) $canal->parametres, array_flip(['couleur', 'logo_url', 'entete', 'pied_page', 'signature']));
        $secrets = $canal->exists ? $canal->secretsDechiffres() : [];
        foreach ($champs as $nom => [, , $secret]) {
            $valeur = $donnees['valeurs'][$nom] ?? null;
            if (!$secret) {
                $parametres[$nom] = $valeur;
            } elseif ($valeur !== null && $valeur !== '') {          // vide = conserver le secret enregistré
                $secrets[$nom] = $valeur;
            }
        }
        $canal->fill(['fournisseur' => $fournisseur, 'parametres' => $parametres, 'actif' => $request->boolean('actif'), 'sandbox' => $request->boolean('sandbox')]);
        $canal->definirSecrets(array_intersect_key($secrets, array_filter($champs, fn ($c) => $c[2])));
        $canal->save();

        Audit::logSave(['action' => 'CONFIGURER CANAL ' . $code, 'code_piece' => $canal->id, 'menu' => 'INTEGRATIONS', 'etat' => 'Succès', 'objet' => 'PARAMETRAGE']);

        return redirect()->route('integrations.index', ['onglet' => $code])->with('success', $def['libelle'] . ' : configuration enregistrée, appliquée dès le prochain envoi.');
    }

    public function tester(Request $request, string $code)
    {
        $canal = Canal::configuration($code);
        if (!$canal) {
            return back()->with('error', 'Enregistrez d\'abord la configuration.');
        }
        $destinataire = $request->validate(['destinataire' => $code === 'EMAIL' ? 'required|email|max:255' : 'required|string|max:30'])['destinataire'];

        try {
            $statut = NotificationService::tester($canal, $destinataire);
        } catch (\Throwable $e) {
            return back()->with('error', 'Échec du test : ' . $e->getMessage());
        }

        return back()->with($statut === 'ECHEC' ? 'error' : 'success', match ($statut) {
            'ENVOYE' => 'Message de test envoyé.', 'SIMULE' => 'Mode sandbox : envoi simulé et journalisé.',
            'IGNORE' => 'Rien envoyé (canal ou destinataire manquant).', default => 'Échec : ' . $canal->fresh()->dernier_test_message,
        });
    }

    public function gabarit(Request $request, AgenceCourante $agence)
    {
        $donnees = $request->validate(['couleur' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'logo_url' => 'nullable|url|max:500', 'entete' => 'nullable|string|max:120',
            'pied_page' => 'nullable|string|max:1000', 'signature' => 'nullable|string|max:500']);
        $canal = Canal::configuration('EMAIL') ?? new Canal(['code' => 'EMAIL', 'fournisseur' => 'SMTP', 'agence_id' => $agence->id()]);
        $canal->parametres = array_merge((array) $canal->parametres, $donnees);
        $canal->save();

        return redirect()->route('integrations.index', ['onglet' => 'GABARIT'])->with('success', 'Modèle d\'e-mail enregistré.');
    }

    public function apercu()
    {
        return response(view('notifications.email', ['sujet' => 'Aperçu', 'nom' => 'M. KOUASSI Jean',
            'contenu' => '<p>Nous accusons réception de votre paiement de <strong>150 000 FCFA</strong> du 05/11/2026 (Espèces).</p><p>Référence du reçu : REC-2026-00042</p>',
            'gabarit' => NotificationService::gabarit()])->render());
    }

    public function modele(Request $request, ModeleMessage $modele)
    {
        $donnees = $request->validate(['sujet' => 'nullable|string|max:255', 'corps_email' => 'nullable|string|max:10000', 'corps_sms' => 'nullable|string|max:640',
            'canaux' => 'nullable|array', 'canaux.*' => 'in:' . implode(',', array_keys(NotificationService::CANAUX)), 'actif' => 'nullable|boolean']);
        $modele->update(['sujet' => $donnees['sujet'] ?? null, 'corps_email' => $donnees['corps_email'] ?? null, 'corps_sms' => $donnees['corps_sms'] ?? null,
            'canaux' => array_values($donnees['canaux'] ?? []), 'actif' => $request->boolean('actif')]);

        return redirect()->route('integrations.index', ['onglet' => 'MODELES'])->with('success', 'Message « ' . $modele->libelle . ' » enregistré.');
    }
}
