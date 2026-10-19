@props(['titulo' => '', 'descripcion' => null, 'icono' => 'heroicon-o-inbox'])

<div class="flex flex-col items-center gap-3 rounded-lg bg-sunken px-6 py-10 text-center">
    <span class="flex size-12 items-center justify-center rounded-full bg-surface text-ink-2">
        @svg($icono, 'size-6')
    </span>
    <span class="font-display text-xl font-bold">{{ $titulo }}</span>
    @if ($descripcion)
        <span class="text-md text-ink-2">{{ $descripcion }}</span>
    @endif
    @isset($cta)
        <div class="mt-1 w-full">{{ $cta }}</div>
    @endisset
</div>
