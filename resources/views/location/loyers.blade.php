@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Loyers & quittances" module="Gestion locative">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        @can('loyers-generer')
            <x-action :url="route('loyers.generer')" libelle="Générer les loyers du mois" icone="ti-calendar-plus" classe="btn btn-sm btn-primary">
                <input type="hidden" name="mois" value="{{ $mois->format('Y-m') }}">
            </x-action>
            <x-action :url="route('loyers.penalites')" libelle="Appliquer les pénalités" icone="ti-alert-triangle" classe="btn btn-sm btn-outline-danger"
                confirmer="Appliquer les pénalités de retard aux loyers impayés au-delà du délai de grâce ?" />
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat :libelle="'Loyers appelés — ' . $mois->locale('fr')->translatedFormat('F Y')" :valeur="montant($appele)" icone="ti-file-invoice" col="col-md-4" />
        <x-stat libelle="Encaissés sur le mois" :valeur="montant($encaisse)" icone="ti-cash" col="col-md-4"
            :detail="$appele > 0 ? round($encaisse / $appele * 100, 1) . ' % de recouvrement' : null" />
        <x-stat libelle="Total des impayés échus" :valeur="montant($impayes)" icone="ti-alert-circle" col="col-md-4" :lien="route('loyers.index', ['filtre' => 'impayes'])" />
    </div>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.select name="filtre" label="Affichage" :options="['mois' => 'Échéances du mois', 'impayes' => 'Tous les impayés échus']" :value="$filtre" placeholder="Tout" col="col-md-3" />
                <x-form.input name="mois" label="Mois" type="month" :value="$mois->format('Y-m')" col="col-md-3" />
                <x-form.select name="nature" label="Nature" :options="\App\Enums\NatureLoyer::options()" :value="request('nature')" placeholder="Toutes" col="col-md-3" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Afficher</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Échéance</th><th>Locataire</th><th>Bien</th><th>Libellé</th><th class="text-end">Montant</th><th class="text-end">Reste</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse($loyers as $loyer)
                        <tr class="{{ $loyer->estEnRetard() ? 'table-danger' : '' }}">
                            <td>{{ $loyer->date_echeance->format('d/m/Y') }}</td>
                            <td><a href="{{ route('baux.show', $loyer->bail) }}">{{ $loyer->bail->locataire?->nom_complet }}</a></td>
                            <td class="small">{{ $loyer->bail->bien?->reference }}</td>
                            <td>{{ $loyer->libelle }}</td>
                            <td class="text-end montant">{{ montant($loyer->montant) }}</td>
                            <td class="text-end montant">{{ montant($loyer->resteAPayer()) }}</td>
                            <td><x-statut :valeur="$loyer->statut" /></td>
                            <td class="text-end text-nowrap">
                                @if($loyer->quittance_numero)
                                    <a href="{{ route('loyers.quittance', $loyer) }}" target="_blank" class="btn btn-sm btn-light" title="Quittance"><i class="ti ti-file-type-pdf"></i></a>
                                @elseif(!$loyer->estSolde())
                                    @can('encaissements-create')
                                        <a href="{{ route('encaissements.create', ['tiers_id' => $loyer->bail->locataire_id, 'activite' => 'location']) }}" class="btn btn-sm btn-success" title="Encaisser"><i class="ti ti-cash"></i></a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucun avis d'échéance pour cette sélection." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($loyers->hasPages())<div class="card-footer">{{ $loyers->links() }}</div>@endif
    </div>
</x-page>
@endsection
