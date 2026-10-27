@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Appels de fonds" module="Syndic">
    <x-slot:actions>
        @can('appelsfonds-create')
            <a href="{{ route('appelsfonds.create', ['copropriete_id' => request('copropriete_id')]) }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Nouvel appel</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.select name="copropriete_id" label="Copropriété" :options="$coproprietes" :value="request('copropriete_id')" placeholder="Toutes" col="col-md-4" />
                <x-form.select name="statut" label="Statut" :options="\App\Enums\StatutAppelFonds::options()" :value="request('statut')" placeholder="Tous" col="col-md-3" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>N°</th><th>Copropriété</th><th>Libellé</th><th>Appel / échéance</th><th class="text-end">Montant</th><th class="text-end">Encaissé</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse($appels as $appel)
                        <tr>
                            <td><a href="{{ route('appelsfonds.show', $appel) }}">{{ $appel->numero ?? 'Brouillon #' . $appel->id }}</a></td>
                            <td>{{ $appel->copropriete?->nom }}</td>
                            <td>{{ $appel->libelle }}<div class="small text-muted">{{ \App\Models\AppelFonds::TYPES[$appel->type] }}</div></td>
                            <td class="small">{{ $appel->date_appel->format('d/m/Y') }} → {{ $appel->date_echeance->format('d/m/Y') }}</td>
                            <td class="text-end montant">{{ montant($appel->montant_total) }}</td>
                            <td class="text-end montant">{{ montant($appel->total_paye ?? 0) }}</td>
                            <td><x-statut :valeur="$appel->statut" /></td>
                            <td class="text-end">
                                @if($appel->statut->value === 'brouillon')
                                    @can('appelsfonds-emettre')
                                        <x-action :url="route('appelsfonds.emettre', $appel)" libelle="Émettre" icone="ti-send" classe="btn btn-sm btn-success" confirmer="Émettre l'appel et le répartir entre les lots ?" />
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucun appel de fonds." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($appels->hasPages())<div class="card-footer">{{ $appels->links() }}</div>@endif
    </div>
</x-page>
@endsection
