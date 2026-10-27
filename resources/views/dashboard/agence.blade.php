@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="'Bonjour ' . auth()->user()->prenom_users" :module="$agenceCourante?->raison_sociale">
    <x-slot:actions>
        @can('encaissements-create')<a href="{{ route('encaissements.create') }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>@endcan
        @can('tiers-create')<a href="{{ route('tiers.create') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-user-plus"></i> Nouveau tiers</a>@endcan
        @can('rendezvous-create')<a href="{{ route('rendezvous.create') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-calendar-plus"></i> Rendez-vous</a>@endcan
    </x-slot:actions>

    <div class="row">
        @foreach($tuiles as [$libelle, $valeur, $icone, $lien])
            <x-stat :libelle="$libelle" :valeur="$valeur" :icone="$icone" :lien="$lien" />
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card h-100">
                <div class="card-header">Encaissements des 12 derniers mois</div>
                <div class="card-body" style="height: 300px">
                    @if(collect($graphique['series'])->flatten()->sum() > 0)
                        <canvas id="graphiqueEncaissements" role="img" aria-label="Encaissements mensuels par activité"></canvas>
                    @else
                        <x-vide message="Aucun encaissement sur la période." icone="ti-chart-bar" />
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Rendez-vous du jour @can('rendezvous-index')<a href="{{ route('rendezvous.index', ['periode' => 'jour']) }}" class="small">Tous</a>@endcan</div>
                <ul class="list-group list-group-flush">
                    @forelse($rendezVous as $rdv)
                        <li class="list-group-item">
                            <span class="badge bg-primary me-1">{{ $rdv->debut->format('H:i') }}</span>
                            <a href="{{ route('rendezvous.edit', $rdv) }}">{{ $rdv->interlocuteur }}</a>
                            <div class="small text-muted">{{ \App\Models\Referentiel::libelle('objet_rdv', $rdv->objet) }} {{ $rdv->bien ? '— ' . $rdv->bien->reference : '' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-vide message="Aucun rendez-vous aujourd'hui." icone="ti-calendar" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        @if($echeances->isNotEmpty())
            <div class="col-xl-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">Échéances de vente en retard <a href="{{ route('echeances.index') }}" class="small">Toutes</a></div>
                    <ul class="list-group list-group-flush small">
                        @foreach($echeances as $e)
                            <li class="list-group-item d-flex justify-content-between">
                                <span><a href="{{ route('ventes.show', $e->vente) }}">{{ $e->vente->acquereur->nom_complet }}</a><div class="text-muted">{{ $e->date_echeance->format('d/m/Y') }} — {{ $e->libelle }}</div></span>
                                <span class="montant text-danger">{{ montant($e->resteAPayer()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @if($loyers->isNotEmpty())
            <div class="col-xl-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">Loyers impayés <a href="{{ route('loyers.index', ['filtre' => 'impayes']) }}" class="small">Tous</a></div>
                    <ul class="list-group list-group-flush small">
                        @foreach($loyers as $l)
                            <li class="list-group-item d-flex justify-content-between">
                                <span><a href="{{ route('baux.show', $l->bail) }}">{{ $l->bail->locataire?->nom_complet }}</a><div class="text-muted">{{ $l->libelle }}</div></span>
                                <span class="montant text-danger">{{ montant($l->resteAPayer()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Réclamations ouvertes @can('reclamations-index')<a href="{{ route('reclamations.index') }}" class="small">Toutes</a>@endcan</div>
                <ul class="list-group list-group-flush small">
                    @forelse($reclamations as $r)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><a href="{{ route('reclamations.edit', $r) }}">{{ $r->objet }}</a><div class="text-muted">{{ $r->tiers?->nom_complet }} — {{ $r->created_at->format('d/m/Y') }}</div></span>
                            <x-statut :valeur="$r->statut" />
                        </li>
                    @empty
                        <li class="list-group-item"><x-vide message="Aucune réclamation en attente." icone="ti-mood-happy" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-page>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var element = document.getElementById('graphiqueEncaissements');
            if (!element) { return; }
            var g = @json($graphique);
            var couleurs = ['#2a78d6', '#1baf7a', '#f2a93b', '#e5484d', '#7b61ff'];
            new Chart(element, {
                type: 'bar',
                data: { labels: g.libelles, datasets: Object.keys(g.series).map(function (nom, i) { return { label: nom, data: g.series[nom], backgroundColor: couleurs[i % couleurs.length], borderRadius: 4 }; }) },
                options: {
                    maintainAspectRatio: false,
                    scales: { x: { stacked: true }, y: { stacked: true, ticks: { callback: function (v) { return Number(v).toLocaleString('fr-FR'); } } } },
                    plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ' : ' + Number(c.raw).toLocaleString('fr-FR'); } } } }
                }
            });
        })();
    </script>
@endpush
