{{-- Estado de un pago de membresía (E6-01). Mientras está pendiente se consulta sola cada 3 s. --}}
<div class="flex min-h-dvh flex-col" @if ($pago->estado === \App\Enums\EstadoPago::Pendiente) wire:poll.3s @endif>
    <header class="flex items-center gap-1 px-1 pt-3">
        <a href="{{ route('cliente.inicio') }}" class="flex size-11 items-center justify-center" aria-label="Volver al inicio">
            <x-heroicon-o-arrow-left class="size-6" />
        </a>
        <span class="text-lg font-bold">Tu pago</span>
    </header>

    <main class="flex flex-1 flex-col gap-5 px-4 pt-4 pb-5">
        @switch($pago->estado)
            @case(\App\Enums\EstadoPago::Aprobado)
                <section
                    class="flex flex-col gap-2 rounded-xl p-5"
                    style="background: var(--color-tier-diamante-bg); color: var(--color-tier-diamante-ink); box-shadow: inset 0 0 0 2px var(--color-tier-diamante-accent)"
                >
                    <x-heroicon-s-check-badge class="size-10" style="color: var(--color-tier-diamante-accent)" />
                    <h1 class="font-display text-3xl font-extrabold">Ya eres {{ $pago->nivel->getLabel() }}</h1>
                    <p class="text-md">
                        Recibimos tu pago de @pesos($pago->monto_cop).
                        @if ($cliente->membresia_vigente_hasta)
                            Tu membresía va hasta el {{ \App\Support\FormatoColombiano::fechaConAnio($cliente->membresia_vigente_hasta) }}.
                        @endif
                    </p>
                </section>
                <x-notice tipo="info">Tus puntos siguen contando, como siempre.</x-notice>
                <div class="mt-auto">
                    <x-button :href="route('cliente.inicio')">Ver mis beneficios</x-button>
                </div>
                @break

            @case(\App\Enums\EstadoPago::Pendiente)
                <section class="flex flex-col items-center gap-3 rounded-xl bg-sunken p-6 text-center" role="status">
                    <x-heroicon-o-clock class="size-10 text-brand" />
                    <h1 class="font-display text-2xl">Estamos confirmando tu pago</h1>
                    <p class="text-md text-ink-2">
                        La pasarela nos avisa en unos segundos. Esta pantalla se actualiza sola; no tienes que pagar otra vez.
                    </p>
                </section>
                @break

            @default
                <section class="flex flex-col items-center gap-3 rounded-xl border border-danger-line bg-danger-soft p-6 text-center" role="alert">
                    <x-heroicon-o-x-circle class="size-10 text-danger" />
                    <h1 class="font-display text-2xl">No pudimos confirmar tu pago</h1>
                    <p class="text-md">La pasarela no aprobó el pago de @pesos($pago->monto_cop). No se te cobró la membresía.</p>
                </section>
                <div class="mt-auto">
                    <x-button :href="route('cliente.membresia')">Intentar de nuevo</x-button>
                </div>
        @endswitch

        <p class="text-center text-sm text-ink-2">Referencia {{ $pago->referencia }}</p>
    </main>
</div>
