@extends('layouts.backLayout.designadmin')

@php use App\Models\Recouvrement; @endphp

@section('content')
<x-page titre="Recouvrement des impayés" module="Recouvrement">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        @can('recouvrements-edit')
            <x-action :url="route('recouvrements.analyser')" libelle="Actualiser les impayés" icone="ti-refresh" classe="btn btn-sm btn-outline-primary" />
        @endcan
        @can('recouvrements-relancer')
            <x-action :url="route('recouvrements.relancerTout')" libelle="Lancer les relances automatiques" icone="ti-send" classe="btn btn-sm btn-primary"
                confirmer="Envoyer maintenant les relances dues selon les niveaux paramétrés ?" />
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Total à recouvrer" :valeur="montant($total)" icone="ti-alarm" :detail="$nombre . ' débiteur(s)'" />
        @foreach(Recouvrement::ACTIVITES as $code => $libelle)
            @if($ligne = $parActivite->get($code))
                <x-stat :libelle="$libelle" :valeur="montant($ligne->total)" icone="ti-folder" :detail="$ligne->nombre . ' dossier(s)'" :lien="route('recouvrements.index', ['activite' => $code])" />
            @endif
        @endforeach
        <x-stat libelle="En contentieux" :valeur="montant($contentieux)" icone="ti-gavel" :lien="route('recouvrements.index', ['statut' => 'contentieux'])" />
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="small text-muted mb-2">Ancienneté des impayés (date de la plus ancienne échéance)</div>
            @php $max = max(1, $parTranche->max()); @endphp
            <div class="row g-3">
                @foreach($parTranche as $tranche => $montant)
                    <div class="col-6 col-md-3">
                        <div class="d-flex justify-content-between small"><span>{{ $tranche }}</span><strong class="montant">{{ montant($montant) }}</strong></div>
                        <div class="progress" style="height: 8px" role="progressbar" aria-label="{{ $tranche }}" aria-valuenow="{{ $montant }}" aria-valuemin="0" aria-valuemax="{{ $max }}">
                            <div class="progress-bar {{ $loop->last ? 'bg-danger' : ($loop->index >= 2 ? 'bg-warning' : '') }}" style="width: {{ round($montant / $max * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.input name="q" label="Débiteur" :value="request('q')" placeholder="Nom, téléphone…" col="col-md-3" />
                <x-form.select name="activite" label="Activité" :options="Recouvrement::ACTIVITES" :value="request('activite')" placeholder="Toutes" col="col-md-2" />
                <x-form.select name="statut" label="Statut" :options="collect(Recouvrement::STATUTS)->map(fn ($s) => $s[0])" :value="request('statut')" placeholder="Dossiers ouverts" col="col-md-3" />
                <x-form.select name="action" label="Échéance d'action" :options="['jour' => 'À traiter aujourd\'hui']" :value="request('action')" placeholder="Toutes" col="col-md-2" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i></button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Débiteur</th><th>Activité</th><th class="text-end">Montant dû</th><th class="text-end">Impayés</th><th>Retard</th><th>Niveau</th><th>Prochaine action</th><th>Gestionnaire</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse($dossiers as $d)
                        @php($retard = $d->joursRetard())
                        <tr class="ligne-cliquable" data-href="{{ route('recouvrements.show', $d) }}" title="Ouvrir le dossier pour procéder au recouvrement">
                            <td><a href="{{ route('recouvrements.show', $d) }}" class="fw-medium">{{ $d->tiers->nom_complet }}</a><div class="small text-muted">{{ $d->tiers->contact }}</div></td>
                            <td>{{ Recouvrement::ACTIVITES[$d->activite] }}</td>
                            <td class="text-end montant fw-semibold">{{ montant($d->montant_du) }}</td>
                            <td class="text-end">{{ $d->nombre_impayes }}</td>
                            <td><span class="badge bg-{{ $retard > 90 ? 'danger' : ($retard > 30 ? 'warning text-dark' : 'light text-dark') }}">{{ $retard }} j</span></td>
                            <td>{{ $d->niveau ?: '—' }} <span class="small text-muted">({{ $d->relances_count }} relance(s))</span></td>
                            <td class="{{ $d->prochaine_action?->lte(today()) ? 'text-danger fw-medium' : '' }}">{{ $d->prochaine_action?->format('d/m/Y') }}</td>
                            <td class="small">{{ $d->gestionnaire?->nom_complet }}</td>
                            <td><span class="badge bg-{{ $d->couleurStatut() }}">{{ $d->libelleStatut() }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('recouvrements.show', $d) }}" class="btn btn-sm btn-primary"><i class="ti ti-hand-finger-right"></i> Recouvrer</a>
                                @if($d->tiers->telephone)<a href="tel:{{ preg_replace('/\s+/', '', ($d->tiers->indicatif ?: '') . $d->tiers->telephone) }}" class="btn btn-sm btn-light" title="Appeler"><i class="ti ti-phone"></i></a>@endif
                                @can('encaissements-create')<a href="{{ route('encaissements.create', ['tiers_id' => $d->tiers_id]) }}" class="btn btn-sm btn-light" title="Encaisser"><i class="ti ti-cash"></i></a>@endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-vide message="Aucun dossier : cliquez sur « Actualiser les impayés »." icone="ti-mood-happy" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($dossiers->hasPages())<div class="card-footer">{{ $dossiers->links() }}</div>@endif
    </div>
</x-page>
@endsection
