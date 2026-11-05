{{-- Bandeja de avisos del cliente (E7-01). El cuerpo llega escapado de RenderPlantilla::html. --}}
<div>
    <x-client-header />
    <x-tab-nav activa="" />

    <main class="flex flex-col gap-6 px-5 py-6">
        <a href="{{ route('cliente.inicio') }}" class="inline-flex min-h-touch items-center gap-1 self-start text-md font-semibold text-ink-2">
            <x-heroicon-o-chevron-left class="size-5" />
            Volver al inicio
        </a>

        <h1 class="font-display text-2xl">Avisos</h1>

        @forelse ($avisos as $aviso)
            <article wire:key="aviso-{{ $aviso->id }}" class="flex flex-col gap-2 rounded-lg border border-line bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="min-w-0 font-display text-lg">{{ $aviso->asunto }}</h2>
                    @if (in_array($aviso->id, $nuevos, true))
                        <span class="shrink-0 rounded-full bg-brand-soft px-3 py-1 text-sm font-bold text-brand-strong">Nuevo</span>
                    @endif
                </div>
                <span class="text-sm text-ink-2 tabular-nums">
                    {{ \App\Support\FormatoColombiano::fecha($aviso->created_at) }} · {{ \App\Support\FormatoColombiano::hora($aviso->created_at) }}
                </span>
                <div class="flex flex-col gap-2 text-md break-words [&_a]:font-semibold [&_a]:text-brand-strong [&_a]:underline">
                    {!! $render->html($aviso->cuerpo) !!}
                </div>
            </article>
        @empty
            <x-empty-state
                titulo="No tienes avisos"
                descripcion="Aquí te contamos cuando subas de nivel, uses un beneficio o tus puntos estén por vencer."
                icono="heroicon-o-bell"
            />
        @endforelse
    </main>
</div>
