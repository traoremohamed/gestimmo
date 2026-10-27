@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'col' => 'col-md-6', 'aide' => null])

@php
    $id = 'champ_' . str_replace(['[', ']', '.'], '_', $name);
    $cle = str_replace(['[', ']'], ['.', ''], $name);
    $valeur = old($cle, $value instanceof \DateTimeInterface
        ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d')
        : ($value instanceof \BackedEnum ? $value->value : $value));
@endphp

<div class="{{ $col }} mb-3">
    @if($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $valeur }}" @required($required)
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($cle)]) }}>
    @error($cle)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($aide)<div class="form-text">{{ $aide }}</div>@endif
</div>
