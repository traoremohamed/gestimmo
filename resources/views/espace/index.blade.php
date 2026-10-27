@extends('layouts.backLayout.designadmin')

@php($totalDu = $dettes->sum(fn ($d) => (float) $d->resteAPayer()))

@section('content')
<x-page :titre="'Mon espace — ' . $tiers->nom_complet" module="Espace client">
    <div class="row">
        <x-stat libelle="Reste à payer" :valeur="montant($totalDu)" icone="ti-wallet" col="col-md-4" />
        <x-stat libelle="Total réglé" :valeur="montant($encaissements->sum('montant'))" icone="ti-cash" col="col-md-4" />
        <x-stat libelle="Contrats" :valeur="$tiers->ventes->where('numero', '!=', null)->count() + $tiers->baux->where('numero', '!=', null)->count()" icone="ti-file-text" col="col-md-4" />
    </div>

    @if($dettes->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">Mes prochains paiements</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Objet</th><th>Échéance</th><th class="text-end">Reste à payer</th></tr></thead>
                    <tbody>
                        @foreach($dettes as $dette)
                            @php($date = $dette->date_echeance ?? $dette->appel?->date_echeance ?? $dette->date_arrivee ?? null)
                            <tr class="{{ $date && \Illuminate\Support\Carbon::parse($date)->isPast() ? 'table-warning' : '' }}">
                                <td>{{ $dette->libelleAffectation() }}</td><td>{{ date_fr($date) }}</td><td class="text-end montant">{{ montant($dette->resteAPayer()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row">
        @foreach($tiers->ventes->whereNotNull('numero') as $vente)
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">Achat — contrat {{ $vente->numero }} <x-statut :valeur="$vente->statut" /></div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">{{ $vente->biens->map->designation->implode(' ; ') }}</div>
                        @php($taux = $vente->tauxRecouvrement())
                        <div class="d-flex justify-content-between small"><span>Payé : {{ montant($vente->totalPaye()) }}</span><span>{{ $taux }} %</span></div>
                        <div class="progress mb-2" role="progressbar" aria-valuenow="{{ $taux }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ min(100, $taux) }}%"></div></div>
                        <div class="small">Prix : {{ montant($vente->montant_net) }} + frais {{ montant($vente->montant_frais) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
        @foreach($tiers->baux->whereNotNull('numero') as $bail)
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">Location — bail {{ $bail->numero }} <x-statut :valeur="$bail->statut" /></div>
                    <div class="card-body small">
                        {{ $bail->bien->designation }}<br>
                        Loyer : {{ montant((float) $bail->loyer_mensuel + (float) $bail->charges_mensuelles) }} / mois, payable le {{ $bail->jour_echeance }}
                    </div>
                </div>
            </div>
        @endforeach
        @foreach($tiers->lotsCopropriete as $lot)
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Copropriété — {{ $lot->copropriete->nom }}</div>
                    <div class="card-body small">Lot {{ $lot->numero }} {{ $lot->designation }} — {{ (float) $lot->tantiemes }} / {{ $lot->copropriete->total_tantiemes }} tantièmes</div>
                </div>
            </div>
        @endforeach
        @if($tiers->biensEnGestion->isNotEmpty())
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Mes biens en gestion</div>
                    <ul class="list-group list-group-flush small">
                        @foreach($tiers->biensEnGestion as $bien)
                            <li class="list-group-item d-flex justify-content-between">{{ $bien->designation }} <x-statut :valeur="$bien->statut" /></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Mes reçus</div>
                <ul class="list-group list-group-flush small">
                    @forelse($encaissements as $e)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $e->date_encaissement->format('d/m/Y') }} — {{ $e->numero }}</span>
                            <span><span class="montant me-2">{{ montant($e->montant) }}</span><a href="{{ route('moncompte.recu', $e) }}" target="_blank"><i class="ti ti-file-type-pdf"></i></a></span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun reçu.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        @if($quittances->isNotEmpty() || $reversements->isNotEmpty())
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">{{ $quittances->isNotEmpty() ? 'Mes quittances' : 'Mes reversements' }}</div>
                    <ul class="list-group list-group-flush small">
                        @foreach($quittances as $q)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $q->libelle }}</span><a href="{{ route('moncompte.quittance', $q) }}" target="_blank"><i class="ti ti-file-type-pdf"></i> {{ $q->quittance_numero }}</a></li>
                        @endforeach
                        @foreach($reversements as $r)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $r->numero }} — {{ $r->periode_fin->format('m/Y') }}</span><span class="montant">{{ montant($r->montant_net) }} <x-statut :valeur="$r->statut" /></span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-page>
@endsection
