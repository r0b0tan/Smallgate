@php($branding = \App\Models\Branding::current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The portal must never show up in a search index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Kundenportal') · {{ $branding->displayName() }}</title>
    {{-- The administrator's logo, if there is one: the light one for a light
         browser tab, the dark one for a dark tab, either one alone for both. --}}
    @if ($branding->hasAnyLogo())
        @foreach (['light', 'dark'] as $variant)
            @if ($branding->hasLogo($variant))
                <link rel="icon" href="{{ $branding->logoUrl($variant) }}" type="{{ $branding->getAttribute("logo_{$variant}_mime") }}"
                      @if ($branding->hasLogo('light') && $branding->hasLogo('dark')) media="(prefers-color-scheme: {{ $variant }})" @endif>
            @endif
        @endforeach
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- The administrator's colours, after app.css so they win. --}}
    @if ($stylesheet = $branding->stylesheetUrl())
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endif
</head>
<body class="h-full min-h-full antialiased">
    @yield('body')
</body>
</html>
