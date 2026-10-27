<?php

namespace App\Http\Controllers\Tiers;

use App\Enums\StatutRendezVous;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Programme;
use App\Models\Referentiel;
use App\Models\RendezVous;
use App\Models\Tiers;
use App\Models\User;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Agenda des visites, signatures, états des lieux. Un agent ne voit que ses rendez-vous,
 * sauf permission rendezvous-tous. Les demandes de visite du site vitrine arrivent ici.
 */
class RendezVousController extends CrudController
{
    protected string $modele = RendezVous::class;

    protected string $route = 'rendezvous';

    protected string $titre = 'Rendez-vous';

    protected string $titreSingulier = 'Rendez-vous';

    protected string $module = 'Tiers & CRM';

    protected array $with = ['tiers', 'bien', 'agent', 'programme'];

    protected string $tri = 'debut';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'tiers_id', 'label' => 'Tiers', 'type' => 'select', 'recherche' => true, 'defaut' => request('tiers_id'),
                'options' => fn () => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'objet', 'label' => 'Objet', 'type' => 'select', 'options' => fn () => Referentiel::options('objet_rdv'), 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'VISITE'],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => StatutRendezVous::options(), 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'planifie'],
            ['nom' => 'debut', 'label' => 'Date et heure', 'type' => 'datetime', 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'fin', 'label' => 'Fin', 'type' => 'datetime', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'after:debut']],
            ['nom' => 'reporte_au', 'label' => 'Reporté au', 'type' => 'datetime', 'col' => 'col-md-3'],
            ['nom' => 'user_id', 'label' => 'Agent', 'type' => 'select', 'col' => 'col-md-3', 'defaut' => auth()->id(),
                'options' => fn () => User::where('agence_id', agence()?->id)->orderBy('name')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'bien_id', 'label' => 'Bien concerné', 'type' => 'select', 'recherche' => true, 'col' => 'col-md-6', 'defaut' => request('bien_id'),
                'options' => fn () => Bien::orderBy('reference')->get()->pluck('designation', 'id')],
            ['nom' => 'programme_id', 'label' => 'Programme', 'type' => 'select', 'col' => 'col-md-6', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'lieu', 'label' => 'Lieu', 'col' => 'col-md-6'],
            ['nom' => 'contact_nom', 'label' => 'Contact (sans fiche tiers)', 'col' => 'col-md-6'],
            ['nom' => 'contact_telephone', 'label' => 'Téléphone du contact', 'col' => 'col-md-3'],
            ['nom' => 'contact_email', 'label' => 'E-mail du contact', 'type' => 'email', 'col' => 'col-md-3'],
            ['nom' => 'commentaire', 'label' => 'Commentaire', 'type' => 'textarea', 'col' => 'col-md-6'],
            ['nom' => 'compte_rendu', 'label' => 'Compte rendu après rendez-vous', 'type' => 'textarea', 'col' => 'col-md-6'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Date', fn ($r) => Cellule::deuxLignes($r->debut->locale('fr')->translatedFormat('D d M Y'), $r->debut->format('H:i'))],
            ['Interlocuteur', fn ($r) => $r->tiers ? Cellule::lien($r->interlocuteur, route('tiers.show', $r->tiers_id)) : Cellule::deuxLignes($r->contact_nom, $r->contact_telephone)],
            ['Objet', fn ($r) => Referentiel::libelle('objet_rdv', $r->objet)],
            ['Bien / programme', fn ($r) => $r->bien?->designation ?? $r->programme?->nom],
            ['Agent', fn ($r) => $r->agent?->nom_complet],
            ['Source', fn ($r) => $r->source === 'vitrine' ? new HtmlString('<span class="badge bg-info text-dark">Site web</span>') : ''],
            ['Statut', fn ($r) => Cellule::statut($r->statut)],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'periode', 'label' => 'Période', 'options' => ['jour' => 'Aujourd\'hui', 'semaine' => 'Cette semaine', 'avenir' => 'À venir', 'passes' => 'Passés']],
            ['nom' => 'statut', 'label' => 'Statut', 'options' => StatutRendezVous::options()],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $this->restreindre($query)
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->periode === 'jour', fn ($q) => $q->whereDate('debut', today()))
            ->when($request->periode === 'semaine', fn ($q) => $q->whereBetween('debut', [now()->startOfWeek(), now()->endOfWeek()]))
            ->when($request->periode === 'avenir', fn ($q) => $q->where('debut', '>=', now()))
            ->when($request->periode === 'passes', fn ($q) => $q->where('debut', '<', now()));
    }

    protected function trouver(string $id): Model
    {
        return $this->restreindre(RendezVous::query()->with($this->with))->findOrFail($id);
    }

    protected function restreindre(Builder $query): Builder
    {
        return auth()->user()->can('rendezvous-tous') ? $query : $query->where(fn ($q) => $q->where('user_id', auth()->id())->orWhereNull('user_id'));
    }
}
