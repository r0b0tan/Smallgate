@extends('layouts.app')

@section('title', 'Projekt bearbeiten')
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-white">Projekte</a> /
    <a href="{{ route('admin.projects.show', $project) }}" class="hover:text-white">{{ $project->name }}</a>
@endsection
@section('header', 'Projekt bearbeiten')

@section('content')
    <div class="max-w-2xl sg-card">
        <x-errors />

        <form method="POST" action="{{ route('admin.projects.update', $project) }}">
            @csrf
            @method('PATCH')
            @include('admin.projects._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.projects.show', $project) }}" class="sg-btn-secondary">Abbrechen</a>
                <button type="submit" class="sg-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
@endsection
