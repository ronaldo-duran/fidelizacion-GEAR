@props(['nivel' => 'bronce', 'actual' => false, 'meta' => null])

{{-- Tarjeta de nivel (pantalla Niveles). Lo que venga en el slot va debajo del encabezado. --}}
@php
    $esDiamante = $nivel === 'diamante';
    $nombre = ucfirst($nivel);
    $sombras = array_filter([
        $actual ? "0 0 0 2px var(--color-tier-{$nivel}-ink)" : null,
        $esDiamante ? 'inset 0 0 0 2px var(--color-tier-diamante-accent)' : null,
    ]);
    $sombra = $sombras === [] ? '' : 'box-shadow: ' . implode(', ', $sombras) . ';';
@endphp

<div
    {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-lg px-4 py-3']) }}
    style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink); {{ $sombra }}"
>
    <div class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-display {{ $esDiamante ? 'text-2xl' : 'text-xl' }} font-bold">{{ $nombre }}</span>
            @if ($meta)
                <span class="text-sm">{{ $meta }}</span>
            @endif
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if ($actual)
                <x-tier-badge :nivel="$nivel" :tu-nivel="true" />
            @endif
            <x-tier-diamonds :nivel="$nivel" />
        </div>
    </div>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif
</div>
