@extends('layouts.backLayout.designadmin')

@php($i = $element)

@section('content')
<x-page :titre="$i->numero . ' — ' . $i->objet" module="Interventions" :retour="route('interventions.index')">
    <x-slot:badge><x-statut :valeur="$i->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        @can('interventions-edit')
            @unless(in_array($i->statut->value, ['terminee', 'annulee']))
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalTerminer"><i class="ti ti-check"></i> Clôturer</button>
                <a href="{{ route('interventions.edit', $i) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
            @endunless
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body">
            <dl class="row fiche mb-0">
                <div class="col-md-4"><dt>Lieu</dt><dd>@if($i->bien)<a href="{{ route('biens.show', $i->bien) }}">{{ $i->bien->designation }}</a>@else{{ $i->copropriete?->nom ?? '—' }}@endif</dd></div>
                <div class="col-md-4"><dt>Prestataire</dt><dd>{{ $i->fournisseur?->nom_complet ?? '—' }} <span class="small text-muted">{{ $i->fournisseur?->telephone }}</span></dd></div>
                <div class="col-md-4"><dt>À la charge de</dt><dd>{{ \App\Models\Intervention::A_CHARGE_DE[$i->a_charge_de] }}</dd></div>
                <div class="col-md-4"><dt>Signalée / prévue / réalisée</dt><dd>{{ date_fr($i->date_signalement) }} / {{ date_fr($i->date_prevue) ?: '—' }} / {{ date_fr($i->date_realisation) ?: '—' }}</dd></div>
                <div class="col-md-4"><dt>Coût estimé / réel</dt><dd>{{ $i->cout_estime ? montant($i->cout_estime) : '—' }} / {{ $i->cout_reel ? montant($i->cout_reel) : '—' }}</dd></div>
                <div class="col-md-4"><dt>Dépense</dt><dd>@if($i->depense)<a href="{{ route('depenses.edit', $i->depense) }}">{{ $i->depense->numero }}</a>@else — @endif</dd></div>
                @if($i->reclamation)<div class="col-12"><dt>Réclamation</dt><dd><a href="{{ route('reclamations.edit', $i->reclamation) }}">{{ $i->reclamation->objet }}</a></dd></div>@endif
                @if($i->description)<div class="col-12"><dt>Description</dt><dd style="white-space: pre-line">{{ $i->description }}</dd></div>@endif
            </dl>
        </div>
    </div>

    <div class="modal fade" id="modalTerminer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('interventions.terminer', $i) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Clôturer l'intervention</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body row">
                    <x-form.input name="date_realisation" label="Réalisée le" type="date" :value="today()" required col="col-md-6" />
                    <x-form.input name="cout_reel" label="Coût réel" type="number" step="0.01" min="0" :value="$i->cout_estime" col="col-md-6" />
                    <x-form.select name="mode_paiement" label="Payé par" :options="\App\Models\Referentiel::options('mode_paiement')" col="col-12"
                        aide="Le coût est enregistré en dépense, déduite du prochain reversement s'il est à la charge du propriétaire." />
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Clôturer</button></div>
            </form>
        </div>
    </div>
    <x-ged :objet="$i" categorie="FACTURE" />
</x-page>
@endsection
