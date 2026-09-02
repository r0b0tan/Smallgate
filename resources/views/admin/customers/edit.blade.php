@extends('layouts.app')

@section('title', 'Kunde bearbeiten')
@section('breadcrumb')
    <a href="{{ route('admin.customers.index') }}" class="hover:text-white">Kunden</a> /
    <a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-white">{{ $customer->name }}</a>
@endsection
@section('header', 'Kunde bearbeiten')

@section('content')
    <div class="max-w-2xl sg-card">
        <x-errors />

        <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
            @csrf
            @method('PATCH')
            @include('admin.customers._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.customers.show', $customer) }}" class="sg-btn-secondary">Abbrechen</a>
                <button type="submit" class="sg-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
@endsection
