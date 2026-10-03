@extends('layouts.app')

@section('title', 'Erscheinungsbild')
@section('header', 'Erscheinungsbild')
@section('subheader', 'Name, Farben, Logos und Rechtstexte des Portals – für Kunden, Anmeldung und E-Mails.')

@use('App\Models\Branding')

@php
    $colors = [
        'brand_color' => [
            'label' => 'Hauptfarbe',
            'swatch' => 'bg-brand',
            'default' => Branding::DEFAULT_BRAND_COLOR,
            'hint' => 'Schaltflächen, aktive Navigation und das Anmeldefeld.',
        ],
        'accent_color' => [
            'label' => 'Akzentfarbe',
            'swatch' => 'bg-accent',
            'default' => Branding::DEFAULT_ACCENT_COLOR,
            'hint' => 'Kleine Hervorhebungen wie das Konto-Symbol.',
        ],
    ];

    $logos = [
        'light' => [
            'label' => 'Logo für helle Flächen',
            'hint' => 'Navigationsleiste, Kopfzeile und die Seiten rund um die Anmeldung.',
            'ground' => 'bg-rail',
            'empty' => 'Kein eigenes Logo – es erscheint das Standardzeichen.',
        ],
        'dark' => [
            'label' => 'Logo für dunkle Flächen',
            'hint' => 'Die farbige Fläche auf der Anmeldeseite.',
            'ground' => 'sg-login-panel',
            'empty' => $branding->hasLogo('light')
                ? 'Kein eigenes Logo – das Logo für helle Flächen erscheint dort auf einem hellen Feld.'
                : 'Kein eigenes Logo – es erscheint das Standardzeichen.',
        ],
    ];

    $legalPages = [
        'imprint' => [
            'label' => 'Impressum',
            'url_label' => 'Link zum Impressum',
            'text_label' => 'Text des Impressums',
        ],
        'privacy' => [
            'label' => 'Datenschutz',
            'url_label' => 'Link zur Datenschutzerklärung',
            'text_label' => 'Text der Datenschutzerklärung',
        ],
    ];
@endphp

@section('content')
    <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data"
          class="max-w-3xl space-y-6">
        @csrf
        @method('PATCH')

        <x-errors />

        <section class="sg-card">
            <h2 class="text-lg font-semibold">Name und Fußzeile</h2>

            <div class="mt-4 space-y-4">
                <x-field name="name" label="Schriftzug" :value="$branding->name"
                         :placeholder="config('app.name')" maxlength="60"
                         :hint="'Steht neben dem Logo, im Browser-Tab und in den E-Mails. Leer lassen für „'.config('app.name').'“.'" />

                <x-field name="footer_text" label="Fußzeile" :value="$branding->footer_text"
                         :placeholder="Branding::DEFAULT_FOOTER_TEXT" maxlength="120"
                         :hint="'Steht unten auf jeder Seite und in den E-Mails. Leer lassen für „'.Branding::DEFAULT_FOOTER_TEXT.'“.'" />
            </div>
        </section>

        <section class="sg-card">
            <h2 class="text-lg font-semibold">Farben</h2>
            <p class="mt-1 text-sm sg-muted">
                Als Hex-Wert, z. B. aus dem Styleguide. Hellere und dunklere Abstufungen werden daraus berechnet.
                Leer lassen für die Standardfarbe.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($colors as $name => $color)
                    <div>
                        <label for="{{ $name }}" class="sg-label">{{ $color['label'] }}</label>
                        <div class="mt-1 flex items-center gap-3">
                            {{-- The colour currently saved -- the page itself is drawn with it. --}}
                            <span class="size-10.5 shrink-0 rounded-md ring-1 ring-line {{ $color['swatch'] }}" aria-hidden="true"></span>
                            <input id="{{ $name }}" name="{{ $name }}" type="text"
                                   value="{{ old($name, $branding->{$name}) }}"
                                   placeholder="{{ $color['default'] }}"
                                   pattern="#[0-9a-fA-F]{6}" maxlength="7"
                                   autocomplete="off" spellcheck="false"
                                   class="sg-field font-mono">
                        </div>
                        <p class="mt-1 text-xs sg-faint">{{ $color['hint'] }}</p>
                        @error($name)
                            <p class="mt-1 text-sm sg-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="sg-card">
            <h2 class="text-lg font-semibold">Logos</h2>
            <p class="mt-1 text-sm sg-muted">
                PNG oder WebP mit transparentem Hintergrund, höchstens 1000 × 1000 Pixel und 512 KB. Am besten nur das Bildzeichen,
                quadratisch oder hochformatig – der Schriftzug steht daneben.
            </p>

            <div class="mt-4 divide-y divide-line">
                @foreach ($logos as $variant => $logo)
                    <div class="py-5 first:pt-0 last:pb-0">
                        <p class="sg-label">{{ $logo['label'] }}</p>
                        <p class="mt-0.5 text-xs sg-faint">{{ $logo['hint'] }}</p>

                        @if ($branding->hasLogo($variant))
                            <div class="mt-3 flex flex-wrap items-center gap-4">
                                <span class="flex h-20 w-32 items-center justify-center rounded-md p-3 ring-1 ring-line {{ $logo['ground'] }}">
                                    <img src="{{ $branding->logoUrl($variant) }}" alt="Aktuelles {{ $logo['label'] }}"
                                         class="max-h-full max-w-full object-contain">
                                </span>
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="hidden" name="remove_logo_{{ $variant }}" value="0">
                                    <input type="checkbox" name="remove_logo_{{ $variant }}" value="1"
                                           @checked(old("remove_logo_{$variant}"))
                                           class="size-4 rounded border-line accent-brand">
                                    Logo entfernen
                                </label>
                            </div>
                        @else
                            <p class="mt-3 text-sm sg-muted">{{ $logo['empty'] }}</p>
                        @endif

                        <label for="logo_{{ $variant }}" class="sr-only">{{ $logo['label'] }} hochladen</label>
                        <input id="logo_{{ $variant }}" name="logo_{{ $variant }}" type="file"
                               accept="image/png,image/webp"
                               class="mt-3 block w-full text-sm text-ink-muted
                                      file:mr-4 file:min-h-10 file:cursor-pointer file:rounded-md file:border-0
                                      file:bg-brand-soft file:px-4 file:text-sm file:font-semibold file:text-brand
                                      hover:file:bg-brand-tint">
                        @error("logo_{$variant}")
                            <p class="mt-1 text-sm sg-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="sg-card">
            <h2 class="text-lg font-semibold">Rechtliches</h2>
            <p class="mt-1 text-sm sg-muted">
                Wohin „Impressum“ und „Datenschutz“ unten auf jeder Seite und bei der Anmeldung führen. Ein Link auf die
                Seiten Ihrer Website genügt, wenn dort derselbe Anbieter steht – die Datenschutzerklärung sollte dann
                auch das Kundenportal abdecken. Für Betrieb mit echten Kunden nicht ausblenden.
            </p>

            <div class="mt-4 divide-y divide-line">
                @foreach ($legalPages as $page => $legal)
                    @php($mode = old("{$page}_mode", $branding->legalMode($page)->value))
                    <fieldset class="space-y-3 py-5 first:pt-0 last:pb-0">
                        <legend class="sg-label">{{ $legal['label'] }}</legend>

                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            @foreach (\App\Enums\LegalLinkMode::cases() as $option)
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="radio" name="{{ $page }}_mode" value="{{ $option->value }}"
                                           @checked($mode === $option->value)
                                           class="size-4 border-line accent-brand">
                                    {{ $option->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error("{$page}_mode")
                            <p class="text-sm sg-error">{{ $message }}</p>
                        @enderror

                        <x-field :name="$page.'_url'" :label="$legal['url_label']" type="url"
                                 :value="$branding->getAttribute($page.'_url')"
                                 placeholder="https://" maxlength="2048" autocomplete="url"
                                 hint="Nur für „Eigener Link“. Öffnet sich in einem neuen Tab." />

                        <x-field :name="$page.'_text'" :label="$legal['text_label']" type="textarea"
                                 :value="$branding->getAttribute($page.'_text')"
                                 maxlength="100000" class="min-h-48 text-sm"
                                 hint="Nur für „Eigener Text“. Einfach einfügen – jede Zeile bleibt eine Zeile. Überschriften mit „## “ am Zeilenanfang, Listen mit „- “. HTML wird entfernt." />
                    </fieldset>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="sg-btn-primary">Speichern</button>
        </div>
    </form>
@endsection
