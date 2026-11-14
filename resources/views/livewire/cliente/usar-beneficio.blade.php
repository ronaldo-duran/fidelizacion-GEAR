{{-- Detalle y uso de un beneficio (E8-04, pantallas 1o y 1p). --}}
<div class="flex flex-col">
    @php
        $beneficio = $estado?->beneficio;
        $tipo = $estado?->estado;
        $Estado = \App\Enums\EstadoBeneficio::class;
    @endphp

    <header class="flex items-center gap-1 px-2 pb-1 pt-3">
        <a href="{{ route('cliente.beneficios') }}" class="flex size-11 items-center justify-center rounded-full" aria-label="Volver a los beneficios">
            <x-heroicon-o-arrow-left class="size-6" />
        </a>
        <span class="truncate text-lg">{{ $beneficio?->comercio?->razon_social }}</span>
    </header>

    <main class="flex flex-col gap-5 px-4 pt-2 pb-6">
        @if ($estado === null)
            <x-empty-state
                titulo="Este beneficio ya no está disponible"
                descripcion="Revisa los otros beneficios del Club."
                icono="heroicon-o-gift"
            >
                <x-slot:cta>
                    <x-button variant="enlace" :href="route('cliente.beneficios')">Ver los beneficios</x-button>
                </x-slot:cta>
            </x-empty-state>
        @else
            <div class="flex flex-col gap-1">
                <h1 class="font-display text-2xl">{{ $beneficio->nombre }}</h1>
                @if ($beneficio->condiciones_texto)
                    <p class="text-md text-ink-2">{{ $beneficio->condiciones_texto }}</p>
                @endif
            </div>

            @if ($tipo === $Estado::Disponible)
                <section
                    x-data="usoBeneficio({ servidorMs: {{ $servidorMs }}, refrescoMs: {{ $refrescoMs }} })"
                    wire:init="renovar"
                    class="flex flex-col items-center gap-4 rounded-xl bg-brand p-5 text-white"
                    aria-label="Código para usar el beneficio"
                >
                    <p class="flex items-center gap-2 font-display text-xl font-extrabold">
                        <x-heroicon-s-check-badge class="size-6" />
                        LISTO PARA USAR
                    </p>

                    @if ($error)
                        <div wire:key="codigo-error" class="flex flex-col items-center gap-3 rounded-lg bg-surface p-4 text-center text-ink">
                            <p class="text-md">No pudimos generar tu código.</p>
                            <button type="button" wire:click="renovar" class="h-touch rounded-md border border-ink px-4 font-sans text-md font-bold text-ink">Reintentar</button>
                        </div>
                    @elseif ($imagenQr === null)
                        {{-- Cada bloque con su llave: Livewire no reusa el skeleton para el código y el x-show se conserva. --}}
                        <div wire:key="codigo-cargando" class="size-50 animate-pulse rounded-lg bg-sunken" aria-label="Cargando tu código"></div>
                    @else
                        <div wire:key="codigo-{{ $codigo }}" x-show="codigoVigente()" class="flex flex-col items-center gap-3">
                            <div class="size-50 rounded-lg bg-surface p-2 [&>svg]:size-full" role="img" aria-label="Código QR del beneficio">
                                {!! $imagenQr !!}
                            </div>
                            <p class="font-mono text-2xl font-bold tracking-widest tabular-nums">{{ $codigo }}</p>
                        </div>
                        <p wire:key="codigo-sin-conexion" x-show="!codigoVigente()" x-cloak class="rounded-lg bg-surface p-4 text-center text-md text-ink">
                            Sin conexión. Tu código aparece cuando vuelvas a tener señal.
                        </p>
                    @endif

                    <p class="flex items-center gap-2 text-md font-semibold tabular-nums">
                        <span class="size-2.5 rounded-full" style="background: var(--color-live)" aria-hidden="true"></span>
                        <span x-text="hora()"></span>
                    </p>
                    <p class="text-md font-semibold">{{ $cliente->nombre_completo }} · {{ $cliente->nivel_efectivo->getLabel() }}</p>
                </section>

                <p class="text-center text-md text-ink-2">Muestra esta pantalla al cajero. Él escanea el código y listo.</p>
            @elseif ($tipo === $Estado::Usado)
                <section class="flex flex-col items-center gap-3 rounded-xl bg-sunken p-5 text-center">
                    <span class="flex size-16 items-center justify-center rounded-full bg-surface text-brand">
                        <x-heroicon-o-check class="size-8" />
                    </span>
                    <h2 class="font-display text-xl font-bold">{{ \App\Beneficios\CatalogoCliente::yaLoUsaste($beneficio) }}</h2>
                    @if ($usado)
                        <p class="text-md text-ink-2">{{ $usado }}</p>
                    @endif
                    @if ($vuelve)
                        <span class="inline-flex items-center gap-2 rounded-full bg-surface px-3 py-1 text-sm font-semibold">
                            <x-heroicon-o-calendar class="size-4" />
                            Vuelve el {{ $vuelve }}
                        </span>
                    @endif
                </section>
            @elseif ($tipo === $Estado::Bloqueado)
                <section class="flex flex-col items-start gap-3 rounded-xl bg-sunken p-5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-semibold"
                        style="background: var(--color-tier-{{ $estado->nivelRequerido?->value }}-bg); color: var(--color-tier-{{ $estado->nivelRequerido?->value }}-ink)"
                    >
                        <x-heroicon-s-lock-closed class="size-3" />
                        {{ $estado->nivelRequerido?->getLabel() }}
                    </span>
                    <p class="text-lg">{{ $meta }}</p>
                    <x-button variant="enlace" :href="route('cliente.niveles')">Ver cómo subir de nivel</x-button>
                </section>
            @else
                <x-notice tipo="aviso">{{ $meta }}</x-notice>
            @endif

            @if ($otros !== [])
                <section class="flex flex-col gap-2" aria-labelledby="otros-para-hoy">
                    <h2 id="otros-para-hoy" class="px-1 text-lg">Otros para hoy</h2>
                    @foreach ($otros as $f)
                        <x-benefit-row
                            wire:key="otro-{{ $f->beneficioId() }}"
                            :comercio="$f->comercio()"
                            :beneficio="$f->nombre()"
                            :meta="$f->meta"
                            :href="route('cliente.beneficio', $f->beneficioId())"
                        />
                    @endforeach
                </section>
            @endif
        @endif
    </main>
</div>
