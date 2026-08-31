@extends('layouts.app')

@section('title', 'Kunde anlegen')
@section('breadcrumb')
    <a href="{{ route('admin.customers.index') }}" class="hover:text-white/60">Kunden</a>
@endsection
@section('header', 'Kunde anlegen')

@section('content')
    <div class="max-w-2xl sg-card">
        <x-errors />

        <form method="POST" action="{{ route('admin.customers.store') }}">
            @csrf
            @include('admin.customers._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.customers.index') }}" class="sg-btn-secondary">Abbrechen</a>
                <button type="submit" class="sg-btn-primary">Kunde anlegen</button>
            </div>
        </form>
    </div>
@endsection
