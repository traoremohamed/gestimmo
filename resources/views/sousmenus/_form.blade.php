@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="libelle">Libellé <span class="text-danger">*</span></label>
        <input type="text" name="libelle" id="libelle" class="form-control" required maxlength="255" value="{{ old('libelle', $sousmenus->libelle) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="menus">Module <span class="text-danger">*</span></label>
        <select name="menus" id="menus" class="form-select select2" required>
            <option value="">-- Sélectionner --</option>
            @foreach ($menus as $menu)
                <option value="{{ $menu->id_menu }}" @selected(old('menus', $sousmenus->menu_id_menu) == $menu->id_menu)>{{ $menu->menu }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="parent">Sous-menu parent</label>
        <select name="parent" id="parent" class="form-select select2" data-placeholder="Premier niveau du module">
            <option value="">— Premier niveau du module —</option>
            @foreach ($parents as $p)
                <option value="{{ $p['hid'] }}" data-menu="{{ $p['menu'] }}" @selected((int) old('parent', $sousmenus->parent_id) === (int) $p['hid'])>
                    {{ $p['module'] }} › {{ $p['libelle'] }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Un enfant hérite toujours du module de son parent. Aucune limite de profondeur.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sousmenu">Lien (URL / nom de route)</label>
        <div class="input-group">
            <span class="input-group-text">/</span>
            <input type="text" name="sousmenu" id="sousmenu" class="form-control" maxlength="255" placeholder="ex : parametre/services" value="{{ old('sousmenu', $sousmenus->sousmenu) }}">
        </div>
        <div class="form-text">Laisser vide pour un simple groupe qui contiendra d'autres sous-menus.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="icone">Icône (classe Tabler)</label>
        <div class="input-group">
            <span class="input-group-text"><i id="apercu-icone" class="{{ old('icone', $sousmenus->icone) ?: 'ti ti-point' }}"></i></span>
            <input type="text" name="icone" id="icone" class="form-control" placeholder="ti ti-stethoscope" value="{{ old('icone', $sousmenus->icone) }}">
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="code">Code technique</label>
        <input type="text" name="code" id="code" class="form-control" maxlength="60" value="{{ old('code', $sousmenus->code) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="priorite_sousmenu">Priorité (ordre)</label>
        <input type="number" min="0" name="priorite_sousmenu" id="priorite_sousmenu" class="form-control" value="{{ old('priorite_sousmenu', $sousmenus->priorite_sousmenu ?? 0) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <input type="text" name="description" id="description" class="form-control" maxlength="255" value="{{ old('description', $sousmenus->description) }}">
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="is_valide" id="is_valide" value="1" @checked(old('is_valide', $sousmenus->is_valide ?? true))>
            <label class="form-check-label" for="is_valide">Actif</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="flag_is_admin" id="flag_is_admin" value="1" @checked(old('flag_is_admin', $sousmenus->flag_is_admin))>
            <label class="form-check-label" for="flag_is_admin">Écran de la plateforme (super administrateur uniquement)</label>
        </div>
    </div>
</div>
<hr>
<div class="d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('sousmenus.index') }}">Retour</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Enregistrer</button>
</div>

@push('scripts')
<script>
    document.getElementById('icone').addEventListener('input', function () {
        document.getElementById('apercu-icone').className = this.value || 'ti ti-point';
    });
    // le module suit automatiquement le parent choisi
    $('#parent').on('change', function () {
        var menu = $(this).find(':selected').data('menu');
        if (menu) { $('#menus').val(menu).trigger('change'); }
    });
</script>
@endpush
