@props(['comercio' => '', 'titulo' => '', 'icono' => 'heroicon-o-gift', 'href' => null])

{{-- Fila de beneficio sin tarjeta (inicio). Con `href` lleva a su detalle (E8-04). --}}
<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'flex min-h-14 items-center gap-3 border-b border-line py-2']) }}
>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-sm bg-brand-soft text-brand">
        @svg($icono, 'size-5')
    </span>
    <span class="flex min-w-0 flex-1 flex-col">
        <span class="text-md font-bold leading-tight">{{ $titulo }}</span>
        <span class="text-sm text-ink-2">{{ $comercio }}</span>
    </span>
    @if ($href)
        <x-heroicon-o-chevron-right class="size-5 shrink-0 text-ink-2" />
    @endif
</{{ $href ? 'a' : 'div' }}>
