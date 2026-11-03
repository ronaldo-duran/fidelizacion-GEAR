{{-- Misiones del cliente (E3-06): misiones vigentes, su estado y el envío del pantallazo. --}}
<div>
    <x-client-header />
    <x-tab-nav activa="" />

    <main class="flex flex-col gap-6 px-5 py-6">
        <a href="{{ route('cliente.inicio') }}" class="inline-flex min-h-touch items-center gap-1 self-start text-md font-semibold text-ink-2">
            <x-heroicon-o-chevron-left class="size-5" />
            Volver al inicio
        </a>

        <div class="flex flex-col gap-2">
            <h1 class="font-display text-2xl">Misiones</h1>
            <p class="text-md text-ink-2">Cumple misiones de los comercios del Club y suma puntos para tu nivel. El comercio revisa tu envío antes de abonarlos.</p>
        </div>

        @if ($enviada)
            <x-notice tipo="info">Enviamos tu misión a revisión. Te abonamos los puntos cuando el comercio la apruebe.</x-notice>
        @endif

        @forelse ($misiones as $item)
            @php $codigo = $item->codigo; @endphp

            <article wire:key="mision-{{ $codigo }}" class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-sm text-ink-2">{{ $item->comercio }}</span>
                        <h2 class="font-display text-xl">{{ $item->titulo }}</h2>
                    </div>
                    <span class="shrink-0 rounded-full bg-brand-soft px-3 py-1 text-sm font-bold text-brand-strong tabular-nums">
                        +{{ \App\Support\FormatoColombiano::puntos($item->puntos) }}
                    </span>
                </div>

                <p class="text-md text-ink-2">{{ $item->descripcion }}</p>

                @if ($item->maximo > 1)
                    <span class="text-sm text-ink-2 tabular-nums">Llevas {{ $item->veces }} de {{ $item->maximo }}</span>
                @endif

                @if ($item->estado === \App\Promociones\MisionParaCliente::EN_REVISION)
                    <div class="flex items-center gap-2 rounded-md bg-sunken p-3 text-sm text-ink">
                        <x-heroicon-o-clock class="size-5 shrink-0" />
                        <span><strong>En revisión.</strong> El comercio está revisando tu envío.</span>
                    </div>
                @elseif ($item->estado === \App\Promociones\MisionParaCliente::COMPLETADA)
                    <div class="flex items-center gap-2 rounded-md bg-sunken p-3 text-sm text-success-ink">
                        <x-heroicon-o-check-circle class="size-5 shrink-0" />
                        <span><strong>Completada.</strong> Ya recibiste los puntos de esta misión.</span>
                    </div>
                @else
                    @if ($item->motivoRechazo)
                        <x-notice>El comercio rechazó tu último envío: {{ $item->motivoRechazo }}</x-notice>
                    @endif

                    @if ($abierta === $codigo)
                        <form wire:submit="enviar" class="flex flex-col gap-4" novalidate>
                            @if ($item->requiereSoporte)
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-md font-semibold">Pantallazo</span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        wire:model="soporte"
                                        class="min-h-touch rounded-md border border-field bg-surface p-2 text-md text-ink file:mr-3 file:h-9 file:rounded-sm file:border-0 file:bg-sunken file:px-3 file:font-semibold file:text-ink"
                                    >
                                    <span class="text-sm text-ink-2">Foto o captura en JPG, PNG o WEBP de hasta 5 MB.</span>
                                </label>
                                <span wire:loading wire:target="soporte" class="text-sm text-ink-2">Subiendo el pantallazo…</span>
                            @endif

                            @error('soporte') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror

                            <div wire:loading.remove wire:target="soporte">
                                <x-button type="submit">Enviar a revisión</x-button>
                            </div>
                            <button type="button" wire:click="cerrar" class="inline-flex min-h-touch items-center justify-center text-md font-semibold text-ink-2">Cancelar</button>
                        </form>
                    @else
                        <button
                            type="button"
                            wire:click="abrir(@js($codigo))"
                            class="flex h-touch items-center justify-center rounded-md border border-ink font-sans text-md font-bold text-ink"
                        >Cumplir misión</button>
                    @endif
                @endif
            </article>
        @empty
            <x-empty-state
                titulo="No hay misiones por ahora"
                descripcion="Vuelve pronto: los comercios del Club publican misiones nuevas."
                icono="heroicon-o-flag"
            />
        @endforelse
    </main>
</div>
