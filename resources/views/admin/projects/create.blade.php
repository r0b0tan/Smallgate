@extends('layouts.app')

@section('title', 'Projekt anlegen')
@section('breadcrumb')
    <a href="{{ route('admin.projects.index') }}" class="hover:text-white/60">Projekte</a>
@endsection
@section('header', 'Projekt anlegen')

@section('content')
    <div class="max-w-2xl sg-card">
        <x-errors />

        <form method="POST" action="{{ route('admin.projects.store') }}">
            @csrf
            @include('admin.projects._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.projects.index') }}" class="sg-btn-secondary">Abbrechen</a>
                <button type="submit" class="sg-btn-primary">Projekt anlegen</button>
            </div>
        </form>
    </div>
@endsection
