{{-- Fenêtre de téléversement ; $objet : modèle auquel rattacher les documents (ou null) --}}
@php($idModal = 'modalGed' . ($objet ? '-' . $objet->getMorphClass() . '-' . $objet->getKey() : ''))
<div class="modal fade" id="{{ $idModal }}" tabindex="-1" aria-labelledby="{{ $idModal }}-titre" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('ged.store') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            @if($objet)
                <input type="hidden" name="documentable_type" value="{{ $objet->getMorphClass() }}">
                <input type="hidden" name="documentable_id" value="{{ $objet->getKey() }}">
            @endif
            <div class="modal-header"><h5 class="modal-title" id="{{ $idModal }}-titre">Ajouter des documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
            <div class="modal-body row">
                <div class="col-12 mb-3">
                    <label class="form-label required" for="{{ $idModal }}-fichiers">Fichiers</label>
                    <input type="file" name="fichiers[]" id="{{ $idModal }}-fichiers" class="form-control" multiple required>
                    <div class="form-text">PDF, photos, Excel, Word… ({{ parametre('ged.taille_max_mo') }} Mo maximum par fichier, 20 fichiers à la fois). Sur téléphone : prise de photo directe.</div>
                </div>
                <x-form.input name="titre" label="Titre (si un seul fichier)" col="col-12" />
                <x-form.select name="categorie" label="Catégorie" :options="\App\Models\Referentiel::options('categorie_document')" :value="$categorie ?? 'AUTRE'" col="col-md-6" required placeholder="" />
                @can('ged-confidentiel')
                    <x-form.checkbox name="confidentiel" label="Confidentiel (accès restreint)" col="col-md-6" />
                @endcan
                <x-form.textarea name="description" label="Description" col="col-12" rows="2" />
            </div>
            <div class="modal-footer"><button class="btn btn-primary"><i class="ti ti-upload"></i> Enregistrer</button></div>
        </form>
    </div>
</div>
