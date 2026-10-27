@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Contrats de vente" module="Ventes">
    <x-slot:actions>
        @can('ventes-create')
            <a href="{{ route('ventes.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Nouveau contrat</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.input name="q" label="Recherche" :value="request('q')" placeholder="N° de contrat, client…" col="col-md-4" />
                <x-form.select name="statut" label="Statut" :options="\App\Enums\StatutVente::options()" :value="request('statut')" placeholder="Tous" col="col-md-3" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Contrat</th><th>Acquéreur</th><th>Biens</th><th class="text-end">Prix net</th><th>Commercial</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse($ventes as $vente)
                        <tr class="ligne-cliquable" data-href="{{ route('ventes.show', $vente) }}">
                            <td><a href="{{ route('ventes.show', $vente) }}" class="fw-medium">{{ $vente->numero ?? 'Brouillon #' . $vente->id }}</a>
                                <div class="small text-muted">{{ $vente->date_contrat?->format('d/m/Y') }}</div></td>
                            <td>{{ $vente->acquereur?->nom_complet }}</td>
                            <td class="small">{{ $vente->biens->pluck('reference')->implode(', ') }}</td>
                            <td class="text-end montant">{{ montant($vente->montant_net) }}</td>
                            <td>{{ $vente->commercial?->nom_complet }}</td>
                            <td><x-statut :valeur="$vente->statut" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-vide message="Aucun contrat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ventes->hasPages())<div class="card-footer">{{ $ventes->links() }}</div>@endif
    </div>
</x-page>
@endsection
