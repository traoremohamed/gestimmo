@extends('layouts.backLayout.designadmin')

@php($bienChoisi = $biens->firstWhere('id', (int) old('bien_id', $bienId)))

@section('content')
<x-page :titre="$bail ? 'Modifier le bail' : 'Nouveau bail'" module="Gestion locative" :retour="$bail ? route('baux.show', $bail) : route('baux.index')">
    <form method="POST" action="{{ $bail ? route('baux.update', $bail) : route('baux.store') }}">
        @csrf
        @if($bail) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">Parties et bien</div>
                    <div class="card-body row">
                        <div class="col-12 mb-3">
                            <label class="form-label required" for="bien_id">Bien loué</label>
                            <select name="bien_id" id="bien_id" class="form-select select2" required>
                                <option value="">— Sélectionner —</option>
                                @foreach($biens as $bien)
                                    <option value="{{ $bien->id }}" data-loyer="{{ $bien->loyer_mensuel }}" data-charges="{{ $bien->charges_mensuelles }}"
                                        @selected((int) old('bien_id', $bail?->bien_id ?? $bienId) === $bien->id) @disabled(!$bien->estDisponible() && $bien->id !== $bail?->bien_id)>
                                        {{ $bien->designation }} — {{ montant($bien->loyer_mensuel) }} {{ $bien->estDisponible() ? '' : '(' . $bien->statut->libelle() . ')' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <x-form.select name="locataire_id" label="Locataire" :options="$tiers" :value="$bail?->locataire_id" required recherche />
                        <x-form.select name="garant_id" label="Garant / caution solidaire" :options="$tiers" :value="$bail?->garant_id" recherche />
                        <x-form.select name="type_bail" label="Type de bail" :options="\App\Models\Bail::TYPES" :value="$bail?->type_bail ?? 'habitation'" required col="col-md-4" />
                        <x-form.input name="date_debut" label="Date d'effet" type="date" :value="$bail?->date_debut ?? today()" required col="col-md-4" />
                        <x-form.input name="duree_mois" label="Durée (mois)" type="number" min="1" :value="$bail?->duree_mois ?? parametre('location.duree_bail_mois')" required col="col-md-4" />
                        <x-form.checkbox name="tacite_reconduction" label="Tacite reconduction" :value="$bail?->tacite_reconduction ?? true" col="col-md-4" />
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Conditions financières</div>
                    <div class="card-body row">
                        <x-form.input name="loyer_mensuel" label="Loyer mensuel" type="number" step="0.01" min="0" :value="$bail?->loyer_mensuel ?? $bienChoisi?->loyer_mensuel" required col="col-md-4" />
                        <x-form.input name="charges_mensuelles" label="Charges mensuelles" type="number" step="0.01" min="0" :value="$bail?->charges_mensuelles ?? $bienChoisi?->charges_mensuelles" col="col-md-4" />
                        <x-form.select name="periodicite_mois" label="Périodicité" :options="[1 => 'Mensuelle', 3 => 'Trimestrielle', 6 => 'Semestrielle', 12 => 'Annuelle']" :value="$bail?->periodicite_mois ?? 1" required col="col-md-4" />
                        <x-form.input name="jour_echeance" label="Jour d'échéance" type="number" min="1" max="28" :value="$bail?->jour_echeance ?? parametre('location.jour_echeance')" required col="col-md-3" />
                        <x-form.input name="mois_caution" label="Caution (mois)" type="number" min="0" :value="$bail?->mois_caution ?? parametre('location.mois_caution')" required col="col-md-3" />
                        <x-form.input name="mois_avance" label="Avance (mois)" type="number" min="0" :value="$bail?->mois_avance ?? parametre('location.mois_avance')" required col="col-md-3" />
                        <x-form.input name="taux_penalite" label="Pénalité retard (%)" type="number" step="0.01" min="0" :value="$bail?->taux_penalite ?? parametre('location.taux_penalite_retard')" col="col-md-3" />
                        <x-form.input name="depot_garantie" label="Dépôt de garantie" type="number" step="0.01" min="0" :value="$bail?->depot_garantie" col="col-md-4" aide="Vide = loyer × mois de caution" />
                        <x-form.input name="frais_agence" label="Frais d'agence" type="number" step="0.01" min="0" :value="$bail?->frais_agence" col="col-md-4" aide="Vide = loyer × mois paramétrés" />
                        <x-form.input name="taux_revision" label="Révision annuelle (%)" type="number" step="0.01" min="0" :value="$bail?->taux_revision" col="col-md-2" />
                        <x-form.input name="date_prochaine_revision" label="Prochaine révision" type="date" :value="$bail?->date_prochaine_revision" col="col-md-2" />
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">Compteurs d'eau et d'électricité</div>
                    <div class="card-body row">
                        <x-form.input name="compteur_sodeci_numero" label="N° compteur SODECI" :value="$bail?->compteur_sodeci_numero" col="col-md-3" />
                        <x-form.input name="caution_sodeci" label="Caution SODECI perçue" type="number" step="0.01" min="0" :value="$bail?->caution_sodeci" col="col-md-3" aide="Restituée à la sortie si la dernière facture est réglée" />
                        <x-form.input name="compteur_cie_numero" label="N° compteur CIE" :value="$bail?->compteur_cie_numero" col="col-md-3" />
                        <x-form.input name="caution_cie" label="Caution CIE perçue" type="number" step="0.01" min="0" :value="$bail?->caution_cie" col="col-md-3" />
                        <x-form.checkbox name="compteur_cie_credit" label="Compteur CIE prépayé pris à crédit par le locataire" :value="(bool) $bail?->compteur_cie_credit" col="col-md-6"
                            aide="La CIE prélève une part du crédit à chaque rechargement : le montant remboursé par le locataire lui est restitué à la sortie, sur justificatifs." />
                        <x-form.input name="compteur_cie_montant_credit" label="Montant du crédit compteur" type="number" step="0.01" min="0" :value="$bail?->compteur_cie_montant_credit" col="col-md-3" />
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body row">
                        <x-form.textarea name="observation" label="Clauses particulières" :value="$bail?->observation" />
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer le brouillon</button></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="alert alert-info small">
                    <i class="ti ti-info-circle"></i> À l'<strong>activation</strong>, l'application génère automatiquement le dépôt de garantie, les frais d'agence,
                    les frais de contrat obligatoires et les loyers payés d'avance, tous exigibles à la date d'effet. Les loyers suivants sont générés chaque mois.
                </div>
            </div>
        </div>
    </form>
</x-page>
@endsection

@push('scripts')
<script>
    $('#bien_id').on('change', function () {
        var option = $(this).find(':selected');
        if (option.data('loyer')) { $('#champ_loyer_mensuel').val(option.data('loyer')); }
        if (option.data('charges') !== undefined) { $('#champ_charges_mensuelles').val(option.data('charges')); }
    });
</script>
@endpush
