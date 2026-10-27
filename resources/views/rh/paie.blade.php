@extends('layouts.backLayout.designadmin')

@php use App\Models\Rh\PeriodePaie; @endphp

@section('content')
<x-page titre="Paie" module="Paie">
    <x-slot:actions>
        @can('paie-calculer')
            <form method="POST" action="{{ route('paie.ouvrir') }}" class="d-flex gap-2">
                @csrf
                <input type="month" name="mois" value="{{ today()->format('Y-m') }}" class="form-control form-control-sm" aria-label="Mois" required>
                <button class="btn btn-sm btn-primary text-nowrap"><i class="ti ti-calendar-plus"></i> Ouvrir la période</button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Masse salariale de l'année (coût employeur)" :valeur="montant($masse)" icone="ti-report-money" col="col-md-5" />
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Période</th><th class="text-end">Bulletins</th><th class="text-end">Brut</th><th class="text-end">Net à payer</th><th class="text-end">Coût employeur</th><th>Paiement</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse($periodes as $p)
                        <tr class="ligne-cliquable" data-href="{{ route('paie.show', $p) }}">
                            <td><a href="{{ route('paie.show', $p) }}" class="fw-medium">{{ $p->libelle }}</a></td>
                            <td class="text-end">{{ $p->bulletins_count }}</td>
                            <td class="text-end montant">{{ montant($p->total_brut) }}</td>
                            <td class="text-end montant">{{ montant($p->total_net) }}</td>
                            <td class="text-end montant">{{ montant($p->total_cout) }}</td>
                            <td>{{ $p->date_paiement?->format('d/m/Y') }}</td>
                            <td><span class="badge bg-{{ PeriodePaie::STATUTS[$p->statut][1] }}">{{ PeriodePaie::STATUTS[$p->statut][0] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-vide message="Ouvrez la première période de paie." icone="ti-report-money" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($periodes->hasPages())<div class="card-footer">{{ $periodes->links() }}</div>@endif
    </div>
</x-page>
@endsection
