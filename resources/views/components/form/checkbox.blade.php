@props(['name', 'label', 'value' => false, 'col' => 'col-md-6', 'aide' => null])

@php($id = 'champ_' . str_replace(['[', ']', '.'], '_', $name))

<div class="{{ $col }} mb-3 d-flex align-items-end">
    <div class="form-check form-switch">
        {{-- valeur envoyée même décochée --}}
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" id="{{ $id }}" value="1" @checked(old($name, $value))>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        @if($aide)<div class="form-text">{{ $aide }}</div>@endif
    </div>
</div>
