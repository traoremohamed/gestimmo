@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Échéances & impayés" module="Ventes">
    <div class="row">
        <x-stat libelle="Total des impayés échus" :valeur="montant($totalRetard)" icone="ti-alert-triangle" col="col-md-4" />
    </div>

    <ul class="nav nav-tabs mb-0">
        @foreach(['retard' => 'En retard', 'mois' => 'Ce mois-ci', '30j' => '30 prochains jours', 'toutes' => 'Toutes les échéances ouvertes'] as $code => $libelle)
            <li class="nav-item"><a class="nav-link @if($filtre === $code) active @endif" href="{{ route('echeances.index', ['filtre' => $code]) }}">{{ $libelle }}</a></li>
        @endforeach
    </ul>
    <div class="card rounded-top-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Échéance</th><th>Contrat</th><th>Acquéreur</th><th>Contact</th><th class="text-end">Montant</th><th class="text-end">Reste</th><th>Retard</th><th></th></tr></thead>
                <tbody>
                    @forelse($echeances as $echeance)
                        <tr>
                            <td>{{ $echeance->date_echeance->format('d/m/Y') }}<div class="small text-muted">{{ $echeance->libelle }}</div></td>
                            <td><a href="{{ route('ventes.show', $echeance->vente) }}">{{ $echeance->vente->numero }}</a></td>
                            <td><a href="{{ route('tiers.show', $echeance->vente->acquereur) }}">{{ $echeance->vente->acquereur->nom_complet }}</a></td>
                            <td class="small">{{ $echeance->vente->acquereur->contact }}</td>
                            <td class="text-end montant">{{ montant($echeance->montant) }}</td>
                            <td class="text-end montant fw-medium">{{ montant($echeance->resteAPayer()) }}</td>
                            <td>@if($echeance->estEnRetard())<span class="badge bg-danger">{{ (int) $echeance->date_echeance->diffInDays(today()) }} j</span>@endif</td>
                            <td class="text-end">
                                @can('encaissements-create')
                                    <a href="{{ route('encaissements.create', ['tiers_id' => $echeance->vente->acquereur_id, 'activite' => 'vente']) }}" class="btn btn-sm btn-success" title="Encaisser"><i class="ti ti-cash"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucune échéance dans cette sélection." icone="ti-mood-happy" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($echeances->hasPages())<div class="card-footer">{{ $echeances->links() }}</div>@endif
    </div>
</x-page>
@endsection
