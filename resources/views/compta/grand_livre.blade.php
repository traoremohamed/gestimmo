@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="'Grand livre — ' . $compte->numero . ' ' . $compte->libelle" module="Comptabilité" :retour="route('comptabilite.index')">
    <x-slot:actions><x-export /><button onclick="window.print()" class="btn btn-sm btn-outline-primary"><i class="ti ti-printer"></i> Imprimer</button></x-slot:actions>

    <div class="card mb-4 no-print">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-3 mb-0" />
            <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-3 mb-0" />
            <div class="col-auto"><button class="btn btn-outline-primary">Actualiser</button></div>
        </form>
    </div>

    @php($solde = $report)
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>N°</th><th>Pièce</th><th>Libellé</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde</th></tr></thead>
                <tbody>
                    <tr class="table-light"><td colspan="6">Report au {{ $du->format('d/m/Y') }}</td><td class="text-end montant">{{ montant($report, false) }}</td></tr>
                    @foreach($lignes as $l)
                        @php($solde += (float) $l->debit - (float) $l->credit)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($l->date_ecriture)->format('d/m/Y') }}</td><td class="small">{{ $l->numero }}</td><td class="small">{{ $l->piece }}</td><td>{{ $l->libelle }}</td>
                            <td class="text-end montant">{{ (float) $l->debit ? montant($l->debit, false) : '' }}</td>
                            <td class="text-end montant">{{ (float) $l->credit ? montant($l->credit, false) : '' }}</td>
                            <td class="text-end montant">{{ montant($solde, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="4">Totaux de la période</td><td class="text-end montant">{{ montant($lignes->sum('debit'), false) }}</td>
                    <td class="text-end montant">{{ montant($lignes->sum('credit'), false) }}</td><td class="text-end montant">{{ montant($solde, false) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</x-page>
@endsection
