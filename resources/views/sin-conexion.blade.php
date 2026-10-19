<x-layouts.cliente title="Sin conexión">
    <div class="w-full bg-ink px-5 py-2 text-center text-sm font-semibold text-paper">Sin conexión</div>

    <main class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-sunken text-ink-2">
            <x-heroicon-o-signal-slash class="size-7" />
        </span>
        <h1 class="font-display text-2xl font-bold">No hay conexión</h1>
        <p class="text-md text-ink-2">Revisa tu internet y vuelve a intentarlo. Tus puntos y beneficios siguen a salvo.</p>
        <div class="w-full max-w-xs">
            <x-button variant="primario" href="/">Reintentar</x-button>
        </div>
    </main>
</x-layouts.cliente>
