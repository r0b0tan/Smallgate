@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'autocomplete' => null,
    'suffix' => null,
])

@php
    $displayValue = $type === 'password' ? '' : old($name, $value);

    // A suffixed field shows and takes only the part in front of the fixed
    // ending, whether the value comes from the model or from old input.
    if ($suffix !== null && is_string($displayValue) && str_ends_with($displayValue, $suffix)) {
        $displayValue = substr($displayValue, 0, -strlen($suffix));
    }
@endphp

<div>
    <label for="{{ $name }}" class="sg-label">
        {{ $label }}@if ($required)<span class="sg-required"> *</span>@endif
    </label>

    <div class="mt-1">
        @if ($type === 'textarea')
            <textarea id="{{ $name }}" name="{{ $name }}" rows="4"
                      @if ($required) required @endif
                      {{ $attributes->merge(['class' => 'sg-field']) }}>{{ $displayValue }}</textarea>
        @elseif ($suffix !== null)
            <div class="flex rounded-md bg-white ring-1 ring-inset ring-line
                        focus-within:ring-2 focus-within:ring-brand">
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
                       value="{{ $displayValue }}"
                       @if ($required) required @endif
                       @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                       {{ $attributes->merge([
                           'class' => 'w-full min-w-0 border-0 bg-transparent px-3.5 py-2.5 text-base text-ink
                                       placeholder:text-ink-muted/80 focus:outline-none focus:ring-0',
                       ]) }}>
                <span class="flex select-none items-center whitespace-nowrap pr-3.5 font-mono text-sm sg-muted">
                    {{ $suffix }}
                </span>
            </div>
        @else
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
                   value="{{ $displayValue }}"
                   @if ($required) required @endif
                   @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                   {{ $attributes->merge(['class' => 'sg-field']) }}>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 text-xs sg-faint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-sm sg-error">{{ $message }}</p>
    @enderror
</div>
