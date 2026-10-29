{{-- Caja del comercio (E4-02, pantalla 8: 1c, 1g, 1h, 1i). Fondo blanco, una acción por pantalla, zonas de 64 px. --}}
<div class="flex min-h-dvh flex-col">
    @if ($paso === 'registrada')
        {{-- 1g: pantalla completa para girar el celular hacia el cliente. --}}
        <section
            class="flex min-h-dvh flex-col bg-success px-5 pb-5 text-white"
            x-data
            x-init="setTimeout(() => $wire.nuevaCompra(), {{ $segundos * 1000 }})"
        >
            <div class="flex flex-1 flex-col items-center justify-center gap-2.5 text-center">
                <x-heroicon-s-check-circle class="size-24" />
                <p class="text-xl font-bold">Compra registrada</p>
                <p class="font-display text-hero tabular-nums">+{{ number_format($puntos, 0, ',', '.') }}</p>
                <p class="text-cajero-tecla font-bold">puntos</p>
                @if ($cliente)
                    <p class="mt-4 text-cajero-nombre font-bold">{{ $cliente->nombre_completo ?? $cliente->tipo_documento->value . ' ' . $cliente->documento }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-2 text-lg font-normal">
                        <x-tier-badge :nivel="$cliente->nivel_efectivo->value" />
                        @if ($siguiente)
                            <span>Le faltan {{ number_format($siguiente['faltan'], 0, ',', '.') }} para {{ $siguiente['nivel']->getLabel() }}</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-2">
                <button type="button" wire:click="nuevaCompra" class="flex h-touch-cashier items-center justify-center rounded-lg bg-surface text-xl font-bold text-cashier-ink">
                    Nueva compra
                </button>
                <div class="flex items-center justify-between text-md font-semibold">
                    <span>Vuelve sola en {{ $segundos }} s</span>
                    <button
                        type="button"
                        wire:click="anular({{ $transaccionId }})"
                        wire:confirm="¿Anular esta compra de @pesos($monto)?"
                        class="h-touch-cashier underline"
                    >Anular esta compra</button>
                </div>
            </div>
        </section>
    @elseif ($paso === 'registro_qr')
        {{-- 1i: el cliente se registra desde su celular y el cajero atiende al siguiente. --}}
        <section class="flex min-h-dvh flex-col gap-4 p-4">
            <h1 class="text-lg">Registrar cliente</h1>

            <div class="flex items-center gap-3 rounded-md border-2 border-warn-line bg-warn-soft px-3.5 py-3">
                <x-heroicon-o-clock class="size-6 shrink-0" />
                <p class="text-md">
                    <b>Compra pendiente: @pesos($monto)</b><br>
                    {{ $form->tipoDocumento }} {{ $form->documento }} · Los puntos se le suman cuando se registre
                </p>
            </div>

            <div class="flex flex-1 flex-col items-center justify-center gap-4 px-2 text-center">
                <p class="font-display text-cajero-nombre font-bold leading-tight">Que el cliente escanee<br>este código</p>
                <div class="w-full max-w-64 rounded-lg border-3 border-cashier-ink p-3.5 [&_svg]:h-auto [&_svg]:w-full" data-qr-registro="{{ $enlace }}">
                    {!! $qr !!}
                </div>
                <p class="text-base text-ink-3">Nombre, documento y celular desde su teléfono. Al terminar, los puntos aparecen en su cuenta.</p>
            </div>

            <button type="button" wire:click="nuevaCompra" class="flex h-touch-cashier items-center justify-center rounded-lg bg-cashier-ink text-xl font-bold text-white">
                Atender al siguiente
            </button>
        </section>
    @else
        <x-cajero.encabezado :sede="$sede" :cajero="$cajero" />
        <x-cajero.modo />

        @if ($paso === 'monto')
            {{-- 1c: monto primero. No se muestran los puntos que suma (decisión del cliente). --}}
            <section class="flex flex-1 flex-col" x-data="tecladoMonto({ maximo: {{ $maxDigitos }} })">
                @if ($aviso)
                    <div class="mx-4 mt-3"><x-notice tipo="info">{{ $aviso }}</x-notice></div>
                @endif

                <div class="flex flex-1 flex-col items-center justify-center gap-1 px-5 pt-5">
                    <p class="text-md font-bold text-cashier-ink-2">Monto de la compra</p>
                    <p class="font-display text-cajero-monto font-bold tabular-nums" x-text="texto" data-monto>$0</p>
                    @error('monto') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2.5 px-4 pb-4">
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

                    <button
                        type="button"
                        x-on:click="$wire.continuar(monto)"
                        x-bind:disabled="monto === 0"
                        class="flex h-touch-cashier items-center justify-center gap-2.5 rounded-lg bg-cashier-ink text-xl font-bold text-white disabled:bg-sunken disabled:text-ink-2"
                    >
                        Continuar
                        <x-heroicon-o-arrow-right class="size-5.5" />
                    </button>

                    @if ($ultima)
                        <button
                            type="button"
                            wire:click="anular({{ $ultima->id }})"
                            wire:confirm="¿Anular la última compra, de @pesos($ultima->monto)?"
                            class="h-touch-cashier text-md font-bold text-danger"
                        >Anular la última compra (@pesos($ultima->monto))</button>
                    @endif
                </div>
            </section>
        @elseif ($paso === 'identificar')
            <section class="flex flex-1 flex-col gap-4 px-4 pb-4 pt-4">
                <div class="flex items-center justify-between">
                    <button type="button" wire:click="volverA('monto')" class="flex h-touch-cashier items-center gap-2 text-md font-bold">
                        <x-heroicon-o-arrow-left class="size-6" />
                        Monto
                    </button>
                    <p class="font-display text-2xl tabular-nums">@pesos($monto)</p>
                </div>

                <p class="text-center text-sm font-bold">¿Quién es el cliente?</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['documento' => ['Cédula', 'heroicon-o-identification'], 'qr' => ['Escanear QR', 'heroicon-o-camera']] as $opcion => [$texto, $icono])
                        <button
                            type="button"
                            wire:click="$set('metodo', '{{ $opcion }}')"
                            @class([
                                'flex h-touch-cashier items-center justify-center gap-2 rounded-lg text-lg font-bold',
                                'bg-cashier-ink text-white' => $metodo === $opcion,
                                'bg-cashier-key text-cashier-ink' => $metodo !== $opcion,
                            ])
                            aria-pressed="{{ $metodo === $opcion ? 'true' : 'false' }}"
                        >
                            <x-dynamic-component :component="$icono" class="size-6" />
                            {{ $texto }}
                        </button>
                    @endforeach
                </div>

                @if ($metodo === 'documento')
                    <form wire:submit="buscar" class="flex flex-1 flex-col gap-3.5" novalidate>
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
                            Buscar cliente
                        </button>
                    </form>
                @else
                    <div class="flex flex-1 flex-col gap-3" x-data="lectorQr" wire:key="lector-qr">
                        <div class="relative grid aspect-square w-full place-items-center overflow-hidden rounded-lg bg-cashier-ink">
                            <video x-ref="video" class="absolute inset-0 size-full object-cover" playsinline muted></video>
                            <canvas x-ref="lienzo" class="hidden"></canvas>
                            <p x-show="activo" class="relative mt-auto mb-4 text-lg font-bold text-white">Apunta al código del cliente</p>
                            <div x-show="! activo" class="relative flex flex-col items-center gap-3 px-6 text-center text-white">
                                <p class="text-md" x-text="error ?? 'Abriendo la cámara…'"></p>
                                <button type="button" x-show="error" x-on:click="iniciar()" class="h-touch-cashier rounded-lg bg-surface px-5 text-lg font-bold text-cashier-ink">Intentar de nuevo</button>
                            </div>
                        </div>

                        @error('form.codigo')
                            <div class="flex flex-col gap-2">
                                <p class="text-md font-bold text-danger" role="alert">{{ $message }}</p>
                                <button type="button" x-on:click="iniciar()" class="h-touch-cashier rounded-lg bg-cashier-key text-lg font-bold">Escanear otra vez</button>
                            </div>
                        @enderror

                        <form wire:submit="identificarQr" class="flex gap-2" novalidate>
                            <label class="flex-1">
                                <span class="sr-only">Código del cliente</span>
                                <input
                                    type="text"
                                    name="codigo"
                                    wire:model="form.codigo"
                                    placeholder="AR-1234"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    class="h-touch-cashier w-full rounded-md border border-field bg-surface px-4 font-mono text-xl uppercase outline-none placeholder:text-cashier-placeholder focus:border-brand"
                                >
                            </label>
                            <button type="submit" class="flex h-touch-cashier items-center gap-2 rounded-lg bg-cashier-ink px-4 text-lg font-bold text-white">
                                <x-heroicon-o-hashtag class="size-5" />
                                Digitar código
                            </button>
                        </form>
                    </div>
                @endif
            </section>
        @elseif ($paso === 'confirmar')
            {{-- Paso de confirmación (decisión 2 de la spec): el nombre del cliente antes de registrar. --}}
            <section class="flex flex-1 flex-col gap-4 px-4 pb-4 pt-4">
                <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                    <p class="text-md font-bold text-cashier-ink-2">¿Registrar la compra a este cliente?</p>
                    <p class="text-cajero-nombre font-bold leading-tight">{{ $cliente?->nombre_completo ?? $form->tipoDocumento . ' ' . $form->documento }}</p>
                    @if ($cliente?->nombre_completo)
                        <p class="text-md text-cashier-ink-2">{{ $form->tipoDocumento }} {{ $form->documento }}</p>
                    @endif
                    @if ($cliente)
                        <x-tier-badge :nivel="$cliente->nivel_efectivo->value" />
                    @endif
                    <p class="mt-3 font-display text-cajero-monto font-bold tabular-nums">@pesos($monto)</p>
                    @error('registro') <p class="text-md font-bold text-danger" role="alert">{{ $message }}</p> @enderror
                </div>

                <button
                    type="button"
                    wire:click="registrar"
                    wire:loading.attr="disabled"
                    class="flex h-touch-cashier items-center justify-center rounded-lg bg-cashier-ink text-xl font-bold text-white disabled:bg-sunken disabled:text-ink-2"
                >
                    <span wire:loading.remove wire:target="registrar">Registrar compra</span>
                    <span wire:loading wire:target="registrar">Registrando…</span>
                </button>
                <button type="button" wire:click="volverA('identificar')" class="flex h-touch-cashier items-center justify-center rounded-lg bg-cashier-key text-lg font-bold">
                    No es el cliente
                </button>
            </section>
        @elseif ($paso === 'no_registrado')
            {{-- 1h: el monto queda guardado y el teclado se reemplaza por tres salidas. --}}
            <section class="flex flex-1 flex-col gap-2.5 px-4 pb-4 pt-4">
                <div class="rounded-lg border-3 border-warn bg-warn-soft px-3.5 py-2.5">
                    <p class="text-overline uppercase text-warn-ink">{{ $form->tipo()->getLabel() }}</p>
                    <p class="text-cajero-cifra font-bold tabular-nums">{{ $form->documento }}</p>
                    <p class="mt-1 flex items-center gap-2 text-lg text-warn-ink">
                        <x-heroicon-s-exclamation-triangle class="size-5.5" />
                        {{ $form->tipoDocumento === 'CC' ? 'No está registrada' : 'No está registrado' }}
                    </p>
                </div>

                <div class="flex items-center justify-between rounded-lg border-2 border-field px-3.5 py-2.5">
                    <div>
                        <p class="text-overline uppercase text-cashier-ink-2">Monto</p>
                        <p class="text-cajero-cifra font-bold tabular-nums">@pesos($monto)</p>
                    </div>
                    <p class="flex items-center gap-1.5 text-sm font-bold text-success">
                        <x-heroicon-o-lock-closed class="size-4.5" />
                        Guardado
                    </p>
                </div>

                @error('registro') <p class="text-md font-bold text-danger" role="alert">{{ $message }}</p> @enderror

                <div class="flex-1"></div>

                <button type="button" wire:click="guardarPendiente" wire:loading.attr="disabled" class="flex min-h-19 items-center gap-3.5 rounded-lg bg-brand px-4.5 text-left text-white">
                    <x-heroicon-o-clock class="size-7 shrink-0" />
                    <span>
                        <span class="block text-lg">Guardar pendiente y seguir</span>
                        <span class="block text-sm font-normal">Los puntos se le suman cuando se registre</span>
                    </span>
                </button>
                <button type="button" wire:click="registrarAhora" wire:loading.attr="disabled" class="flex min-h-19 items-center gap-3.5 rounded-lg bg-cashier-ink px-4.5 text-left text-white">
                    <x-heroicon-o-user-plus class="size-7 shrink-0" />
                    <span>
                        <span class="block text-lg">Registrar cliente ahora</span>
                        <span class="block text-sm font-normal">Él escanea un código con su celular</span>
                    </span>
                </button>
                <button type="button" wire:click="volverA('identificar')" class="flex h-touch-cashier items-center justify-center gap-2.5 rounded-lg bg-cashier-key text-lg font-bold">
                    <x-heroicon-o-pencil class="size-5.5" />
                    Corregir cédula
                </button>
            </section>
        @endif
    @endif
</div>
