{{-- TODO(E8-03): vista provisional del inicio; E8-03 la reemplaza con el bloque del nivel y "Para usar hoy". --}}
@php
    $cliente = auth('cliente')->user();
    // El plan más barato para el enlace a la suscripción (E6-01); E8-03 lo lleva a la pantalla de niveles.
    $mensual = \App\Models\PlanMembresia::query()->where('activo', true)->orderBy('precio_cop')->first();
@endphp

<x-layouts.cliente title="Inicio">
    <x-client-header />
    <x-tab-nav activa="inicio" />

    <main class="flex flex-col gap-6 px-5 py-6">
        <h1 class="font-display text-2xl">Hola, {{ $cliente?->nombre_completo }}</h1>
        <p class="text-md text-ink-2">Ya eres parte del Club Aponte Rivera.</p>

        <a href="{{ route('cliente.misiones') }}" class="flex min-h-touch items-center gap-3 rounded-lg border border-line bg-surface p-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-sm bg-brand-soft text-brand">
                <x-heroicon-o-flag class="size-5" />
            </span>
            <span class="flex min-w-0 flex-1 flex-col">
                <span class="text-md font-bold">Misiones</span>
                <span class="text-sm text-ink-2">Suma puntos cumpliendo misiones de los comercios.</span>
            </span>
            <x-heroicon-o-chevron-right class="size-5 shrink-0 text-ink-2" />
        </a>

        @if ($mensual)
            <a href="{{ route('cliente.membresia') }}" class="flex min-h-touch items-center gap-3 rounded-lg border border-line bg-surface p-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-sm" style="background: var(--color-tier-diamante-bg); color: var(--color-tier-diamante-accent)">
                    <x-heroicon-o-sparkles class="size-5" />
                </span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="text-md font-bold">Suscripción Diamante</span>
                    <span class="text-sm text-ink-2">Desde @pesos($mensual->precio_cop) al mes, sin cobro automático.</span>
                </span>
                <x-heroicon-o-chevron-right class="size-5 shrink-0 text-ink-2" />
            </a>
        @endif

        <form method="POST" action="{{ route('cliente.salir') }}">
            @csrf
            <x-button type="submit" variant="secundario">Cerrar sesión</x-button>
        </form>
    </main>
</x-layouts.cliente>
