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
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
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
