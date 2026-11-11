@props(['activo' => false, 'href' => null])

{{-- Ficha de filtro (44 px de alto). Con `href` es un enlace, como las categorías del catálogo (E8-04). --}}
@php
    $clases = 'flex h-11 shrink-0 items-center rounded-full px-4 font-sans text-sm '
        . ($activo ? 'bg-ink font-bold text-white' : 'border border-field font-semibold text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($activo) aria-current="page" @endif {{ $attributes->merge(['class' => $clases]) }}>
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $clases]) }}>
        {{ $slot }}
    </button>
@endif
