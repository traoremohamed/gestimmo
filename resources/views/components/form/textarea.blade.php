@props(['name', 'label' => null, 'value' => null, 'required' => false, 'col' => 'col-12', 'rows' => 3, 'aide' => null])

@php($id = 'champ_' . str_replace(['[', ']', '.'], '_', $name))

<div class="{{ $col }} mb-3">
    @if($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($aide)<div class="form-text">{{ $aide }}</div>@endif
</div>
