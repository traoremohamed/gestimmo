@extends('layouts.backLayout.designadmin')

@php
    $totalEntrees = $mouvements->sum('entree');
    $totalSorties = $mouvements->sum('sortie');
@endphp

@section('content')
<x-page titre="Journal de caisse" module="Caisse & finances">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        <button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="ti ti-printer"></i> Imprimer</button>
    </x-slot:actions>

    <div class="card mb-4 no-print">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-3" />
            <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-3" />
            <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Afficher</button></div>
        </form>
    </div>

    <div class="row">
        <x-stat libelle="Entrées" :valeur="montant($totalEntrees)" icone="ti-arrow-down-left" col="col-md-4" />
        <x-stat libelle="Sorties" :valeur="montant($totalSorties)" icone="ti-arrow-up-right" col="col-md-4" />
        <x-stat libelle="Solde de la période" :valeur="montant($totalEntrees - $totalSorties)" icone="ti-scale" col="col-md-4" />
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Mouvements du {{ $du->format('d/m/Y') }} au {{ $au->format('d/m/Y') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Pièce</th><th>Libellé</th><th>Mode</th><th class="text-end">Entrée</th><th class="text-end">Sortie</th></tr></thead>
                        <tbody>
                            @forelse($mouvements as $m)
                                <tr>
                                    <td>{{ $m['date']->format('d/m/Y') }}</td>
                                    <td><a href="{{ $m['url'] }}">{{ $m['piece'] }}</a></td>
                                    <td>{{ $m['libelle'] }}</td>
                                    <td>{{ $m['mode'] }}</td>
                                    <td class="text-end montant">{{ $m['entree'] ? montant($m['entree'], false) : '' }}</td>
                                    <td class="text-end montant">{{ $m['sortie'] ? montant($m['sortie'], false) : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-vide message="Aucun mouvement sur la période." /></td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Totaux</td><td class="text-end montant">{{ montant($totalEntrees, false) }}</td><td class="text-end montant">{{ montant($totalSorties, false) }}</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Par mode de paiement</div>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Mode</th><th class="text-end">Entrées</th><th class="text-end">Sorties</th></tr></thead>
                    <tbody>
                        @foreach($parMode as $ligne)
                            <tr><td>{{ $ligne['libelle'] }}</td><td class="text-end montant">{{ montant($ligne['entrees'], false) }}</td><td class="text-end montant">{{ montant($ligne['sorties'], false) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-page>
@endsection
