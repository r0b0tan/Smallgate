{{-- Laravel's mail frame, with the name and copyright holder from the
     administration's "Erscheinungsbild" instead of APP_NAME. --}}
@php($branding = \App\Models\Branding::current())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $branding->displayName() }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $branding->copyrightHolder() }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
