@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Nouvel appel de fonds" module="Syndic" :retour="route('appelsfonds.index')">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('appelsfonds.store') }}" class="card">
                @csrf
                <div class="card-body row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required" for="copropriete_id">Copropriété</label>
                        <select name="copropriete_id" id="copropriete_id" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($coproprietes as $copro)
                                <option value="{{ $copro->id }}" data-cles='@json($copro->cles->pluck('libelle', 'id'))' @selected((int) old('copropriete_id', $coproprieteId) === $copro->id)>{{ $copro->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form.select name="type" label="Type" :options="\App\Models\AppelFonds::TYPES" value="travaux" required col="col-md-6" />
                    <x-form.input name="libelle" label="Libellé" required col="col-md-8" placeholder="Ex. : Réfection de la toiture" />
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="cle_repartition_id">Répartition</label>
                        <select name="cle_repartition_id" id="cle_repartition_id" class="form-select"><option value="">Charges générales</option></select>
                    </div>
                    <x-form.input name="date_appel" label="Date d'appel" type="date" :value="today()" required col="col-md-4" />
                    <x-form.input name="date_echeance" label="Date limite de paiement" type="date" :value="today()->addDays((int) parametre('syndic.delai_paiement_jours'))" required col="col-md-4" />
                    <x-form.input name="montant_total" label="Montant total à appeler" type="number" step="0.01" min="0" required col="col-md-4" />
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Créer le brouillon</button></div>
            </form>
        </div>
    </div>
</x-page>
@endsection

@push('scripts')
<script>
    $(function () {
        function cles() {
            var donnees = $('#copropriete_id :selected').data('cles') || {}, select = $('#cle_repartition_id');
            select.find('option:not(:first)').remove();
            $.each(donnees, function (id, libelle) { select.append($('<option>').val(id).text(libelle)); });
        }
        $('#copropriete_id').on('change', cles);
        cles();
    });
</script>
@endpush
