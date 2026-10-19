@props([
    'variant' => 'primario',
    'type' => 'button',
    'disabled' => false,
    'ayuda' => null,
    'href' => null,
])

@php
    $clases = match ($variant) {
        'secundario' => 'h-touch rounded-md border border-ink font-sans text-md font-bold text-ink',
        'enlace' => 'min-h-touch font-sans text-md font-semibold text-brand',
        'destructivo' => 'min-h-touch font-sans text-md font-bold text-danger',
        default => 'h-btn rounded-md bg-brand font-sans text-lg font-bold text-white',
    };
    $deshabilitado = 'h-btn rounded-md bg-sunken font-sans text-lg font-bold text-ink-2';
@endphp

<div class="flex flex-col gap-2">
    @if ($href && ! $disabled)
        <a href="{{ $href }}" class="flex items-center justify-center {{ $clases }}">{{ $slot }}</a>
    @else
        <button
            type="{{ $type }}"
            @disabled($disabled)
            class="flex items-center justify-center {{ $disabled ? $deshabilitado : $clases }}"
        >{{ $slot }}</button>
    @endif

    @if ($disabled && $ayuda)
        <span class="text-sm text-ink-2">{{ $ayuda }}</span>
    @endif
</div>
