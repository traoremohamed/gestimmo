@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Génération de biens en masse" module="Patrimoine" :retour="route('biens.index')">
    <form method="POST" action="{{ route('biens.generer') }}">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">Où générer ?</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cible" id="cible_lot" value="lotissement" @checked(old('cible', 'lotissement') === 'lotissement')>
                                <label class="form-check-label" for="cible_lot">Lots d'un lotissement</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cible" id="cible_imm" value="immeuble" @checked(old('cible') === 'immeuble')>
                                <label class="form-check-label" for="cible_imm">Appartements / locaux d'un immeuble</label>
                            </div>
                        </div>
                        <div class="row">
                            <x-form.select name="lotissement_id" label="Lotissement" :options="$lotissements" col="col-md-6 cible-lotissement" />
                            <x-form.input name="ilot" label="Îlot" col="col-md-6 cible-lotissement" />
                            <x-form.select name="immeuble_id" label="Immeuble" :options="$immeubles" col="col-md-6 cible-immeuble" />
                            <x-form.input name="par_etage" label="Nombre de portes par étage" type="number" min="0" col="col-md-6 cible-immeuble"
                                aide="Renseigne automatiquement l'étage (0 = rez-de-chaussée)." />
                            <x-form.select name="programme_id" label="Programme (facultatif)" :options="$programmes" col="col-md-6" />
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Numérotation et modèle</div>
                    <div class="card-body row">
                        <x-form.input name="prefixe" label="Libellé" value="Lot" required col="col-md-4" aide="Ex. « Lot » donne « Lot 1 », « Lot 2 »…" />
                        <x-form.input name="debut" label="Du numéro" type="number" value="1" required col="col-md-4" />
                        <x-form.input name="fin" label="Au numéro" type="number" value="10" required col="col-md-4" />
                        <x-form.select name="type_bien" label="Type de bien" :options="$types" value="TERRAIN" required col="col-md-4" />
                        <x-form.input name="superficie" label="Superficie (m²)" type="number" step="0.01" col="col-md-4" />
                        <x-form.input name="nb_pieces" label="Pièces" type="number" col="col-md-2" />
                        <x-form.input name="nb_chambres" label="Chambres" type="number" col="col-md-2" />
                        <x-form.checkbox name="a_vendre" label="À vendre" :value="true" col="col-md-3" />
                        <x-form.input name="prix_vente" label="Prix de vente unitaire" type="number" step="0.01" col="col-md-3" />
                        <x-form.checkbox name="a_louer" label="À louer" col="col-md-3" />
                        <x-form.input name="loyer_mensuel" label="Loyer mensuel" type="number" step="0.01" col="col-md-3" />
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary"><i class="ti ti-wand"></i> Générer</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="alert alert-info">
                    <i class="ti ti-info-circle"></i> Les numéros déjà présents dans le lotissement (même îlot) ou l'immeuble sont ignorés :
                    vous pouvez relancer une génération sans créer de doublons. Chaque bien reçoit une référence unique.
                </div>
            </div>
        </div>
    </form>
</x-page>
@endsection

@push('scripts')
<script>
    $(function () {
        function basculer() {
            var cible = $('input[name=cible]:checked').val();
            $('.cible-lotissement').toggle(cible === 'lotissement');
            $('.cible-immeuble').toggle(cible === 'immeuble');
        }
        $('input[name=cible]').on('change', basculer);
        basculer();
    });
</script>
@endpush
