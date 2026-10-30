{{-- Validar beneficio (E5-02, pantalla 8: 1j, 1k, 1l). Fondo blanco, una acción por pantalla, zonas de 64 px. --}}
<div class="flex min-h-dvh flex-col">
    @if ($paso === 'resultado' && $vista && $resultado['pantalla'] === 'valido')
        {{-- 1k: pantalla completa para girar el celular hacia el cliente. --}}
        <section class="flex min-h-dvh flex-col bg-success px-5 pb-5 text-white" data-resultado="valido">
            <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <x-heroicon-s-check-circle class="size-24" />
                <p class="font-display text-3xl">VÁLIDO</p>
                <p class="mt-3.5 text-balance font-display text-cajero-cifra font-bold leading-tight">{{ $vista['beneficio']?->nombre }}</p>
                <p class="text-lg font-normal">{{ $vista['valorTexto'] }}</p>
                @if ($vista['beneficio']?->condiciones_texto)
                    <p class="text-lg font-normal">{{ $vista['beneficio']->condiciones_texto }}</p>
                @endif
                @if ($vista['cliente'])
                    <p class="mt-3.5 text-cajero-nombre font-bold">{{ $vista['cliente']->nombre_completo ?? $vista['cliente']->tipo_documento->value . ' ' . $vista['cliente']->documento }}</p>
                    <x-tier-badge :nivel="$vista['cliente']->nivel_efectivo->value" />
                @endif
                @error('entrega') <p class="text-md font-bold" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <button
                    type="button"
                    wire:click="confirmar"
                    wire:loading.attr="disabled"
                    class="flex h-touch-cashier items-center justify-center rounded-lg bg-surface text-xl font-bold text-cashier-ink"
                >Confirmar entrega</button>
                <button type="button" wire:click="otro" class="h-touch text-base font-semibold underline">Cancelar</button>
            </div>
        </section>
    @elseif ($paso === 'resultado' && $vista)
        {{-- 1l (ya usado) y NO VÁLIDO, sobre fondo danger. --}}
        <section class="flex min-h-dvh flex-col bg-danger px-5 pb-5 text-white" data-resultado="{{ $resultado['pantalla'] }}">
            <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                <x-heroicon-s-x-circle class="size-24" />
                <p class="font-display text-3xl">{{ $resultado['pantalla'] === 'usado' ? 'YA USADO' : 'NO VÁLIDO' }}</p>
                @if ($vista['beneficio'])
                    <p class="mt-3.5 font-display text-2xl">{{ $vista['beneficio']->nombre }}</p>
                @endif

                @if ($resultado['pantalla'] === 'usado')
                    @if ($vista['usoTexto'])
                        <p class="text-balance text-lg font-normal">{{ $vista['usoTexto'] }}</p>
                    @endif
                    @if ($vista['vuelveTexto'])
                        <p class="mt-2 text-lg">Disponible de nuevo el {{ $vista['vuelveTexto'] }}</p>
                    @elseif ($vista['beneficio']?->max_por_cliente_ventana === \App\Enums\VentanaBeneficio::Vida)
                        <p class="mt-2 text-lg">Es de una vez por cliente: no vuelve.</p>
                    @endif
                @else
                    <p class="text-balance text-lg font-normal">{{ $vista['mensaje'] }}</p>
                    @if ($vista['proximaTexto'])
                        <p class="mt-2 text-lg">Disponible el {{ $vista['proximaTexto'] }}</p>
                    @endif
                @endif
            </div>

            <button type="button" wire:click="otro" class="flex h-touch-cashier items-center justify-center rounded-lg bg-surface text-xl font-bold text-cashier-ink">
                Escanear otro
            </button>
        </section>
    @elseif ($paso === 'entregado')
        <section
            class="flex min-h-dvh flex-col bg-success px-5 pb-5 text-white"
            x-data
            x-init="setTimeout(() => $wire.otro(), {{ $segundos * 1000 }})"
        >
            <div class="flex flex-1 flex-col items-center justify-center gap-2.5 text-center">
                <x-heroicon-s-check-circle class="size-24" />
                <p class="text-xl font-bold">Beneficio entregado</p>
                <p class="font-display text-cajero-monto font-bold tabular-nums">@pesos($valorEntregado ?? 0)</p>
                <p class="text-lg font-normal">de descuento</p>
            </div>
            <div class="flex flex-col gap-2">
                <button type="button" wire:click="otro" class="flex h-touch-cashier items-center justify-center rounded-lg bg-surface text-xl font-bold text-cashier-ink">
                    Escanear otro
                </button>
                <p class="text-center text-md font-semibold">Vuelve sola en {{ $segundos }} s</p>
            </div>
        </section>
    @else
        <x-cajero.encabezado :sede="$sede" :cajero="$cajero" />
        <x-cajero.modo activo="validar" />

        @if ($paso === 'escaner')
            {{-- 1j: escaneo continuo y un solo botón, "Digitar código". Sin linterna. --}}
            <section class="flex flex-1 flex-col gap-3 p-4" x-data="lectorQr({ accion: 'validarCodigo' })" wire:key="lector-beneficio">
                <div class="relative grid flex-1 place-items-center overflow-hidden rounded-lg bg-cashier-ink">
                    <video x-ref="video" class="absolute inset-0 size-full object-cover" playsinline muted></video>
                    <canvas x-ref="lienzo" class="hidden"></canvas>
                    <p x-show="activo" class="relative mt-auto mb-4 text-lg font-bold text-white">Apunta al código del cliente</p>
                    <div x-show="! activo" class="relative flex flex-col items-center gap-3 px-6 text-center text-white">
                        <p class="text-md" x-text="error ?? 'Abriendo la cámara…'"></p>
                        <button type="button" x-show="error" x-on:click="iniciar()" class="h-touch-cashier rounded-lg bg-surface px-5 text-lg font-bold text-cashier-ink">Intentar de nuevo</button>
                    </div>
                </div>

                @error('codigo') <p class="text-md font-bold text-danger" role="alert">{{ $message }}</p> @enderror

                @if ($digitar)
                    <form wire:submit="validarCodigo" class="flex gap-2" novalidate>
                        <label class="flex-1">
                            <span class="sr-only">Código del beneficio</span>
                            <input
                                type="text"
                                name="codigo"
                                wire:model="codigo"
                                placeholder="BBC-4821"
                                autocomplete="off"
                                autocapitalize="characters"
                                class="h-touch-cashier w-full rounded-md border border-field bg-surface px-4 font-mono text-xl uppercase outline-none placeholder:text-cashier-placeholder focus:border-brand"
                            >
                        </label>
                        <button type="submit" class="h-touch-cashier rounded-lg bg-cashier-ink px-5 text-lg font-bold text-white">Validar</button>
                    </form>
                    <button type="button" wire:click="porDocumento" class="h-touch-cashier text-md font-bold underline">Buscar por documento</button>
                @else
                    <button type="button" wire:click="$set('digitar', true)" class="flex h-touch-cashier items-center justify-center gap-2 rounded-lg bg-cashier-ink text-lg font-bold text-white">
                        <x-heroicon-o-hashtag class="size-5" />
                        Digitar código
                    </button>
                @endif
            </section>
        @elseif ($paso === 'documento')
            <form wire:submit="buscar" class="flex flex-1 flex-col gap-3.5 p-4" novalidate>
                <button type="button" wire:click="otro" class="flex h-touch-cashier items-center gap-2 self-start text-md font-bold">
                    <x-heroicon-o-arrow-left class="size-6" />
                    Escanear
                </button>

                <label class="flex flex-col gap-1.5">
                    <span class="text-md font-semibold">Tipo de documento</span>
                    <select wire:model.live="form.tipoDocumento" name="tipo_documento" class="h-touch-cashier rounded-md border border-field bg-surface px-4 text-lg">
                        @foreach (\App\Enums\TipoDocumento::cases() as $tipo)
                            <option value="{{ $tipo->value }}">{{ $tipo->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-md font-semibold">Número de documento</span>
                    <input
                        type="text"
                        name="documento"
                        wire:model="form.documento"
                        inputmode="{{ $form->tipoDocumento === 'CC' ? 'numeric' : 'text' }}"
                        autocomplete="off"
                        autocapitalize="characters"
                        class="h-touch-cashier rounded-md border border-field bg-surface px-4 text-2xl tabular-nums outline-none focus:border-brand"
                    >
                </label>
                @error('form.documento') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                @error('form.tipoDocumento') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror

                <div class="flex-1"></div>

                <button type="submit" wire:loading.attr="disabled" class="flex h-touch-cashier items-center justify-center rounded-lg bg-cashier-ink text-xl font-bold text-white">
                    Ver beneficios
                </button>
            </form>
        @elseif ($paso === 'lista')
            <section class="flex flex-1 flex-col gap-3 p-4">
                <button type="button" wire:click="porDocumento" class="flex h-touch-cashier items-center gap-2 self-start text-md font-bold">
                    <x-heroicon-o-arrow-left class="size-6" />
                    Otro documento
                </button>

                @if ($clienteLista)
                    <div class="flex flex-col gap-1">
                        <p class="text-cajero-nombre font-bold">{{ $clienteLista->nombre_completo ?? $clienteLista->tipo_documento->value . ' ' . $clienteLista->documento }}</p>
                        <span class="self-start"><x-tier-badge :nivel="$clienteLista->nivel_efectivo->value" /></span>
                    </div>
                @endif

                @forelse ($lista as $item)
                    @php
                        $detalle = match ($item->estado) {
                            \App\Enums\EstadoBeneficio::Disponible => \App\Beneficios\ResultadoEnCaja::valorTexto($item->beneficio->tipo, (int) $item->valor),
                            \App\Enums\EstadoBeneficio::Usado => $item->vuelve ? 'Ya lo usó · vuelve el ' . \App\Support\FormatoColombiano::fecha($item->vuelve) : 'Ya lo usó',
                            \App\Enums\EstadoBeneficio::NoDisponibleHoy => $item->proximaFecha ? 'Disponible el ' . \App\Support\FormatoColombiano::fecha($item->proximaFecha) : 'Falta su fecha de nacimiento',
                            \App\Enums\EstadoBeneficio::SinCupos => 'Sin cupos',
                            \App\Enums\EstadoBeneficio::Bloqueado => 'Desde ' . $item->nivelRequerido?->getLabel(),
                        };
                    @endphp
                    @if ($item->estado === \App\Enums\EstadoBeneficio::Disponible)
                        <button
                            type="button"
                            wire:click="elegir({{ $item->beneficio->id }})"
                            class="flex min-h-touch-cashier items-center justify-between gap-3 rounded-lg bg-cashier-ink px-4 py-3 text-left text-white"
                        >
                            <span>
                                <span class="block text-lg">{{ $item->beneficio->nombre }}</span>
                                <span class="block text-md">{{ $detalle }}</span>
                            </span>
                            <x-heroicon-o-arrow-right class="size-6 shrink-0" />
                        </button>
                    @else
                        <div class="flex min-h-touch-cashier flex-col justify-center rounded-lg border-2 border-field px-4 py-3" data-no-disponible>
                            <span class="text-lg">{{ $item->beneficio->nombre }}</span>
                            <span class="text-md text-cashier-ink-2">{{ $detalle }}</span>
                        </div>
                    @endif
                @empty
                    <x-notice tipo="info">Este comercio no tiene beneficios para este cliente hoy.</x-notice>
                @endforelse
            </section>
        @elseif ($paso === 'datos' && $vista)
            {{-- Monto de la cuenta (teclado de 1c) y soporte, antes de entregar. --}}
            <section class="flex flex-1 flex-col" x-data="tecladoMonto({ maximo: {{ $maxDigitos }}, porcentaje: {{ $vista['porcentaje'] ?? 'null' }} })">
                <div class="flex flex-col items-center gap-1 px-5 pt-4 text-center">
                    <p class="text-md font-bold">{{ $vista['beneficio']?->nombre }}</p>
                    <p class="text-md text-cashier-ink-2">{{ $vista['valorTexto'] }}</p>
                </div>

                @if ($vista['pideMonto'])
                    <div class="flex flex-1 flex-col items-center justify-center gap-1 px-5">
                        <p class="text-md font-bold text-cashier-ink-2">Monto de la cuenta</p>
                        <p class="font-display text-cajero-monto font-bold tabular-nums" x-text="texto" data-monto>$0</p>
                        <p class="text-lg tabular-nums">Descuento: <span x-text="descuento" data-descuento>$0</span></p>
                        @error('monto_cuenta') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="flex-1"></div>
                @endif

                <div class="flex flex-col gap-2.5 px-4 pb-4">
                    @if ($vista['pideSoporte'])
                        <label class="flex flex-col gap-1.5">
                            <span class="text-md font-semibold">Soporte (contrato o factura)</span>
                            <input
                                type="text"
                                name="soporte"
                                wire:model="soporte"
                                maxlength="100"
                                autocomplete="off"
                                class="h-touch-cashier rounded-md border border-field bg-surface px-4 text-lg outline-none focus:border-brand"
                            >
                        </label>
                        @error('soporte') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                    @endif

                    @if ($vista['pideMonto'])
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digito)
                                <button type="button" x-on:click="tecla('{{ $digito }}')" class="h-touch-cashier rounded-md bg-cashier-key text-cajero-tecla font-semibold">{{ $digito }}</button>
                            @endforeach
                            <button type="button" x-on:click="tecla('000')" class="h-touch-cashier rounded-md bg-cashier-key text-2xl font-semibold">000</button>
                            <button type="button" x-on:click="tecla('0')" class="h-touch-cashier rounded-md bg-cashier-key text-cajero-tecla font-semibold">0</button>
                            <button type="button" x-on:click="borrar()" class="grid h-touch-cashier place-items-center rounded-md" aria-label="Borrar">
                                <x-heroicon-o-backspace class="size-7" />
                            </button>
                        </div>
                    @endif

                    @error('entrega') <p class="text-md font-bold text-danger" role="alert">{{ $message }}</p> @enderror

                    <button
                        type="button"
                        x-on:click="$wire.entregar({{ $vista['pideMonto'] ? 'monto' : 'null' }})"
                        @if ($vista['pideMonto']) x-bind:disabled="monto === 0" @endif
                        wire:loading.attr="disabled"
                        class="flex h-touch-cashier items-center justify-center rounded-lg bg-cashier-ink text-xl font-bold text-white disabled:bg-sunken disabled:text-ink-2"
                    >Entregar beneficio</button>
                    <button type="button" wire:click="otro" class="h-touch-cashier text-md font-bold">Cancelar</button>
                </div>
            </section>
        @endif
    @endif
</div>
