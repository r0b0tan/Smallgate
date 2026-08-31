@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'autocomplete' => null,
])

<div>
    <label for="{{ $name }}" class="sg-label">
        {{ $label }}@if ($required)<span class="text-accent"> *</span>@endif
    </label>

    <div class="mt-1">
        @if ($type === 'textarea')
            <textarea id="{{ $name }}" name="{{ $name }}" rows="4"
                      @if ($required) required @endif
                      {{ $attributes->merge(['class' => 'sg-field']) }}>{{ old($name, $value) }}</textarea>
        @else
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
                   value="{{ $type === 'password' ? '' : old($name, $value) }}"
                   @if ($required) required @endif
                   @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                   {{ $attributes->merge(['class' => 'sg-field']) }}>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-white/35">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
    @enderror
</div>
