@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Reversements aux propriétaires" module="Gestion locative">
    <x-slot:actions>
        @can('reversements-create')
            <a href="{{ route('reversements.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Préparer un reversement</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>N°</th><th>Propriétaire</th><th>Période</th><th class="text-end">Encaissé</th><th class="text-end">Commission</th><th class="text-end">Déductions</th><th class="text-end">Net à reverser</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse($reversements as $r)
                        <tr>
                            <td><a href="{{ route('reversements.show', $r) }}">{{ $r->numero }}</a></td>
                            <td>{{ $r->proprietaire?->nom_complet }}</td>
                            <td class="small">{{ $r->periode_debut->format('d/m/Y') }} → {{ $r->periode_fin->format('d/m/Y') }}</td>
                            <td class="text-end montant">{{ montant($r->montant_encaisse) }}</td>
                            <td class="text-end montant">{{ montant($r->montant_commission) }}</td>
                            <td class="text-end montant">{{ montant($r->montant_deductions) }}</td>
                            <td class="text-end montant fw-semibold">{{ montant($r->montant_net) }}</td>
                            <td><x-statut :valeur="$r->statut" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucun reversement." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reversements->hasPages())<div class="card-footer">{{ $reversements->links() }}</div>@endif
    </div>
</x-page>
@endsection
