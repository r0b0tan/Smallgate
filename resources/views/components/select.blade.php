@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'hint' => null,
])

<div>
    <label for="{{ $name }}" class="sg-label">
        {{ $label }}@if ($required)<span class="text-accent"> *</span>@endif
    </label>

    <div class="mt-1">
        <select id="{{ $name }}" name="{{ $name }}"
                @if ($required) required @endif
                {{ $attributes->merge(['class' => 'sg-field']) }}>
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-white/35">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
    @enderror
</div>
