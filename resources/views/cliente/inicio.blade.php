{{-- TODO(E8-03): vista provisional del inicio; E8-03 la reemplaza con el bloque del nivel y "Para usar hoy". --}}
@php $cliente = auth('cliente')->user(); @endphp

<x-layouts.cliente title="Inicio">
    <x-client-header />
    <x-tab-nav activa="inicio" />

    <main class="flex flex-col gap-6 px-5 py-6">
        <h1 class="font-display text-2xl">Hola, {{ $cliente?->nombre_completo }}</h1>
        <p class="text-md text-ink-2">Ya eres parte del Club Aponte Rivera.</p>

        <form method="POST" action="{{ route('cliente.salir') }}">
            @csrf
            <x-button type="submit" variant="secundario">Cerrar sesión</x-button>
        </form>
    </main>
</x-layouts.cliente>
