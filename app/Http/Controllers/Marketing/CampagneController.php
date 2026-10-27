<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\RoleTiers;
use App\Helpers\Audit;
use App\Http\Controllers\CrudController;
use App\Models\Campagne;
use App\Models\Referentiel;
use App\Services\Marketing\CampagneService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CampagneController extends CrudController
{
    protected string $modele = Campagne::class;

    protected string $route = 'campagnes';

    protected string $titre = 'Campagnes';

    protected string $titreSingulier = 'Campagne';

    protected string $module = 'Marketing';

    protected array $recherche = ['nom', 'objet'];

    protected ?string $vueFiche = 'marketing.campagne';

    public function __construct(protected CampagneService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'nom', 'label' => 'Nom de la campagne', 'requis' => true],
            ['nom' => 'canal', 'label' => 'Canal', 'type' => 'select', 'options' => Campagne::CANAUX, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'email'],
            ['nom' => 'objet', 'label' => 'Objet (e-mail)', 'col' => 'col-md-3'],
            ['nom' => '_t', 'label' => 'Cible', 'type' => 'titre'],
            ['nom' => 'cible_roles', 'label' => 'Rôles', 'type' => 'select', 'multiple' => true, 'options' => RoleTiers::options(), 'col' => 'col-md-6',
                'regles' => ['nullable', 'array'], 'defaut' => $element?->cible['roles'] ?? []],
            ['nom' => 'cible_canal', 'label' => 'Canal d\'acquisition', 'type' => 'select', 'options' => fn () => Referentiel::options('canal_acquisition'), 'col' => 'col-md-3',
                'defaut' => $element?->cible['canal_acquisition'] ?? null],
            ['nom' => 'cible_type_bien', 'label' => 'Prospects intéressés par', 'type' => 'select', 'options' => fn () => Referentiel::options('type_bien'), 'col' => 'col-md-3',
                'defaut' => $element?->cible['type_bien'] ?? null],
            ['nom' => 'cible_newsletter', 'label' => 'Inclure les abonnés newsletter du site', 'type' => 'checkbox', 'defaut' => $element?->cible['newsletter'] ?? false],
            ['nom' => 'message', 'label' => 'Message ({nom} est remplacé par le nom du destinataire)', 'type' => 'textarea', 'requis' => true, 'rows' => 6],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Campagne', fn ($c) => Cellule::deuxLignes($c->nom, $c->objet)],
            ['Canal', fn ($c) => Campagne::CANAUX[$c->canal]],
            ['Destinataires', fn ($c) => $c->nb_destinataires, 'text-end'],
            ['Envoyée le', fn ($c) => $c->envoyee_le?->format('d/m/Y H:i')],
            ['Statut', fn ($c) => Cellule::badge($c->statut === 'envoyee' ? 'Envoyée' : 'Brouillon', $c->statut === 'envoyee' ? 'success' : 'secondary')],
        ];
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        abort_if($element?->statut === 'envoyee', 403, 'Une campagne envoyée ne peut plus être modifiée.');

        $donnees['cible'] = array_filter([
            'roles' => $donnees['cible_roles'] ?? [],
            'canal_acquisition' => $donnees['cible_canal'] ?? null,
            'type_bien' => $donnees['cible_type_bien'] ?? null,
            'newsletter' => $request->boolean('cible_newsletter'),
        ]);
        unset($donnees['cible_roles'], $donnees['cible_canal'], $donnees['cible_type_bien'], $donnees['cible_newsletter']);

        return $element ? $donnees : $donnees + ['statut' => 'brouillon', 'created_by' => auth()->id()];
    }

    public function show(string $id)
    {
        $campagne = $this->trouver($id);

        return view('marketing.campagne', $this->donneesVue() + [
            'campagne' => $campagne,
            'destinataires' => $campagne->statut === 'envoyee' ? $campagne->destinataires()->get() : $this->service->destinataires($campagne),
        ]);
    }

    public function envoyer(Campagne $campagne)
    {
        $nombre = $this->service->envoyer($campagne);
        Audit::logSave(['action' => 'ENVOYER', 'code_piece' => $campagne->id, 'menu' => 'CAMPAGNES', 'etat' => 'Succès', 'objet' => 'MARKETING']);

        return back()->with('success', 'Campagne mise en file d\'envoi pour ' . $nombre . ' destinataire(s).');
    }
}
