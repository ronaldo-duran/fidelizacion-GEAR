@props(['nivel' => 'bronce', 'actual' => false, 'meta' => null])

@php
    $esDiamante = $nivel === 'diamante';
    $nombre = ucfirst($nivel);
    $sombra = $actual ? "box-shadow: 0 0 0 2px var(--color-tier-{$nivel}-ink);" : '';
@endphp

<div
    class="flex items-center justify-between rounded-lg px-4 py-3"
    style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink); {{ $sombra }}"
>
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
