@extends('layouts.guest')

@section('title', $title)
@section('width', 'max-w-3xl')

@section('card')
    <h1 class="text-xl font-bold">{{ $title }}</h1>

    {{-- Pasted in under "Erscheinungsbild" and rendered from Markdown with any
         HTML stripped and unsafe links dropped (Branding::legalHtml()). --}}
    <div class="mt-4 sg-prose">
        {!! $html !!}
    </div>
@endsection
