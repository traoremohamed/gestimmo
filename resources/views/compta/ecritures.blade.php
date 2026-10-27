@extends('layouts.backLayout.designadmin')

@php use App\Models\Compta\Ecriture; @endphp

@section('content')
<x-page titre="Écritures & journaux" module="Comptabilité">
    <x-slot:actions>
        <x-export :url="route('comptabilite.export', ['du' => $du->toDateString(), 'au' => $au->toDateString()])" libelle="Exporter les lignes"
            :formats="['xlsx' => 'Excel', 'pdf' => 'PDF', 'fec' => 'Fichier cabinet (CSV, proche FEC)']" />
        @can('ecritures-generer')
            <form method="POST" action="{{ route('ecritures.generer') }}" class="d-flex gap-1" data-confirm="Générer les écritures manquantes des opérations de la période ?">
                @csrf
                <input type="hidden" name="du" value="{{ $du->toDateString() }}"><input type="hidden" name="au" value="{{ $au->toDateString() }}">
                <button class="btn btn-sm btn-outline-primary"><i class="ti ti-refresh"></i> Générer depuis les opérations</button>
            </form>
        @endcan
        @can('ecritures-create')
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEcriture"><i class="ti ti-plus"></i> Saisie manuelle</button>
        @endcan
    </x-slot:actions>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <x-form.input name="q" label="Recherche" :value="request('q')" placeholder="N°, libellé, pièce…" col="col-md-3" />
                <x-form.select name="journal_id" label="Journal" :options="$journaux" :value="request('journal_id')" placeholder="Tous" col="col-md-3" />
                <x-form.input name="du" label="Du" type="date" :value="$du" col="col-md-2" />
                <x-form.input name="au" label="Au" type="date" :value="$au" col="col-md-2" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i></button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>N°</th><th>Libellé / compte</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th></th></tr></thead>
                <tbody>
                    @forelse($ecritures as $e)
                        <tr class="table-light">
                            <td class="fw-medium">{{ $e->date_ecriture->format('d/m/Y') }}</td>
                            <td class="small">{{ $e->numero }}<div class="text-muted">{{ $e->journal->code }}</div></td>
                            <td class="fw-medium">{{ $e->libelle }} <span class="badge bg-light text-dark">{{ Ecriture::NATURES[$e->nature] ?? $e->nature }}</span></td>
                            <td class="text-end montant fw-medium">{{ montant($e->total, false) }}</td>
                            <td class="text-end montant fw-medium">{{ montant($e->total, false) }}</td>
                            <td class="text-end">
                                @can('ecritures-create')
                                    @if(in_array($e->nature, ['normale', 'manuelle'], true))
                                        <form method="POST" action="{{ route('ecritures.contrepasser', $e) }}" class="d-inline" data-confirm="Contre-passer cette écriture ?">
                                            @csrf <input type="hidden" name="motif" value="Contre-passation manuelle">
                                            <button class="btn btn-sm btn-light" title="Contre-passer"><i class="ti ti-arrow-back-up"></i></button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @foreach($e->lignes as $l)
                            <tr class="small">
                                <td></td><td></td>
                                <td class="ps-4"><a href="{{ route('comptabilite.grandlivre', $l->compte) }}">{{ $l->compte->numero }}</a> {{ $l->compte->libelle }}
                                    <span class="text-muted">— {{ $l->libelle }}{{ $l->tiers ? ' (' . $l->tiers->nom_complet . ')' : '' }}</span></td>
                                <td class="text-end montant">{{ (float) $l->debit ? montant($l->debit, false) : '' }}</td>
                                <td class="text-end montant">{{ (float) $l->credit ? montant($l->credit, false) : '' }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="6"><x-vide message="Aucune écriture sur la période : les écritures sont créées automatiquement à chaque encaissement, dépense, reversement ou paie." icone="ti-calculator" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ecritures->hasPages())<div class="card-footer">{{ $ecritures->links() }}</div>@endif
    </div>

    @can('ecritures-create')
        <div class="modal fade" id="modalEcriture" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <form method="POST" action="{{ route('ecritures.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Écriture manuelle (opérations diverses)</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <x-form.select name="journal" label="Journal" :options="\App\Models\Compta\Journal::orderBy('code')->get()->mapWithKeys(fn ($j) => [$j->code => $j->code . ' — ' . $j->libelle])" value="OD" required col="col-md-3" />
                            <x-form.input name="date_ecriture" label="Date" type="date" :value="today()" required col="col-md-3" />
                            <x-form.input name="libelle" label="Libellé" required col="col-md-4" />
                            <x-form.input name="piece" label="Pièce" col="col-md-2" />
                        </div>
                        <datalist id="listeComptes">@foreach($comptes as $c)<option value="{{ $c->numero }}">{{ $c->libelle }}</option>@endforeach</datalist>
                        <table class="table table-sm">
                            <thead><tr><th>Compte</th><th>Libellé de ligne</th><th style="width: 160px">Débit</th><th style="width: 160px">Crédit</th></tr></thead>
                            <tbody>
                                @foreach(range(0, 5) as $i)
                                    <tr>
                                        <td><input name="lignes[{{ $i }}][compte]" list="listeComptes" class="form-control form-control-sm" aria-label="Compte"></td>
                                        <td><input name="lignes[{{ $i }}][libelle]" class="form-control form-control-sm" aria-label="Libellé"></td>
                                        <td><input type="number" step="0.01" min="0" name="lignes[{{ $i }}][debit]" class="form-control form-control-sm text-end saisie-debit" aria-label="Débit"></td>
                                        <td><input type="number" step="0.01" min="0" name="lignes[{{ $i }}][credit]" class="form-control form-control-sm text-end saisie-credit" aria-label="Crédit"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><td colspan="2" class="text-end">Totaux <span id="ecart" class="badge"></span></td><td class="text-end montant" id="totalDebit">0</td><td class="text-end montant" id="totalCredit">0</td></tr></tfoot>
                        </table>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    @endcan
</x-page>
@endsection

@push('scripts')
<script>
    $(document).on('input', '.saisie-debit, .saisie-credit', function () {
        var d = 0, c = 0;
        $('.saisie-debit').each(function () { d += parseFloat(this.value || 0); });
        $('.saisie-credit').each(function () { c += parseFloat(this.value || 0); });
        $('#totalDebit').text(d.toLocaleString('fr-FR')); $('#totalCredit').text(c.toLocaleString('fr-FR'));
        var equilibre = Math.abs(d - c) < 0.005;
        $('#ecart').text(equilibre ? 'équilibrée' : 'écart ' + (d - c).toLocaleString('fr-FR')).toggleClass('bg-success', equilibre).toggleClass('bg-danger', !equilibre);
    });
</script>
@endpush
