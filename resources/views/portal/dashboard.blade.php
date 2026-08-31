@extends('layouts.app')

@section('title', 'Meine Projekte')
@section('header', 'Meine Projekte')
@section('subheader', $customer?->name)

@section('content')
    @if ($projects->isEmpty())
        <x-empty message="Für Sie sind derzeit keine Projekte freigegeben." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($projects as $project)
                <a href="{{ route('portal.projects.show', $project) }}"
                   class="sg-card block transition-colors hover:bg-surface-raised">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold text-white">{{ $project->name }}</h2>
                        <span class="sg-badge {{ $project->status->badgeClasses() }}">
                            {{ $project->status->label() }}
                        </span>
                    </div>

                    @if ($project->description)
                        <p class="mt-3 line-clamp-3 text-sm text-white/50">{{ $project->description }}</p>
                    @endif

                    <p class="mt-4 text-xs text-white/35">
                        {{ trans_choice('{0}Keine Vorschau|{1}1 Vorschau|[2,*]:count Vorschauen',
                            $project->previews_count, ['count' => $project->previews_count]) }}
                        verfügbar
                    </p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
