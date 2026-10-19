@props(['nivel' => 'bronce'])

@php
    $orden = ['bronce' => 1, 'plata' => 2, 'oro' => 3, 'diamante' => 4];
    $llenos = $orden[$nivel] ?? 1;
    $esDiamante = $nivel === 'diamante';
@endphp

<span class="inline-flex items-center gap-1" aria-hidden="true">
    @for ($rombo = 1; $rombo <= 4; $rombo++)
        @if ($esDiamante)
            <span class="size-3 rotate-45" style="background: var(--color-tier-diamante-accent)"></span>
        @elseif ($rombo <= $llenos)
            <span class="size-3 rotate-45" style="background: var(--color-tier-{{ $nivel }}-ink)"></span>
        @else
            <span class="size-3 rotate-45" style="border: 1.5px solid var(--color-tier-{{ $nivel }}-ink); opacity: 0.45"></span>
        @endif
    @endfor
</span>
