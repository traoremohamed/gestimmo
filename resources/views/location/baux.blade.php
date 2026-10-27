@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Baux" module="Gestion locative">
    <x-slot:actions>
        @can('baux-create')
            <a href="{{ route('baux.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Nouveau bail</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.input name="q" label="Recherche" :value="request('q')" placeholder="N° de bail, locataire, référence du bien…" col="col-md-4" />
                <x-form.select name="statut" label="Statut" :options="\App\Enums\StatutBail::options()" :value="request('statut')" placeholder="Tous" col="col-md-3" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Bail</th><th>Locataire</th><th>Bien</th><th class="text-end">Loyer + charges</th><th>Période</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse($baux as $bail)
                        <tr class="ligne-cliquable" data-href="{{ route('baux.show', $bail) }}">
                            <td><a href="{{ route('baux.show', $bail) }}" class="fw-medium">{{ $bail->numero ?? 'Brouillon #' . $bail->id }}</a>
                                <div class="small text-muted">{{ \App\Models\Bail::TYPES[$bail->type_bail] }}</div></td>
                            <td>{{ $bail->locataire?->nom_complet }}<div class="small text-muted">{{ $bail->locataire?->telephone }}</div></td>
                            <td class="small">{{ $bail->bien?->designation }}</td>
                            <td class="text-end montant">{{ montant((float) $bail->loyer_mensuel + (float) $bail->charges_mensuelles) }}</td>
                            <td class="small">{{ $bail->date_debut->format('d/m/Y') }} → {{ $bail->date_resiliation?->format('d/m/Y') ?? $bail->date_fin?->format('d/m/Y') }}</td>
                            <td><x-statut :valeur="$bail->statut" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-vide message="Aucun bail." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($baux->hasPages())<div class="card-footer">{{ $baux->links() }}</div>@endif
    </div>
</x-page>
@endsection
