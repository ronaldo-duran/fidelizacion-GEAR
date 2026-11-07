{{-- Compra o renovación de la membresía (E6-01, pantalla 1s de docs/diseno). --}}
<div class="flex min-h-dvh flex-col">
    @php $miles = static fn (int $n): string => number_format($n, 0, ',', '.'); @endphp

    <header class="flex items-center gap-1 px-1 pt-3">
        <a href="{{ route('cliente.inicio') }}" class="flex size-11 items-center justify-center" aria-label="Volver al inicio">
            <x-heroicon-o-arrow-left class="size-6" />
        </a>
        <h1 class="text-lg font-bold">Suscripción Diamante</h1>
    </header>

    <main class="flex flex-1 flex-col gap-4 px-4 pt-2 pb-5">
        <section
            class="flex flex-col gap-1 rounded-xl p-5"
            style="background: var(--color-tier-diamante-bg); color: var(--color-tier-diamante-ink); box-shadow: inset 0 0 0 2px var(--color-tier-diamante-accent)"
        >
            <span class="font-display text-3xl font-extrabold">Diamante</span>
            <p class="text-md">
                Los beneficios Diamante en los comercios del Club desde el día en que se confirma tu pago{{ $umbralDiamante ? ', sin esperar a los ' . $miles($umbralDiamante) . ' puntos' : '' }}.
            </p>
        </section>

        @if ($cliente->membresia_vigente_hasta?->gte(today()))
            <x-notice tipo="info">
                Tu membresía va hasta el {{ \App\Support\FormatoColombiano::fechaConAnio($cliente->membresia_vigente_hasta) }}.
                Si renuevas hoy, el periodo nuevo empieza al día siguiente y no pierdes ningún día.
            </x-notice>
        @endif

        <form wire:submit="pagar" class="flex flex-1 flex-col gap-4">
            <fieldset class="flex flex-col gap-3">
                <legend class="mb-3 text-md font-bold">Elige tu plan</legend>

                @forelse ($planes as $opcion)
                    <label
                        wire:key="plan-{{ $opcion->id }}"
                        class="flex min-h-touch cursor-pointer items-center gap-3 rounded-lg bg-surface p-4 {{ $opcion->id === $plan ? 'border-[2.5px] border-brand' : 'border-[1.5px] border-line' }}"
                    >
                        <input type="radio" wire:model.live="plan" value="{{ $opcion->id }}" class="sr-only" name="plan">
                        <span class="size-[22px] shrink-0 rounded-full {{ $opcion->id === $plan ? 'border-[7px] border-brand' : 'border-2 border-ink-2' }}" aria-hidden="true"></span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="text-md font-bold">{{ $opcion->periodicidad() }}</span>
                            <span class="text-sm text-ink-2">
                                {{ $opcion->duracionEnPalabras() }}, sin cobro automático
                            </span>
                        </span>
                        <span class="text-lg font-bold tabular-nums">@pesos($opcion->precio_cop)</span>
                    </label>
                @empty
                    <x-empty-state
                        titulo="No hay planes disponibles"
                        descripcion="Vuelve más tarde para comprar tu membresía."
                        icono="heroicon-o-sparkles"
                    />
                @endforelse

                @error('plan')
                    <p class="text-sm font-semibold text-danger" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-notice tipo="info">
                Tus {{ $miles($cliente->saldo_puntos) }} puntos siguen contando.
                @if ($umbralDiamante)
                    Si llegas a {{ $miles($umbralDiamante) }} comprando, conservas Diamante sin pagar.
                @endif
            </x-notice>

            <div class="mt-auto flex flex-col gap-2">
                @if ($elegido)
                    <x-button type="submit">
                        <span wire:loading.remove wire:target="pagar">Continuar al pago · @pesos($elegido->precio_cop)</span>
                        <span wire:loading wire:target="pagar">Abriendo el pago…</span>
                    </x-button>
                    <p class="text-center text-sm text-ink-2">Pagas en Wompi con tarjeta, PSE u otros medios. No guardamos tu tarjeta.</p>
                @endif
            </div>
        </form>
    </main>
</div>
