@extends('layouts.backLayout.designadmin')

@php use App\Models\Rh\Bulletin; use App\Models\Rh\PeriodePaie; @endphp

@section('content')
<x-page :titre="'Paie — ' . $periode->libelle" module="Paie" :retour="route('paie.index')">
    <x-slot:badge><span class="badge bg-{{ PeriodePaie::STATUTS[$periode->statut][1] }} fs-6">{{ PeriodePaie::STATUTS[$periode->statut][0] }}</span></x-slot:badge>
    <x-attente-validation :objet="$periode" />
    <x-slot:actions>
        @if($periode->estModifiable())
            @can('paie-calculer')
                <x-action :url="route('paie.calculer', $periode)" libelle="Calculer les bulletins" icone="ti-calculator" classe="btn btn-sm btn-primary" />
            @endcan
            @if($periode->statut === 'calculee')
                @can('paie-valider')
                    <x-action :url="route('paie.valider', $periode)" libelle="Valider la paie" icone="ti-lock" classe="btn btn-sm btn-success"
                        confirmer="Valider la paie ? Les bulletins seront figés, les avances remboursées et l'écriture comptable passée." />
                @endcan
            @endif
        @endif
        @if(in_array($periode->statut, ['validee'], true))
            @can('paie-payer')
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPayer" data-bulletin=""><i class="ti ti-cash"></i> Payer tous les salaires</button>
            @endcan
        @endif
        @if($periode->bulletins->isNotEmpty())
            <a href="{{ route('paie.livre', $periode) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-book"></i> Livre de paie</a>
        @endif
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Brut" :valeur="montant($periode->total_brut)" icone="ti-cash" />
        <x-stat libelle="Net à payer" :valeur="montant($periode->total_net)" icone="ti-wallet" />
        <x-stat libelle="Coût employeur" :valeur="montant($periode->total_cout)" icone="ti-building-bank" />
        <x-stat libelle="Payé" :valeur="montant($periode->bulletins->sum('montant_paye'))" icone="ti-check" />
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Employé</th><th class="text-end">Brut</th><th class="text-end">Cotisations</th><th class="text-end">ITS</th><th class="text-end">Retenues</th><th class="text-end">Net</th><th class="text-end">Payé</th><th>Statut</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse($periode->bulletins->sortBy('employe.nom') as $b)
                        <tr>
                            <td><a href="{{ route('employes.show', $b->employe) }}">{{ $b->employe->nom_complet }}</a><div class="small text-muted">{{ $b->numero }}</div></td>
                            <td class="text-end montant">{{ montant($b->brut, false) }}</td>
                            <td class="text-end montant">{{ montant($b->cotisations_salariales, false) }}</td>
                            <td class="text-end montant">{{ montant($b->impots, false) }}</td>
                            <td class="text-end montant">{{ montant($b->retenues, false) }}</td>
                            <td class="text-end montant fw-semibold">{{ montant($b->net_a_payer, false) }}</td>
                            <td class="text-end montant">{{ montant($b->montant_paye, false) }}</td>
                            <td><span class="badge bg-{{ Bulletin::STATUTS[$b->statut][1] }}">{{ Bulletin::STATUTS[$b->statut][0] }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('paie.bulletin', [$periode, $b]) }}" target="_blank" class="btn btn-sm btn-light" title="Bulletin PDF"><i class="ti ti-file-type-pdf"></i></a>
                                @if(in_array($b->statut, ['valide', 'partiel'], true))
                                    @can('paie-payer')
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPayer" data-bulletin="{{ $b->id }}" data-reste="{{ $b->resteAPayer() }}" title="Payer"><i class="ti ti-cash"></i></button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-vide message="Saisissez les éléments variables puis calculez les bulletins." icone="ti-calculator" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($periode->estModifiable())
        <div class="card">
            <div class="card-header">Éléments variables de la période (primes, heures supplémentaires, retenues)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($variables as $v)
                            <tr><td>{{ $v->employe->nom_complet }}</td><td>{{ $rubriquesSaisie[$v->rubrique_code] ?? $v->rubrique_code }}</td><td>{{ $v->libelle }}</td>
                                <td class="text-end montant">{{ montant($v->montant) }}</td>
                                <td class="text-end"><x-action :url="route('paie.variables.destroy', [$periode, $v])" methode="DELETE" icone="ti-x" classe="btn btn-sm btn-light text-danger" /></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('paie-calculer')
                <form method="POST" action="{{ route('paie.variables.store', $periode) }}" class="card-body border-top row g-2 align-items-end">
                    @csrf
                    <x-form.select name="employe_id" label="Employé" :options="$employes->pluck('nom_complet', 'id')" required col="col-md-4 mb-0" />
                    <x-form.select name="rubrique_code" label="Rubrique" :options="$rubriquesSaisie" required col="col-md-3 mb-0" />
                    <x-form.input name="montant" label="Montant" type="number" step="0.01" required col="col-md-2 mb-0" />
                    <x-form.input name="libelle" label="Précision" col="col-md-2 mb-0" />
                    <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i></button></div>
                </form>
            @endcan
        </div>
    @endif

    <div class="modal fade" id="modalPayer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('paie.payer', $periode) }}" class="modal-content">
                @csrf
                <input type="hidden" name="bulletin_id" id="payer_bulletin">
                <div class="modal-header"><h5 class="modal-title">Paiement des salaires</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body row">
                    <x-form.input name="date_paiement" label="Date" type="date" :value="today()" required col="col-md-6" />
                    <x-form.select name="mode_paiement" label="Mode" :options="$modes" value="VIREMENT" required col="col-md-6" />
                    <x-form.input name="montant" label="Montant (paiement partiel)" type="number" step="0.01" min="0" col="col-md-6" aide="Vide = reste à payer" />
                    <x-form.input name="reference" label="Référence" col="col-md-6" />
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Enregistrer le paiement</button></div>
            </form>
        </div>
    </div>
</x-page>
@endsection

@push('scripts')
<script>
    document.getElementById('modalPayer')?.addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget.dataset;
        document.getElementById('payer_bulletin').value = b.bulletin || '';
        document.getElementById('champ_montant').value = b.reste || '';
        document.getElementById('champ_montant').closest('.mb-3').style.display = b.bulletin ? '' : 'none';
    });
</script>
@endpush
