@extends('layouts.portal')

{{-- Reached only when the preview is not currently reachable -- an available
     one redirects straight to itself. This page exists so an older link from a
     mail lands somewhere that explains itself instead of on an error. --}}

@section('title', $preview->name)

@section('content')
    <div class="mx-auto max-w-xl sg-card p-8 text-center sm:p-10">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
            <x-icon name="clock" class="size-7" />
        </span>
        <p class="mt-5 sg-eyebrow">{{ $project->name }}</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $preview->name }}</h1>
        <p class="mt-3 text-lg sg-muted">
            Dieser Entwurf ist derzeit nicht erreichbar. Wir melden uns, sobald er wieder bereitsteht.
        </p>

        <a href="{{ route('portal.dashboard') }}" class="sg-btn-cta mt-8">
            <x-icon name="arrow-left" /> Zur Übersicht
        </a>
    </div>
@endsection
