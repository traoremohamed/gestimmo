@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Encaissements" module="Caisse & finances">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        @can('encaissements-create')
            <a href="{{ route('encaissements.create') }}" class="btn btn-success btn-sm"><i class="ti ti-cash"></i> Encaisser</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.input name="q" label="Recherche" :value="request('q')" placeholder="N° de reçu, payeur…" col="col-md-3" />
                <x-form.input name="du" label="Du" type="date" :value="request('du')" col="col-md-2" />
                <x-form.input name="au" label="Au" type="date" :value="request('au')" col="col-md-2" />
                <x-form.select name="activite" label="Activité" :options="\App\Models\Encaissement::ACTIVITES" :value="request('activite')" placeholder="Toutes" col="col-md-2" />
                <x-form.select name="mode_paiement" label="Mode" :options="$modes" :value="request('mode_paiement')" placeholder="Tous" col="col-md-2" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i></button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Reçu</th><th>Date</th><th>Payeur</th><th>Activité</th><th>Mode</th><th class="text-end">Montant</th><th>Caissier</th><th></th></tr></thead>
                <tbody>
                    @forelse($encaissements as $e)
                        <tr class="ligne-cliquable {{ $e->estAnnule() ? 'text-muted' : '' }}" data-href="{{ route('encaissements.show', $e) }}">
                            <td><a href="{{ route('encaissements.show', $e) }}">{{ $e->numero }}</a> @if($e->estAnnule())<span class="badge bg-danger">Annulé</span>@endif</td>
                            <td>{{ $e->date_encaissement->format('d/m/Y') }}</td>
                            <td>{{ $e->tiers?->nom_complet }}</td>
                            <td>{{ \App\Models\Encaissement::ACTIVITES[$e->activite] ?? $e->activite }}</td>
                            <td>{{ $modes[$e->mode_paiement] ?? $e->mode_paiement }} <span class="small text-muted">{{ $e->reference }}</span></td>
                            <td class="text-end montant {{ $e->estAnnule() ? 'text-decoration-line-through' : 'fw-medium' }}">{{ montant($e->montant) }}</td>
                            <td class="small">{{ $e->caissier?->nom_complet }}</td>
                            <td class="text-end"><a href="{{ route('encaissements.recu', $e) }}" target="_blank" class="btn btn-sm btn-light" title="Reçu PDF"><i class="ti ti-printer"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucun encaissement." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($encaissements->hasPages())<div class="card-footer">{{ $encaissements->links() }}</div>@endif
    </div>
</x-page>
@endsection
