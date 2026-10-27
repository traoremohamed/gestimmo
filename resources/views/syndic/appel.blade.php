@extends('layouts.backLayout.designadmin')

@php($brouillon = $appel->statut->value === 'brouillon')

@section('content')
<x-page :titre="($appel->numero ?? 'Appel (brouillon)') . ' — ' . $appel->libelle" module="Syndic" :retour="route('appelsfonds.index', ['copropriete_id' => $appel->copropriete_id])">
    <x-slot:badge><x-statut :valeur="$appel->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        @can('appelsfonds-emettre')
            @if($brouillon)
                <x-action :url="route('appelsfonds.emettre', $appel)" libelle="Émettre et répartir" icone="ti-send" classe="btn btn-sm btn-success" confirmer="Émettre l'appel ? Il sera réparti entre les lots selon les tantièmes." />
            @elseif($appel->statut->value === 'emis')
                <x-action :url="route('appelsfonds.annuler', $appel)" libelle="Annuler" icone="ti-ban" classe="btn btn-sm btn-outline-danger" confirmer="Annuler cet appel de fonds ?" />
            @endif
        @endcan
    </x-slot:actions>
    <x-attente-validation :objet="$appel" />

    <div class="row">
        <x-stat libelle="Montant appelé" :valeur="montant($appel->montant_total)" icone="ti-file-invoice" />
        <x-stat libelle="Encaissé" :valeur="montant($appel->lignes->sum('montant_paye'))" icone="ti-cash" />
        <x-stat libelle="Reste à recouvrer" :valeur="montant((float) $appel->lignes->where('statut.value', '!=', 'annule')->sum('montant') - (float) $appel->lignes->sum('montant_paye'))" icone="ti-alert-circle" />
        <x-stat libelle="Échéance" :valeur="$appel->date_echeance->format('d/m/Y')" icone="ti-calendar" :detail="$appel->cle?->libelle ?? 'Charges générales'" />
    </div>

    <div class="card">
        <div class="card-header"><a href="{{ route('coproprietes.show', $appel->copropriete) }}">{{ $appel->copropriete->nom }}</a> — répartition par lot</div>
        @if($brouillon)
            <div class="card-body"><x-vide message="La répartition sera calculée à l'émission de l'appel." icone="ti-chart-pie" /></div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Lot</th><th>Copropriétaire</th><th class="text-end">Tantièmes</th><th class="text-end">Quote-part</th><th class="text-end">Payé</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach($appel->lignes->sortBy('lot.numero', SORT_NATURAL) as $ligne)
                            <tr>
                                <td>{{ $ligne->lot->numero }} <span class="small text-muted">{{ $ligne->lot->designation }}</span></td>
                                <td>@if($ligne->coproprietaire)<a href="{{ route('tiers.show', $ligne->coproprietaire) }}">{{ $ligne->coproprietaire->nom_complet }}</a>@endif</td>
                                <td class="text-end">{{ (float) $ligne->tantiemes }}</td>
                                <td class="text-end montant">{{ montant($ligne->montant) }}</td>
                                <td class="text-end montant">{{ montant($ligne->montant_paye) }}</td>
                                <td><x-statut :valeur="$ligne->statut" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('appelsfonds.avis', [$appel, $ligne]) }}" target="_blank" class="btn btn-sm btn-light" title="Avis d'appel"><i class="ti ti-file-type-pdf"></i></a>
                                    @if(!$ligne->estSolde() && $ligne->coproprietaire_id)
                                        @can('encaissements-create')
                                            <a href="{{ route('encaissements.create', ['tiers_id' => $ligne->coproprietaire_id, 'activite' => 'syndic']) }}" class="btn btn-sm btn-success" title="Encaisser"><i class="ti ti-cash"></i></a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-page>
@endsection
