@props(['comercio' => '', 'titulo' => '', 'icono' => 'heroicon-o-gift'])

<div class="flex h-14 items-center gap-3 border-b border-line">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-sm bg-brand-soft text-brand">
        @svg($icono, 'size-5')
    </span>
    <div class="flex min-w-0 flex-col">
        <span class="text-md font-bold leading-tight">{{ $titulo }}</span>
        <span class="text-sm text-ink-2">{{ $comercio }}</span>
    </div>
</div>
