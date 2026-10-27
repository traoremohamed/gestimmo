@extends('layouts.backLayout.designadmin')

@php
    $decisions = $demande->decisions->keyBy('ordre');
    $estDemandeur = (int) $demande->demandeur_id === (int) auth()->id();
    $lienObjet = match ($demande->objet_type) {
        'vente' => route('ventes.show', $demande->objet_id), 'bail' => route('baux.show', $demande->objet_id), 'decompte_sortie' => route('decomptes.show', $demande->objet_id),
        'reversement' => route('reversements.show', $demande->objet_id), 'appel_fonds' => route('appelsfonds.show', $demande->objet_id),
        'encaissement' => route('encaissements.show', $demande->objet_id), 'depense' => route('depenses.edit', $demande->objet_id),
        'periode_paie' => route('paie.show', $demande->objet_id), 'employe' => route('employes.show', $demande->objet_id), default => null,
    };
@endphp

@section('content')
<x-page :titre="\App\Services\Validation\Catalogue::libelle($demande->action)" module="Validations" :retour="route('validations.index')">
    <x-slot:badge><span class="badge text-bg-{{ $demande->couleurStatut() }} fs-6">{{ $demande->libelleStatut() }}</span></x-slot:badge>

    @if ($demande->statut === 'ECHEC')
        <div class="alert alert-danger"><strong>Exécution impossible :</strong> {{ $demande->erreur }}
            @can('validations-toutes')
                <form method="POST" action="{{ route('validations.executer', $demande) }}" class="d-inline ms-2">@csrf<button class="btn btn-sm btn-outline-danger">Relancer l'exécution</button></form>
            @endcan
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header">Ce qui sera fait</div>
                <div class="card-body">
                    <p class="fs-6 mb-3">{{ $demande->resume }}</p>
                    <dl class="fiche row mb-0">
                        <dt class="col-sm-4">Demande</dt><dd class="col-sm-8">{{ $demande->numero }} — le {{ $demande->demande_at->format('d/m/Y à H:i') }} par {{ $demande->demandeur?->nom_complet }}</dd>
                        <dt class="col-sm-4">Circuit</dt><dd class="col-sm-8">{{ $demande->circuit?->libelle }} (version {{ $demande->circuit_version }})</dd>
                        @if ($demande->reference)<dt class="col-sm-4">Référence</dt><dd class="col-sm-8">@if($lienObjet)<a href="{{ $lienObjet }}">{{ $demande->reference }}</a>@else{{ $demande->reference }}@endif</dd>@endif
                        @if ($demande->montant !== null)<dt class="col-sm-4">Montant</dt><dd class="col-sm-8 fw-semibold">{{ montant($demande->montant) }}</dd>@endif
                        @if ($demande->filtre)<dt class="col-sm-4">Portée</dt><dd class="col-sm-8">{{ $demande->filtre }}</dd>@endif
                        @if ($demande->motif)<dt class="col-sm-4">Motif</dt><dd class="col-sm-8" style="white-space: pre-line">{{ $demande->motif }}</dd>@endif
                        @if ($demande->action === 'PARAMETRES_MODIFICATION')
                            <dt class="col-sm-4">Valeurs demandées</dt>
                            <dd class="col-sm-8">@foreach((array) ($demande->donnees['valeurs'] ?? []) as $cle => $v)
                                <div class="small">{{ config("immobilier.parametres.$cle.0") ?? $cle }} : <strong>{{ is_bool($v) ? ($v ? 'Oui' : 'Non') : $v }}</strong>
                                    <span class="text-muted">(actuel : {{ var_export(parametre($cle), true) }})</span></div>@endforeach</dd>
                        @endif
                        @if ($demande->execute_at)<dt class="col-sm-4">Exécutée le</dt><dd class="col-sm-8">{{ $demande->execute_at->format('d/m/Y H:i') }}</dd>@endif
                    </dl>
                </div>
            </div>

            @if ($peutDecider)
                <form method="POST" action="{{ route('validations.decider', $demande) }}" class="card border-primary">
                    @csrf
                    <div class="card-header"><i class="ti ti-gavel"></i> Votre décision — étape « {{ $etapes->firstWhere('ordre', $demande->etape_courante)?->libelle }} »</div>
                    <div class="card-body"><x-form.textarea name="commentaire" label="Commentaire (obligatoire en cas de rejet)" rows="2" /></div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <button name="decision" value="REJETE" class="btn btn-outline-danger"><i class="ti ti-x"></i> Rejeter</button>
                        <button name="decision" value="APPROUVE" class="btn btn-success"><i class="ti ti-check"></i> Approuver</button>
                    </div>
                </form>
            @elseif ($demande->statut === 'EN_ATTENTE' && $estDemandeur)
                <form method="POST" action="{{ route('validations.annuler', $demande) }}" class="card"><div class="card-body d-flex gap-2">@csrf
                    <input type="text" name="motif" class="form-control" placeholder="Motif du retrait" required aria-label="Motif du retrait">
                    <button class="btn btn-outline-secondary text-nowrap">Retirer ma demande</button></div></form>
            @endif
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">Étapes</div>
                <ul class="list-group list-group-flush">
                    @foreach ($etapes as $e)
                        @php($dec = $decisions[$e->ordre] ?? null)
                        @php($courante = $demande->etape_courante === $e->ordre)
                        <li class="list-group-item d-flex gap-3 {{ $courante ? 'bg-primary-subtle' : '' }}">
                            <span class="badge rounded-pill align-self-start {{ $dec ? ($dec->decision === 'APPROUVE' ? 'bg-success' : 'bg-danger') : ($courante ? 'bg-primary' : 'bg-secondary') }}">{{ $e->ordre }}</span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $e->libelle }}</div>
                                <div class="small text-muted">{{ $e->designation() }} {{ $e->delai_heures ? '· délai ' . $e->delai_heures . ' h' : '' }}</div>
                                @if ($dec)
                                    <div class="small mt-1"><strong>{{ $dec->decision === 'APPROUVE' ? 'Approuvé' : 'Rejeté' }}</strong> par {{ $dec->auteur?->nom_complet }} le {{ $dec->decide_at->format('d/m/Y H:i') }}
                                        @if($dec->commentaire)<div class="text-muted">« {{ $dec->commentaire }} »</div>@endif</div>
                                @elseif ($courante)
                                    <div class="small text-primary mt-1">En attente de décision
                                        @if($demande->echeance_etape_at)— avant le {{ $demande->echeance_etape_at->format('d/m/Y H:i') }}@endif</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-page>
@endsection
