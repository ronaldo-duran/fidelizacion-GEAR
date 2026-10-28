@props(['sede', 'cajero'])

{{-- CashierHeader (pantalla 8): comercio y sede sobre el cajero. El sistema no numera cajas: se muestra la sede. --}}
<header class="flex items-center justify-between gap-3 px-4 pt-3">
    <div class="min-w-0">
        <p class="truncate text-sm text-cashier-ink-2">{{ $sede?->comercio?->razon_social }} · {{ $sede?->nombre }}</p>
        <p class="truncate text-md font-bold">{{ $cajero->name }}</p>
    </div>

    <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false">
        <button type="button" class="grid size-touch place-items-center" x-on:click="abierto = ! abierto" aria-label="Menú">
            <x-heroicon-o-bars-3 class="size-6.5" />
        </button>

        <div x-show="abierto" x-cloak class="absolute right-0 top-full z-10 w-56 rounded-md border border-line bg-surface p-2 shadow-float">
            <form method="POST" action="{{ route('caja.salir') }}">
                @csrf
                <button type="submit" class="flex h-touch-cashier w-full items-center gap-3 rounded-md px-3 text-lg text-danger">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="size-6" />
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
