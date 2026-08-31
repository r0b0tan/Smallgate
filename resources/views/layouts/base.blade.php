<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The portal must never show up in a search index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Kundenportal') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full min-h-full antialiased">
    @yield('body')
</body>
</html>
