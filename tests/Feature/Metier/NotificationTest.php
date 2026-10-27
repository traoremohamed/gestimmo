<?php

namespace Tests\Feature\Metier;

use App\Jobs\EnvoiMessageJob;
use App\Models\Integration\Canal;
use App\Models\Integration\Envoi;
use App\Models\Integration\ModeleMessage;
use App\Models\Recouvrement;
use App\Models\RegleRelance;
use App\Services\Finance\EncaissementService;
use App\Services\Integration\NotificationService;
use App\Services\Location\BailService;
use App\Services\Recouvrement\RecouvrementService;
use App\Support\AgenceCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Canaux e-mail / SMS / WhatsApp configurés à l'écran, par agence, avec repli sur la plateforme.
 */
class NotificationTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function canal(string $code, string $fournisseur, array $parametres = [], array $secrets = [], ?int $agenceId = -1, bool $sandbox = false): Canal
    {
        $canal = new Canal(['code' => $code, 'fournisseur' => $fournisseur, 'parametres' => $parametres, 'actif' => true, 'sandbox' => $sandbox]);
        $canal->agence_id = $agenceId === -1 ? $this->agence->id : $agenceId;
        $canal->definirSecrets($secrets);
        $canal->save();

        return $canal;
    }

    public function test_configuration_a_l_ecran_secrets_chiffres_et_conserves(): void
    {
        $this->seederRbac();
        $this->creerAgence('NOTIF');
        $this->actingAs($this->utilisateur());
        app(AgenceCourante::class)->definir(null);

        $this->post(route('integrations.enregistrer', 'WHATSAPP'), ['fournisseur' => 'META', 'actif' => 1,
            'valeurs' => ['phone_number_id' => '123456', 'access_token' => 'JETON-SECRET', 'version' => 'v20.0', 'indicatif' => '225']])->assertRedirect();

        $canal = Canal::where('code', 'WHATSAPP')->firstOrFail();
        $this->assertSame($this->agence->id, $canal->agence_id);
        $this->assertStringNotContainsString('JETON-SECRET', (string) $canal->getRawOriginal('secrets'));
        $this->assertSame('JETON-SECRET', $canal->valeur('access_token'));

        // secret laissé vide : conservé ; jamais réaffiché à l'écran
        $this->post(route('integrations.enregistrer', 'WHATSAPP'), ['fournisseur' => 'META', 'actif' => 1, 'valeurs' => ['phone_number_id' => '999', 'access_token' => '']]);
        $this->assertSame('JETON-SECRET', $canal->fresh()->valeur('access_token'));
        $this->assertSame('999', $canal->fresh()->valeur('phone_number_id'));
        foreach (['EMAIL', 'SMS', 'WHATSAPP', 'GABARIT', 'MODELES', 'JOURNAL'] as $onglet) {
            $this->get(route('integrations.index', ['onglet' => $onglet]))->assertOk()->assertDontSee('JETON-SECRET');
        }
        $this->get(route('integrations.apercu'))->assertOk();
        $this->assertSame(count(config('notifications')), ModeleMessage::toutesAgences()->where('agence_id', $this->agence->id)->count());
    }

    public function test_canal_de_l_agence_prioritaire_sinon_canal_de_la_plateforme(): void
    {
        $this->creerAgence('A');
        $plateforme = $this->canal('SMS', 'LOG', [], [], null);
        $this->assertSame($plateforme->id, Canal::actif('SMS')->id);

        $propre = $this->canal('SMS', 'TWILIO');
        $this->assertSame($propre->id, Canal::actif('SMS')->id);

        $this->creerAgence('B');   // une autre agence ne voit que la plateforme
        $this->assertSame($plateforme->id, Canal::actif('SMS')->id);
    }

    public function test_whatsapp_meta_sms_orange_sandbox_et_echec_journalises(): void
    {
        $this->creerAgence();
        Http::fake([
            'graph.facebook.com/v20.0/KO/*' => Http::response(['error' => 'Invalid token'], 401),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.42']]]),
            'api.orange.com/oauth/*' => Http::response(['access_token' => 'tok']),
            'api.orange.com/smsmessaging/*' => Http::response(['outboundSMSMessageRequest' => ['resourceURL' => 'https://orange/sms/1']], 201),
        ]);
        $this->canal('WHATSAPP', 'META', ['phone_number_id' => '555', 'version' => 'v20.0', 'indicatif' => '225'], ['access_token' => 'meta-token']);
        $this->canal('SMS', 'ORANGE_CI', ['client_id' => 'id', 'expediteur' => 'tel:+2250000', 'indicatif' => '225'], ['client_secret' => 's']);

        $this->assertSame('ENVOYE', NotificationService::whatsapp('07 00 00 00 01', 'Bonjour'));
        Http::assertSent(fn ($r) => $r->url() === 'https://graph.facebook.com/v20.0/555/messages' && $r['to'] === '2250700000001'
            && $r['text']['body'] === 'Bonjour' && $r->hasHeader('Authorization', 'Bearer meta-token'));
        $this->assertSame('ENVOYE', NotificationService::sms('0700000001', 'Rappel'));

        $envoi = Envoi::where('canal', 'WHATSAPP')->firstOrFail();
        $this->assertSame('wamid.42', $envoi->reference_externe);
        $this->assertStringNotContainsString('0700000001', $envoi->destinataire);   // destinataire masqué
        $this->assertSame($this->agence->id, $envoi->agence_id);

        // sandbox : rien n'est transmis, l'envoi est simulé
        Http::assertSentCount(3);   // WhatsApp + jeton Orange + SMS
        Canal::where('code', 'SMS')->update(['sandbox' => true]);
        $this->assertSame('SIMULE', NotificationService::sms('0700000001', 'Rappel'));
        Http::assertSentCount(3);

        // refus de la passerelle : échec journalisé avec la cause, sans exception
        Canal::where('code', 'WHATSAPP')->update(['parametres' => ['phone_number_id' => 'KO', 'version' => 'v20.0']]);
        $this->assertSame('ECHEC', NotificationService::whatsapp('0700000001', 'x'));
        $this->assertStringContainsString('Invalid token', Envoi::latest('id')->first()->erreur);
    }

    public function test_encaissement_envoie_recu_et_quittance_selon_les_modeles(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-01-10');
        $this->creerAgence();
        ModeleMessage::where('code', 'RECU_PAIEMENT')->update(['canaux' => ['EMAIL', 'WHATSAPP']]);

        $locataire = $this->tiers(['email' => 'loc@test.ci', 'telephone' => '0700000003']);
        $baux = app(BailService::class);
        $baux->activer($baux->creer($this->bien(['a_louer' => true, 'loyer_mensuel' => 100000]), $locataire,
            ['date_debut' => '2026-01-01', 'mois_caution' => 0, 'mois_avance' => 0, 'frais_agence' => 0]));
        $caisse = app(EncaissementService::class);
        $recu = $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($caisse->dettesOuvertes($locataire), 110000));

        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->canal === 'EMAIL' && $j->modele === 'RECU_PAIEMENT'
            && $j->agenceId === $this->agence->id && str_contains($j->message, $recu->numero) && str_contains($j->message, '110 000'));
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->canal === 'WHATSAPP' && $j->modele === 'RECU_PAIEMENT');
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->modele === 'QUITTANCE_LOYER');

        // modèle désactivé : plus d'envoi
        ModeleMessage::where('code', 'RECU_PAIEMENT')->update(['actif' => false]);
        $this->assertSame([], NotificationService::notifier('RECU_PAIEMENT', ['email' => 'x@test.ci']));
    }

    public function test_relance_whatsapp_et_job_execute_avec_le_canal_de_l_agence_emettrice(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        Carbon::setTestNow('2026-01-10');
        $agence = $this->creerAgence('EMET');
        $this->canal('WHATSAPP', 'META', ['phone_number_id' => 'EMETTRICE'], ['access_token' => 't']);
        $locataire = $this->tiers(['telephone' => '0700000004']);
        $baux = app(BailService::class);
        $baux->activer($baux->creer($this->bien(['a_louer' => true, 'loyer_mensuel' => 50000]), $locataire,
            ['date_debut' => '2026-01-01', 'mois_caution' => 0, 'mois_avance' => 0, 'frais_agence' => 0]));
        RegleRelance::where('niveau', 1)->update(['canal' => 'whatsapp', 'delai_jours' => 3]);

        $service = app(RecouvrementService::class);
        $service->analyser();
        $this->assertSame(1, $service->relancesAutomatiques());
        $this->assertSame('whatsapp', Recouvrement::firstOrFail()->relances()->first()->canal);

        // file synchrone : le message est parti par le canal WhatsApp de l'agence émettrice et journalisé pour elle
        Http::assertSent(fn ($r) => str_contains($r->url(), '/EMETTRICE/messages'));
        $envoi = Envoi::where('canal', 'WHATSAPP')->firstOrFail();
        $this->assertSame($agence->id, $envoi->agence_id);
        $this->assertSame('RELANCE', $envoi->modele);
    }
}
