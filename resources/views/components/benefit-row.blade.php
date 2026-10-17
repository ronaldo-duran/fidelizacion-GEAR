@props([
    'comercio' => '',
    'beneficio' => '',
    'meta' => null,
    'estado' => 'disponible',
    'nivel' => 'oro',
    'faltan' => null,
    'icono' => 'heroicon-o-gift',
])

@php $bloqueado = $estado === 'bloqueado'; @endphp

<div class="flex items-center gap-3 rounded-md border border-line bg-surface p-3">
    <span
        class="flex size-11 shrink-0 items-center justify-center rounded-sm {{ $bloqueado ? '' : 'bg-brand-soft text-brand' }}"
        @if ($bloqueado) style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink)" @endif
    >
        @svg($icono, 'size-6')
    </span>

    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
        <span class="text-sm font-semibold text-ink-2">{{ $comercio }}</span>
        <span class="text-md font-bold leading-tight">{{ $beneficio }}</span>

        @if ($bloqueado)
            <span
                class="mt-1 inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-sm font-semibold"
                style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink)"
            >
                <x-heroicon-s-lock-closed class="size-3" />
                Te faltan {{ $faltan }} pts
            </span>
        @elseif ($meta)
            <span class="text-sm text-ink-2">{{ $meta }}</span>
        @endif
    </div>

    @unless ($bloqueado)
        <x-heroicon-o-chevron-right class="size-5 shrink-0 text-ink-2" />
    @endunless
</div>
