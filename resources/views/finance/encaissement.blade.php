@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="'Reçu ' . $encaissement->numero" module="Caisse & finances" :retour="route('encaissements.index')">
    <x-slot:badge>
        @if($encaissement->estAnnule())<span class="badge bg-danger fs-6">Annulé</span>@else<span class="badge bg-success fs-6">Valide</span>@endif
    </x-slot:badge>
    <x-slot:actions>
        <a href="{{ route('encaissements.recu', $encaissement) }}" target="_blank" class="btn btn-sm btn-primary"><i class="ti ti-printer"></i> Imprimer le reçu</a>
        @if(!$encaissement->estAnnule())
            @can('encaissements-annuler')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnnulation"><i class="ti ti-ban"></i> Annuler</button>
            @endcan
        @endif
    </x-slot:actions>

    @if($encaissement->estAnnule())
        <div class="alert alert-danger">Annulé le {{ $encaissement->annule_le?->format('d/m/Y H:i') }} : {{ $encaissement->motif_annulation }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="stat-libelle">Montant encaissé</div>
                        <div class="fs-3 fw-bold montant">{{ montant($encaissement->montant) }}</div>
                    </div>
                    <dl class="fiche mb-0">
                        <dt>Payeur</dt><dd>@if($encaissement->tiers)<a href="{{ route('tiers.show', $encaissement->tiers) }}">{{ $encaissement->tiers->nom_complet }}</a>@endif</dd>
                        <dt>Date</dt><dd>{{ $encaissement->date_encaissement->format('d/m/Y') }}</dd>
                        <dt>Mode</dt><dd>{{ \App\Models\Referentiel::libelle('mode_paiement', $encaissement->mode_paiement) }} {{ $encaissement->reference }} {{ $encaissement->banque }}</dd>
                        <dt>Caissier</dt><dd>{{ $encaissement->caissier?->nom_complet }} — {{ $encaissement->created_at->format('d/m/Y H:i') }}</dd>
                        @if($encaissement->observation)<dt>Observation</dt><dd>{{ $encaissement->observation }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Affectation du règlement</div>
                <table class="table mb-0">
                    <thead><tr><th>Ligne réglée</th><th class="text-end">Montant affecté</th><th class="text-end">Reste dû</th></tr></thead>
                    <tbody>
                        @foreach($encaissement->affectations as $affectation)
                            <tr>
                                <td>{{ $affectation->affectable?->libelleAffectation() }}</td>
                                <td class="text-end montant">{{ montant($affectation->montant) }}</td>
                                <td class="text-end montant">{{ $affectation->affectable ? montant($affectation->affectable->resteAPayer()) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(!$encaissement->estAnnule())
        <div class="modal fade" id="modalAnnulation" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('encaissements.annuler', $encaissement) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Annuler le reçu {{ $encaissement->numero }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <p class="small">Les montants affectés seront retirés des lignes réglées. Le reçu reste conservé (numérotation continue) avec la mention « annulé ».</p>
                        <x-form.textarea name="motif" label="Motif de l'annulation" required col="col-12" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button><button class="btn btn-danger">Annuler l'encaissement</button></div>
                </form>
            </div>
        </div>
    @endif
</x-page>
@endsection
