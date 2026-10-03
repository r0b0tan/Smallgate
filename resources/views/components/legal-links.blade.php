{{-- "Impressum" and "Datenschutz" as set under "Erscheinungsbild": the
     built-in page, the operator's own page (in a new tab, so the portal stays
     open), or nothing at all. --}}
@php($branding = \App\Models\Branding::current())

@foreach (['imprint' => 'Impressum', 'privacy' => 'Datenschutz'] as $page => $label)
    @if ($url = $branding->legalUrl($page))
        <a class="hover:text-ink hover:underline" href="{{ $url }}"
           @if ($branding->legalMode($page) === \App\Enums\LegalLinkMode::Link) target="_blank" rel="noopener noreferrer" @endif>{{ $label }}</a>
    @endif
@endforeach
