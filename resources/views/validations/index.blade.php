@extends('layouts.backLayout.designadmin')

@php($statuts = \App\Models\Validation\Demande::STATUTS)

@section('content')
<x-page titre="Validations" module="Validations">
    <x-slot:actions>
        @can('circuits-index')<a href="{{ route('circuits.index') }}" class="btn btn-sm btn-light"><i class="ti ti-git-merge"></i> Circuits de validation</a>@endcan
    </x-slot:actions>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $onglet === 'a_decider' ? 'active' : '' }}" href="{{ route('validations.index') }}">
            <i class="ti ti-gavel"></i> À décider @if($aDecider->count())<span class="badge bg-warning text-dark">{{ $aDecider->count() }}</span>@endif</a></li>
        <li class="nav-item"><a class="nav-link {{ $onglet === 'mes_demandes' ? 'active' : '' }}" href="{{ route('validations.index', ['onglet' => 'mes_demandes']) }}"><i class="ti ti-send"></i> Mes demandes</a></li>
        @can('validations-toutes')
            <li class="nav-item"><a class="nav-link {{ $onglet === 'toutes' ? 'active' : '' }}" href="{{ route('validations.index', ['onglet' => 'toutes']) }}"><i class="ti ti-list-check"></i> Toutes</a></li>
        @endcan
    </ul>

    @if($onglet === 'toutes')
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="onglet" value="toutes">
            <x-form.select name="statut" label="Statut" :options="collect($statuts)->map(fn ($s) => $s[0])" :value="request('statut')" col="col-md-3" placeholder="Tous" />
            <x-form.select name="action" label="Action" :options="$actions" :value="request('action')" col="col-md-5" placeholder="Toutes" />
            <div class="col-auto align-self-end mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button></div>
        </form>
    @endif

    @php($lignes = $onglet === 'a_decider' ? $aDecider : $liste)
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Demande</th><th>Action</th><th class="text-end">Montant</th><th>Demandeur</th><th>Étape</th><th>Statut</th></tr></thead>
                <tbody>
                @forelse($lignes as $d)
                    <tr class="ligne-cliquable" data-href="{{ route('validations.show', $d) }}">
                        <td><a href="{{ route('validations.show', $d) }}" class="fw-semibold">{{ $d->numero }}</a><div class="small text-muted">{{ $d->demande_at->format('d/m/Y H:i') }}</div></td>
                        <td>{{ \App\Services\Validation\Catalogue::libelle($d->action) }}<div class="small text-muted">{{ $d->reference }}</div></td>
                        <td class="text-end montant">{{ $d->montant !== null ? montant($d->montant) : '—' }}</td>
                        <td class="small">{{ $d->demandeur?->nom_complet }}</td>
                        <td class="small">
                            @if($d->etape_courante)
                                {{ $d->circuit?->etapes->firstWhere('ordre', $d->etape_courante)?->libelle }}
                                @if($d->echeance_etape_at?->isPast())<span class="badge badge-soft-danger">hors délai</span>@endif
                            @else — @endif
                        </td>
                        <td><span class="badge text-bg-{{ $d->couleurStatut() }}">{{ $d->libelleStatut() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-vide :message="$onglet === 'a_decider' ? 'Aucune demande à décider.' : 'Aucune demande.'" icone="ti-checks" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($onglet !== 'a_decider' && $liste)<div class="card-footer">{{ $liste->links() }}</div>@endif
    </div>
</x-page>
@endsection
