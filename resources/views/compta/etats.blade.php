@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="États comptables" module="Comptabilité">
    <x-slot:actions>
        @if(in_array($vue, ['balance', 'etats'], true))
            <x-export :libelle="$vue === 'balance' ? 'Exporter la balance' : 'Exporter les états'" />
        @endif
        <x-export :url="route('comptabilite.export', ['du' => $du->toDateString(), 'au' => $au->toDateString()])" libelle="Lignes d'écritures"
            :formats="['xlsx' => 'Excel', 'pdf' => 'PDF', 'fec' => 'Fichier cabinet (CSV, proche FEC)']" />
        <button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="ti ti-printer"></i> Imprimer</button>
    </x-slot:actions>

    <ul class="nav nav-tabs mb-0 no-print">
        @foreach(['balance' => 'Balance', 'etats' => 'Bilan & compte de résultat', 'exercices' => 'Exercices'] as $code => $libelle)
            <li class="nav-item"><a class="nav-link @if($vue === $code) active @endif" href="{{ route('comptabilite.index', ['vue' => $code, 'du' => $du->toDateString(), 'au' => $au->toDateString()]) }}">{{ $libelle }}</a></li>
        @endforeach
    </ul>
    <div class="card rounded-top-0">
        @if($vue !== 'exercices')
            <form method="GET" class="card-body border-bottom row g-2 align-items-end no-print">
                <input type="hidden" name="vue" value="{{ $vue }}">
                <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-3 mb-0" />
                <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-3 mb-0" />
                @if($vue === 'balance')
                    <x-form.select name="classe" label="Classe" :options="config('comptabilite.classes')" :value="request('classe')" placeholder="Toutes" col="col-md-3 mb-0" />
                @endif
                <div class="col-auto"><button class="btn btn-outline-primary">Actualiser</button></div>
            </form>
        @endif

        @if($vue === 'balance')
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Compte</th><th>Intitulé</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde débiteur</th><th class="text-end">Solde créditeur</th></tr></thead>
                    <tbody>
                        @forelse($balance as $l)
                            <tr>
                                <td><a href="{{ route('comptabilite.grandlivre', [$l->id, 'du' => $du->toDateString(), 'au' => $au->toDateString()]) }}">{{ $l->numero }}</a></td>
                                <td>{{ $l->libelle }}</td>
                                <td class="text-end montant">{{ montant($l->debit, false) }}</td>
                                <td class="text-end montant">{{ montant($l->credit, false) }}</td>
                                <td class="text-end montant">{{ (float) $l->solde > 0 ? montant($l->solde, false) : '' }}</td>
                                <td class="text-end montant">{{ (float) $l->solde < 0 ? montant(-$l->solde, false) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-vide message="Aucun mouvement sur la période." /></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="2">Totaux</td>
                        <td class="text-end montant">{{ montant($balance->sum('debit'), false) }}</td><td class="text-end montant">{{ montant($balance->sum('credit'), false) }}</td>
                        <td class="text-end montant">{{ montant($balance->sum(fn ($l) => max(0, (float) $l->solde)), false) }}</td>
                        <td class="text-end montant">{{ montant($balance->sum(fn ($l) => max(0, -(float) $l->solde)), false) }}</td></tr></tfoot>
                </table>
            </div>
        @elseif($vue === 'etats')
            <div class="card-body">
                <div class="row">
                    <x-stat libelle="Produits" :valeur="montant($etats['total_produits'])" icone="ti-trending-up" col="col-md-4" />
                    <x-stat libelle="Charges" :valeur="montant($etats['total_charges'])" icone="ti-trending-down" col="col-md-4" />
                    <x-stat :libelle="$etats['resultat'] >= 0 ? 'Bénéfice de la période' : 'Perte de la période'" :valeur="montant($etats['resultat'])" icone="ti-scale" col="col-md-4" />
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="text-primary">Compte de résultat</h6>
                        <table class="table table-sm">
                            <thead><tr><th colspan="2">Charges</th></tr></thead>
                            <tbody>@foreach($etats['charges'] as $l)<tr><td>{{ $l->numero }} {{ $l->libelle }}</td><td class="text-end montant">{{ montant($l->solde, false) }}</td></tr>@endforeach</tbody>
                            <thead><tr><th colspan="2">Produits</th></tr></thead>
                            <tbody>@foreach($etats['produits'] as $l)<tr><td>{{ $l->numero }} {{ $l->libelle }}</td><td class="text-end montant">{{ montant(-$l->solde, false) }}</td></tr>@endforeach</tbody>
                            <tfoot><tr class="fw-semibold"><td>Résultat</td><td class="text-end montant">{{ montant($etats['resultat'], false) }}</td></tr></tfoot>
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-primary">Bilan au {{ $au->format('d/m/Y') }}</h6>
                        <table class="table table-sm">
                            <thead><tr><th colspan="2">Actif</th></tr></thead>
                            <tbody>@foreach($etats['actif'] as $l)<tr><td>{{ $l->numero }} {{ $l->libelle }}</td><td class="text-end montant">{{ montant($l->solde, false) }}</td></tr>@endforeach</tbody>
                            <tfoot><tr class="fw-semibold"><td>Total actif</td><td class="text-end montant">{{ montant($etats['total_actif'], false) }}</td></tr></tfoot>
                        </table>
                        <table class="table table-sm">
                            <thead><tr><th colspan="2">Passif</th></tr></thead>
                            <tbody>
                                @foreach($etats['passif'] as $l)<tr><td>{{ $l->numero }} {{ $l->libelle }}</td><td class="text-end montant">{{ montant(-$l->solde, false) }}</td></tr>@endforeach
                                <tr><td>Résultat non affecté</td><td class="text-end montant">{{ montant($etats['resultat_cumule'], false) }}</td></tr>
                            </tbody>
                            <tfoot><tr class="fw-semibold"><td>Total passif</td><td class="text-end montant">{{ montant($etats['total_passif'], false) }}</td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Exercice</th><th>Période</th><th class="text-end">Écritures</th><th class="text-end">Résultat</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @forelse($exercices as $ex)
                            <tr>
                                <td class="fw-medium">{{ $ex->code }}</td>
                                <td>{{ $ex->date_debut->format('d/m/Y') }} → {{ $ex->date_fin->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $ex->ecritures()->count() }}</td>
                                <td class="text-end montant">{{ $ex->resultat !== null ? montant($ex->resultat) : '' }}</td>
                                <td><span class="badge bg-{{ $ex->estOuvert() ? 'success' : 'secondary' }}">{{ $ex->estOuvert() ? 'Ouvert' : 'Clôturé le ' . $ex->cloture_le?->format('d/m/Y') }}</span></td>
                                <td class="text-end">
                                    @if($ex->estOuvert())
                                        @can('comptabilite-cloturer')
                                            <x-action :url="route('comptabilite.cloturer', $ex)" libelle="Clôturer" icone="ti-lock" classe="btn btn-sm btn-outline-danger"
                                                confirmer="Clôturer l'exercice ? Les comptes de charges et de produits seront soldés dans le résultat et plus aucune écriture ne pourra y être passée." />
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-vide message="L'exercice est créé automatiquement à la première écriture." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-page>
@endsection
