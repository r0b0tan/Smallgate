@extends('layouts.app')

{{-- Reached only when the preview is not currently reachable -- an available
     one redirects straight to itself. This page exists so an older link from a
     mail lands somewhere that explains itself instead of on an error. --}}

@section('title', $preview->name)

@section('content')
    <div class="max-w-xl sg-card p-8 text-center sm:p-10">
        <span class="mx-auto flex size-12 items-center justify-center rounded-md bg-amber-50 text-amber-700">
            <x-icon name="clock" class="size-6" />
        </span>
        <p class="mt-5 sg-eyebrow">{{ $project->name }}</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $preview->name }}</h1>
        <p class="mt-3 sg-muted">
            Dieser Entwurf ist derzeit nicht erreichbar. Wir melden uns, sobald er wieder bereitsteht.
        </p>

        <a href="{{ route('portal.dashboard') }}" class="sg-btn-primary mt-8 min-h-12 text-base">
            <x-icon name="arrow-left" /> Zur Übersicht
        </a>
    </div>
@endsection
