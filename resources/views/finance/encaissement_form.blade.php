@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Nouvel encaissement" module="Caisse & finances" :retour="route('encaissements.index')">
    <div class="card mb-4">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.select name="tiers_id" label="Payeur (client, locataire, copropriétaire…)" :options="$listeTiers" :value="$tiers?->id" required recherche col="col-md-6" />
            <x-form.select name="activite" label="Activité" :options="\App\Models\Encaissement::ACTIVITES" :value="$activite" placeholder="Toutes" col="col-md-3" />
            <div class="col-md-3 mb-3"><button class="btn btn-outline-primary w-100"><i class="ti ti-search"></i> Afficher les sommes dues</button></div>
        </form>
    </div>

    @if($tiers)
        <form method="POST" action="{{ route('encaissements.store') }}" id="formEncaissement">
            @csrf
            <input type="hidden" name="tiers_id" value="{{ $tiers->id }}">
            <div class="row">
                <div class="col-xl-8">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <span>Sommes dues par {{ $tiers->nom_complet }}</span>
                            <span class="small text-muted">Cochez les lignes, ajustez les montants pour un paiement partiel</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th style="width: 32px"></th><th>Libellé</th><th>Échéance</th><th class="text-end">Reste dû</th><th style="width: 180px" class="text-end">Montant réglé</th></tr></thead>
                                <tbody>
                                    @forelse($dettes as $dette)
                                        @php
                                            $cle = $dette->getMorphClass() . ':' . $dette->getKey();
                                            $date = $dette->date_echeance ?? $dette->appel?->date_echeance ?? $dette->date_arrivee ?? null;
                                            $reste = $dette->resteAPayer();
                                        @endphp
                                        <tr class="{{ $date && \Illuminate\Support\Carbon::parse($date)->isPast() ? 'table-warning' : '' }}">
                                            <td><input type="checkbox" class="form-check-input ligne-cocher" data-reste="{{ $reste }}" aria-label="Sélectionner"></td>
                                            <td>{{ $dette->libelleAffectation() }}</td>
                                            <td>{{ date_fr($date) }}</td>
                                            <td class="text-end montant">{{ montant($reste) }}</td>
                                            <td><input type="number" step="0.01" min="0" max="{{ $reste }}" name="lignes[{{ $cle }}]" value="{{ old('lignes.' . $cle) }}"
                                                class="form-control form-control-sm text-end ligne-montant" aria-label="Montant réglé"></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5"><x-vide message="Aucune somme due par ce tiers." icone="ti-mood-happy" /></td></tr>
                                    @endforelse
                                </tbody>
                                @if($dettes->isNotEmpty())
                                    <tfoot>
                                        <tr><td colspan="3" class="text-end">Total dû</td><td class="text-end montant fw-semibold">{{ montant($dettes->sum(fn ($d) => (float) $d->resteAPayer())) }}</td>
                                            <td class="text-end fw-semibold montant" id="totalSaisi">0</td></tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card mb-4">
                        <div class="card-header">Règlement</div>
                        <div class="card-body row">
                            <x-form.input name="date_encaissement" label="Date" type="date" :value="today()" required col="col-md-6" max="{{ today()->toDateString() }}" />
                            <x-form.select name="mode_paiement" label="Mode" :options="$modes" value="ESPECES" required col="col-md-6" />
                            <x-form.input name="reference" label="Référence (chèque, transaction…)" col="col-md-6" />
                            <x-form.input name="banque" label="Banque / opérateur" col="col-md-6" />
                            <x-form.input name="montant_global" label="Ou montant global (réparti automatiquement)" type="number" step="0.01" min="0" col="col-12"
                                aide="Utilisé seulement si aucune ligne n'est renseignée : les dettes les plus anciennes sont soldées en premier." />
                            <x-form.textarea name="observation" label="Observation" rows="2" />
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-success w-100" @disabled($dettes->isEmpty())><i class="ti ti-cash"></i> Enregistrer l'encaissement</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif
</x-page>
@endsection

@push('scripts')
<script>
    $(function () {
        function total() {
            var t = 0;
            $('.ligne-montant').each(function () { t += parseFloat($(this).val() || 0); });
            $('#totalSaisi').text(t.toLocaleString('fr-FR'));
        }
        $('.ligne-cocher').on('change', function () {
            var champ = $(this).closest('tr').find('.ligne-montant');
            champ.val(this.checked ? $(this).data('reste') : '');
            total();
        });
        $('.ligne-montant').on('input', total);
        total();
    });
</script>
@endpush
