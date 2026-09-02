@extends('layouts.app')

{{-- Reached only when the preview is not currently reachable -- an available
     one redirects straight to itself. This page exists so an older link from a
     mail lands somewhere that explains itself instead of on an error. --}}

@section('title', $preview->name)
@section('breadcrumb')
    <a href="{{ route('portal.projects.show', $project) }}" class="hover:text-white">{{ $project->name }}</a>
@endsection
@section('header', $preview->name)
@section('subheader', 'Vorschau im Projekt ' . $project->name)

@section('content')
    <div class="max-w-2xl sg-card">
        <p class="text-sm sg-muted">
            Diese Vorschau ist derzeit nicht erreichbar. Wir melden uns, sobald sie wieder bereitsteht.
        </p>

        <a href="{{ route('portal.projects.show', $project) }}" class="sg-btn-secondary mt-6">
            Zurück zum Projekt
        </a>
    </div>
@endsection
