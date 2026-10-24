@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'col' => 'col-md-6',
    'required' => false, 'placeholder' => null, 'help' => null, 'empty' => '— Select —', 'rows' => 3,
])
@php
    $dotted = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($dotted, $value instanceof \Carbon\CarbonInterface ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d') : $value);
    $id = 'f_'.str_replace(['.', '[', ']'], '_', $name);
@endphp
<div class="{{ $col }}">
    @if($type === 'checkbox')
        <div class="form-check form-switch mt-md-4">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked($current)>
            <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        </div>
    @else
        @if($label)<label for="{{ $id }}" class="form-label small fw-semibold mb-1">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>@endif
        @if($type === 'select')
            <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'form-select'.($errors->has($dotted) ? ' is-invalid' : '')]) }} @required($required)>
                @if($empty !== false)<option value="">{{ $empty }}</option>@endif
                @foreach($options as $key => $text)
                    <option value="{{ $key }}" @selected((string) $current === (string) $key)>{{ $text }}</option>
                @endforeach
            </select>
        @elseif($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'form-control'.($errors->has($dotted) ? ' is-invalid' : '')]) }} @required($required)>{{ $current }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $current }}" placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'form-control'.($errors->has($dotted) ? ' is-invalid' : '')]) }} @required($required)>
        @endif
        @error($dotted)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
