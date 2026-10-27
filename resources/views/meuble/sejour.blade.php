@extends('layouts.backLayout.designadmin')

@php
    $transitions = ['reserve' => ['confirme' => 'Confirmer', 'en_cours' => 'Arrivée du client', 'annule' => 'Annuler'], 'confirme' => ['en_cours' => 'Arrivée du client', 'annule' => 'Annuler'], 'en_cours' => ['termine' => 'Départ du client']][$sejour->statut->value] ?? [];
@endphp

@section('content')
<x-page :titre="'Séjour ' . $sejour->numero" module="Résidences meublées" :retour="route('sejours.index')">
    <x-slot:badge><x-statut :valeur="$sejour->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        <a href="{{ route('sejours.facture', $sejour) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-file-type-pdf"></i> Facture</a>
        @if(!$sejour->estSolde() && $sejour->statut->value !== 'annule')
            @can('encaissements-create')
                <a href="{{ route('encaissements.create', ['tiers_id' => $sejour->tiers_id, 'activite' => 'meuble']) }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>
            @endcan
        @endif
        @can('sejours-edit')
            @foreach($transitions as $statut => $libelle)
                <x-action :url="route('sejours.statut', $sejour)" :libelle="$libelle" :classe="'btn btn-sm ' . ($statut === 'annule' ? 'btn-outline-danger' : 'btn-primary')"
                    :confirmer="$statut === 'annule' ? 'Annuler ce séjour ?' : null">
                    <input type="hidden" name="statut" value="{{ $statut }}">
                </x-action>
            @endforeach
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Nuits" :valeur="$sejour->nb_nuits" icone="ti-moon" :detail="$sejour->nb_personnes . ' personne(s)'" />
        <x-stat libelle="Montant" :valeur="montant($sejour->montant)" icone="ti-receipt" />
        <x-stat libelle="Payé" :valeur="montant($sejour->montant_paye)" icone="ti-cash" />
        <x-stat libelle="Reste" :valeur="montant($sejour->resteAPayer())" icone="ti-alert-circle" />
    </div>

    <div class="card">
        <div class="card-body">
            <dl class="row fiche mb-0">
                <div class="col-md-4"><dt>Client</dt><dd><a href="{{ route('tiers.show', $sejour->client) }}">{{ $sejour->client->nom_complet }}</a><div class="small text-muted">{{ $sejour->client->contact }}</div></dd></div>
                <div class="col-md-4"><dt>Bien</dt><dd><a href="{{ route('biens.show', $sejour->bien) }}">{{ $sejour->bien->designation }}</a></dd></div>
                <div class="col-md-4"><dt>Période</dt><dd>du {{ $sejour->date_arrivee->format('d/m/Y') }} au {{ $sejour->date_depart->format('d/m/Y') }}</dd></div>
                <div class="col-md-4"><dt>Tarif</dt><dd>{{ montant($sejour->prix_nuitee) }} × {{ $sejour->nb_nuits }} nuit(s)</dd></div>
                <div class="col-md-4"><dt>Remise / frais</dt><dd>{{ montant($sejour->remise) }} / {{ montant($sejour->frais_supplementaires) }}</dd></div>
                <div class="col-md-4"><dt>Source</dt><dd>{{ $sejour->source ?: '—' }}</dd></div>
                @if($sejour->observation)<div class="col-12"><dt>Observation</dt><dd>{{ $sejour->observation }}</dd></div>@endif
            </dl>
        </div>
    </div>
</x-page>
@endsection
