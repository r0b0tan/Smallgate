@extends('layouts.app')

@section('title', 'Vorschau anlegen')
@section('breadcrumb')
    <a href="{{ route('admin.projects.show', $project) }}" class="hover:text-white">{{ $project->name }}</a>
@endsection
@section('header', 'Vorschau anlegen')

@section('content')
    <div class="max-w-2xl sg-card">
        <x-errors />

        <form method="POST" action="{{ route('admin.projects.previews.store', $project) }}">
            @csrf
            @include('admin.previews._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.projects.show', $project) }}" class="sg-btn-secondary">Abbrechen</a>
                <button type="submit" class="sg-btn-primary">Vorschau anlegen</button>
            </div>
        </form>
    </div>
@endsection
