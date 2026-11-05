{{-- Pasarela simulada (solo local y E2E): sin dinero real. --}}
<x-layouts.cliente title="Pasarela de prueba">
    <main class="flex flex-1 flex-col gap-5 px-4 py-6">
        <x-notice>Pasarela de prueba. Aquí no se cobra nada.</x-notice>

        <section class="flex flex-col gap-1 rounded-lg border border-line bg-surface p-4">
            <span class="text-sm text-ink-2">{{ $pago->plan->nombre }}</span>
            <span class="font-display text-3xl tabular-nums">@pesos($pago->monto_cop)</span>
            <span class="text-sm text-ink-2">Referencia {{ $pago->referencia }}</span>
        </section>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-auto flex flex-col gap-3">
            @csrf
            <input type="hidden" name="resultado" value="aprobar">
            <x-button type="submit">Aprobar pago</x-button>
        </form>
        <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf
            <input type="hidden" name="resultado" value="rechazar">
            <x-button type="submit" variant="secundario">Rechazar pago</x-button>
        </form>
    </main>
</x-layouts.cliente>
