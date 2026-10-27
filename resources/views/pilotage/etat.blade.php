@extends('layouts.backLayout.designadmin')

@php
    $montants = $donnees['montants'] ?? [];
    $cellule = fn ($valeur, $i) => in_array($i, $montants, true) ? montant($valeur, false) : (is_float($valeur) ? number_format($valeur, 0, ',', ' ') : $valeur);
@endphp

@section('content')
<x-page :titre="$donnees['titre']" module="Tableaux de bord" :retour="route('etats.index')">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format'])" />
        <button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="ti ti-printer"></i> Imprimer</button>
    </x-slot:actions>

    @unless($donnees['sansPeriode'] ?? false)
        <div class="card mb-4 no-print">
            <form method="GET" class="card-body row g-2 align-items-end">
                <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-3" />
                <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-3" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Actualiser</button></div>
            </form>
        </div>
    @endunless

    @if(!empty($donnees['graphique']) && !empty($donnees['lignes']))
        <div class="card mb-4">
            <div class="card-body" style="height: 320px"><canvas id="graphique" aria-label="{{ $donnees['titre'] }}" role="img"></canvas></div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>@foreach($donnees['colonnes'] as $i => $colonne)<th class="{{ $i > 0 ? 'text-end' : '' }}">{{ $colonne }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($donnees['lignes'] as $ligne)
                        <tr>@foreach($ligne as $i => $valeur)<td class="{{ $i > 0 ? 'text-end montant' : '' }}">{{ $cellule($valeur, $i) }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($donnees['colonnes']) }}"><x-vide message="Aucune donnée sur la période." /></td></tr>
                    @endforelse
                </tbody>
                @if(!empty($donnees['total']) && !empty($donnees['lignes']))
                    <tfoot><tr class="fw-semibold">@foreach($donnees['total'] as $i => $valeur)<td class="{{ $i > 0 ? 'text-end montant' : '' }}">{{ $cellule($valeur, $i) }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</x-page>
@endsection

@if(!empty($donnees['graphique']) && !empty($donnees['lignes']))
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                var g = @json($donnees['graphique']);
                var couleurs = ['#2a78d6', '#1baf7a', '#f2a93b', '#e5484d', '#7b61ff', '#18a2b8', '#8b8d98'];
                new Chart(document.getElementById('graphique'), {
                    type: g.type,
                    data: {
                        labels: g.libelles,
                        datasets: Object.keys(g.series).map(function (nom, i) {
                            return { label: nom, data: g.series[nom], backgroundColor: g.type === 'doughnut' ? couleurs : couleurs[i % couleurs.length], borderRadius: 4 };
                        })
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ' : ' + Number(c.raw).toLocaleString('fr-FR'); } } } },
                        scales: g.type === 'doughnut' ? {} : { y: { ticks: { callback: function (v) { return Number(v).toLocaleString('fr-FR'); } } } }
                    }
                });
            })();
        </script>
    @endpush
@endif
