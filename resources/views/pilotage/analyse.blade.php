@extends('layouts.backLayout.designadmin')

@php
    $format = fn ($i) => match ($i['format']) {
        'montant' => montant($i['valeur'] ?? 0),
        'pourcentage' => $i['valeur'] === null ? '—' : $i['valeur'] . ' %',
        default => $i['valeur'],
    };
@endphp

@section('content')
<x-page titre="Analyse & décision" module="Informatique décisionnelle">
    <x-slot:actions>
        @can('simulateur-index')<a href="{{ route('simulateur') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-adjustments"></i> Simulateur</a>@endcan
        <button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="ti ti-printer"></i> Imprimer</button>
    </x-slot:actions>

    <div class="card mb-4 no-print">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-3 mb-0" />
            <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-3 mb-0" />
            <div class="col-auto"><button class="btn btn-outline-primary">Analyser</button></div>
            <div class="col small text-muted text-end">Comparaison avec la période du {{ $precedente[0]->format('d/m/Y') }} au {{ $precedente[1]->format('d/m/Y') }}</div>
        </form>
    </div>

    <div class="row">
        @foreach($indicateurs as $i)
            @php
                $tendance = $i['variation'] === null ? null : ($i['variation'] * ($i['sens'] ?: 1) >= 0 ? 'success' : 'danger');
            @endphp
            <div class="col-sm-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body stat-tile">
                        <div class="stat-icon"><i class="ti {{ $i['icone'] }}"></i></div>
                        <div class="flex-grow-1">
                            <div class="stat-libelle">{{ $i['libelle'] }}</div>
                            <div class="stat-valeur">{{ $format($i) }}</div>
                            @if($i['variation'] !== null)
                                <div class="small text-{{ $tendance }}"><i class="ti ti-trending-{{ $i['variation'] >= 0 ? 'up' : 'down' }}"></i> {{ $i['variation'] > 0 ? '+' : '' }}{{ $i['variation'] }} %
                                    <span class="text-muted">vs {{ $format(['valeur' => $i['precedente']] + $i) }}</span></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4">
            <div class="card h-100">
                <div class="card-header">Recommandations</div>
                <ul class="list-group list-group-flush">
                    @foreach($recommandations as $r)
                        <li class="list-group-item d-flex gap-3">
                            <i class="ti ti-{{ ['danger' => 'alert-octagon', 'warning' => 'alert-triangle', 'info' => 'bulb', 'success' => 'circle-check'][$r['niveau']] }} text-{{ $r['niveau'] }} fs-4"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $r['titre'] }}</div>
                                <div class="small text-muted">{{ $r['detail'] }}</div>
                            </div>
                            @if($r['url'])<a href="{{ $r['url'] }}" class="btn btn-sm btn-outline-{{ $r['niveau'] === 'success' ? 'secondary' : $r['niveau'] }} align-self-center text-nowrap no-print">{{ $r['action'] }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-xl-5 mb-4">
            <div class="card h-100">
                <div class="card-header">Débiteurs à risque</div>
                <ul class="list-group list-group-flush small">
                    @forelse($risques as $r)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><a href="{{ route('recouvrements.show', $r['dossier']) }}">{{ $r['dossier']->tiers->nom_complet }}</a>
                                <div class="text-muted">{{ montant($r['dossier']->montant_du) }} — {{ $r['dossier']->joursRetard() }} j — niveau {{ $r['dossier']->niveau }}</div></span>
                            <span class="badge bg-{{ $r['niveau'] }} fs-6" title="Score de risque">{{ $r['score'] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun débiteur.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4">
            <div class="card h-100">
                <div class="card-header">Tendance sur 12 mois</div>
                <div class="card-body" style="height: 300px"><canvas id="tendance" role="img" aria-label="Tendance des encaissements, dépenses et ventes"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5 mb-4">
            <div class="card h-100">
                <div class="card-header">Prévision d'encaissement</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Mois</th><th class="text-end">Échéances dues</th><th class="text-end">Encaissement probable</th></tr></thead>
                        <tbody>
                            @foreach($prevision['lignes'] as $l)
                                <tr><td>{{ $l['mois'] }}</td><td class="text-end montant">{{ montant($l['total'], false) }}</td><td class="text-end montant fw-medium">{{ montant($l['probable'], false) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">Pondéré par le taux de recouvrement des 12 derniers mois ({{ $prevision['taux'] }} %). Arriérés à recouvrer en plus : {{ montant($prevision['arrieres']) }}.</div>
            </div>
        </div>
    </div>

    <div class="row">
        @if($programmes->isNotEmpty())
            <div class="col-xl-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Écoulement des programmes</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Programme</th><th class="text-end">Biens</th><th>Écoulement</th><th class="text-end">CA signé</th><th class="text-end">Stock</th></tr></thead>
                            <tbody>
                                @foreach($programmes as $p)
                                    <tr>
                                        <td>{{ $p['programme']->nom }}<div class="small text-muted">{{ $p['age_mois'] }} mois</div></td>
                                        <td class="text-end">{{ $p['programme']->biens_count }}</td>
                                        <td style="min-width: 120px"><div class="progress" style="height: 8px" role="progressbar" aria-valuenow="{{ $p['ecoulement'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Écoulement">
                                            <div class="progress-bar {{ $p['ecoulement'] < 30 ? 'bg-danger' : ($p['ecoulement'] < 60 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $p['ecoulement'] }}%"></div></div>
                                            <span class="small">{{ $p['ecoulement'] }} %</span></td>
                                        <td class="text-end montant">{{ montant($p['ca'], false) }}</td>
                                        <td class="text-end montant">{{ montant($p['stock'], false) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if($biens->isNotEmpty())
            <div class="col-xl-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Rentabilité des biens en location (12 mois)</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Bien</th><th class="text-end">Loyers encaissés</th><th class="text-end">Dépenses</th><th class="text-end">Net</th><th class="text-end">Impayés</th><th class="text-end">Rendement</th></tr></thead>
                            <tbody>
                                @foreach($biens as $b)
                                    <tr>
                                        <td><a href="{{ route('biens.show', $b['bien']) }}">{{ $b['bien']->reference }}</a> <x-statut :valeur="$b['statut']" /></td>
                                        <td class="text-end montant">{{ montant($b['encaisse'], false) }}</td>
                                        <td class="text-end montant">{{ montant($b['depenses'], false) }}</td>
                                        <td class="text-end montant fw-medium">{{ montant($b['net'], false) }}</td>
                                        <td class="text-end montant {{ $b['impayes'] > 0 ? 'text-danger' : '' }}">{{ montant($b['impayes'], false) }}</td>
                                        <td class="text-end">{{ $b['rendement'] !== null ? $b['rendement'] . ' %' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-page>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var g = @json($tendance), couleurs = ['#2a78d6', '#e5484d', '#1baf7a'];
            new Chart(document.getElementById('tendance'), {
                type: 'line',
                data: { labels: g.libelles, datasets: Object.keys(g.series).map(function (n, i) { return { label: n, data: g.series[n], borderColor: couleurs[i], backgroundColor: couleurs[i], tension: .3 }; }) },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ' : ' + Number(c.raw).toLocaleString('fr-FR'); } } } },
                    scales: { y: { ticks: { callback: function (v) { return Number(v).toLocaleString('fr-FR'); } } } } }
            });
        })();
    </script>
@endpush
