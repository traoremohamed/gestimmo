@extends('layouts.backLayout.designadmin')

@php($statut = $reversement->statut->value)

@section('content')
<x-page :titre="'Reversement ' . $reversement->numero" module="Gestion locative" :retour="route('reversements.index')">
    <x-slot:badge><x-statut :valeur="$reversement->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        <a href="{{ route('reversements.releve', $reversement) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-file-type-pdf"></i> Relevé de gérance</a>
        @can('reversements-edit')
            @if($statut === 'brouillon')
                <x-action :url="route('reversements.valider', $reversement)" libelle="Valider" icone="ti-check" classe="btn btn-sm btn-success" confirmer="Valider ce reversement ? Les loyers seront marqués comme reversés." />
                <x-action :url="route('reversements.destroy', $reversement)" methode="DELETE" libelle="Supprimer" icone="ti-trash" classe="btn btn-sm btn-outline-danger" confirmer="Supprimer ce brouillon ?" />
            @elseif($statut === 'valide')
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPaiement"><i class="ti ti-cash"></i> Enregistrer le paiement</button>
            @endif
        @endcan
    </x-slot:actions>
    <x-attente-validation :objet="$reversement" />

    <div class="row">
        <x-stat libelle="Loyers encaissés" :valeur="montant($reversement->montant_encaisse)" icone="ti-cash" />
        <x-stat libelle="Commission de gestion" :valeur="montant($reversement->montant_commission)" icone="ti-percentage" />
        <x-stat libelle="Dépenses déduites" :valeur="montant($reversement->montant_deductions)" icone="ti-tool" />
        <x-stat libelle="Net à reverser" :valeur="montant($reversement->montant_net)" icone="ti-wallet" />
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ $reversement->proprietaire->nom_complet }} — du {{ $reversement->periode_debut->format('d/m/Y') }} au {{ $reversement->periode_fin->format('d/m/Y') }}
            @if($reversement->paye_le)<span class="float-end small">Payé le {{ $reversement->paye_le->format('d/m/Y') }} ({{ \App\Models\Referentiel::libelle('mode_paiement', $reversement->mode_paiement) }} {{ $reversement->reference_paiement }})</span>@endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Libellé</th><th class="text-end">Encaissé</th><th class="text-end">Taux</th><th class="text-end">Commission</th></tr></thead>
                <tbody>
                    @foreach($reversement->lignes as $ligne)
                        <tr><td>{{ $ligne->libelle }}</td><td class="text-end montant">{{ montant($ligne->montant_encaisse) }}</td><td class="text-end">{{ $ligne->taux_commission }} %</td><td class="text-end montant">{{ montant($ligne->montant_commission) }}</td></tr>
                    @endforeach
                    @foreach($reversement->depenses as $depense)
                        <tr class="table-warning"><td>Dépense : {{ $depense->libelle }}</td><td></td><td></td><td class="text-end montant">- {{ montant($depense->montant) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($statut === 'valide')
        <div class="modal fade" id="modalPaiement" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('reversements.payer', $reversement) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Paiement de {{ montant($reversement->montant_net) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="mode_paiement" label="Mode de paiement" :options="$modes" required col="col-md-6" />
                        <x-form.input name="reference_paiement" label="Référence" col="col-md-6" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Enregistrer</button></div>
                </form>
            </div>
        </div>
    @endif
</x-page>
@endsection
