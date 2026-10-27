{{-- Rendu d'un champ déclaré par un CrudController (ou toute vue utilisant la même définition) --}}
@php
    $type = $champ['type'] ?? 'text';
    // colonne réelle du modèle, sinon valeur par défaut du champ (champs calculés : rôles, cible, biens…)
    $valeur = $element && array_key_exists($champ['nom'], $element->getAttributes())
        ? $element->{$champ['nom']}
        : ($champ['defaut'] ?? null);
    $col = $champ['col'] ?? ($type === 'textarea' ? 'col-12' : 'col-md-6');
    $requis = !empty($champ['requis']);
@endphp

@switch($type)
    @case('select')
        <x-form.select :name="$champ['nom']" :label="$champ['label']" :options="isset($controleur) ? $controleur->options($champ) : ($champ['options'] ?? [])"
            :value="$valeur" :required="$requis" :col="$col" :aide="$champ['aide'] ?? null" :multiple="$champ['multiple'] ?? false" :recherche="$champ['recherche'] ?? false" />
        @break
    @case('textarea')
        <x-form.textarea :name="$champ['nom']" :label="$champ['label']" :value="$valeur" :required="$requis" :col="$col" :aide="$champ['aide'] ?? null" :rows="$champ['rows'] ?? 3" />
        @break
    @case('checkbox')
        <x-form.checkbox :name="$champ['nom']" :label="$champ['label']" :value="(bool) $valeur" :col="$col" :aide="$champ['aide'] ?? null" />
        @break
    @case('montant')
        <x-form.input :name="$champ['nom']" :label="$champ['label']" type="number" step="0.01" min="0" :value="$valeur" :required="$requis" :col="$col" :aide="$champ['aide'] ?? null" class="text-end" />
        @break
    @case('datetime')
        <x-form.input :name="$champ['nom']" :label="$champ['label']" type="datetime-local" :value="$valeur" :required="$requis" :col="$col" :aide="$champ['aide'] ?? null" />
        @break
    @case('couleur')
        <x-form.input :name="$champ['nom']" :label="$champ['label']" type="color" :value="$valeur ?: '#01619b'" :required="$requis" :col="$col" class="form-control-color" />
        @break
    @case('image')
        <div class="{{ $col }} mb-3">
            <label class="form-label {{ $requis && !$valeur ? 'required' : '' }}" for="champ_{{ $champ['nom'] }}">{{ $champ['label'] }}</label>
            @if($valeur)<div class="mb-2"><img src="{{ asset('storage/' . $valeur) }}" alt="" style="max-height: 90px" class="rounded"></div>@endif
            <input type="file" name="{{ $champ['nom'] }}" id="champ_{{ $champ['nom'] }}" accept="image/*" class="form-control @error($champ['nom']) is-invalid @enderror">
            @error($champ['nom'])<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @break
    @case('titre')
        <div class="col-12"><h6 class="text-primary border-bottom pb-1 mt-2 mb-3">{{ $champ['label'] }}</h6></div>
        @break
    @default
        <x-form.input :name="$champ['nom']" :label="$champ['label']" :type="$type" :value="$valeur" :required="$requis" :col="$col" :aide="$champ['aide'] ?? null"
            step="{{ $type === 'number' ? 'any' : '' }}" />
@endswitch
