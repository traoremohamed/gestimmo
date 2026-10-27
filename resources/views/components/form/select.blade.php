@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'col' => 'col-md-6', 'placeholder' => '— Sélectionner —', 'multiple' => false, 'aide' => null, 'recherche' => false])

@php
    $id = 'champ_' . str_replace(['[', ']', '.'], '_', $name);
    $cle = str_replace(['[', ']'], ['.', ''], $name);
    $valeur = old($cle, $value instanceof \BackedEnum ? $value->value : $value);
    if ($multiple && is_string($valeur)) {
        $valeur = array_filter(explode(',', $valeur));
    }
    $selection = collect(is_iterable($valeur) ? $valeur : [$valeur])->map(fn ($v) => (string) ($v instanceof \BackedEnum ? $v->value : $v))->all();
@endphp

<div class="{{ $col }} mb-3">
    @if($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <select name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $id }}" @required($required) @if($multiple) multiple @endif
        {{ $attributes->class(['form-select', 'select2' => $recherche || $multiple, 'is-invalid' => $errors->has($cle)]) }}>
        @unless($multiple)
            <option value="">{{ $placeholder }}</option>
        @endunless
        @foreach($options as $cleOption => $libelle)
            <option value="{{ $cleOption }}" @selected(in_array((string) $cleOption, $selection, true))>{{ $libelle }}</option>
        @endforeach
    </select>
    @error($cle)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($aide)<div class="form-text">{{ $aide }}</div>@endif
</div>
