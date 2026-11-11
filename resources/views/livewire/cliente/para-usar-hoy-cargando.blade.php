{{--
    Mientras carga "Para usar hoy" (E8-03, regla 12): skeleton en `sunken`. Si
    la lista no llega (sin red o error del servidor), este bloque sigue en
    pantalla y a los pocos segundos ofrece reintentar.
--}}
<section class="flex flex-col gap-1" x-data="{ fallo: false }" x-init="setTimeout(() => fallo = true, 8000)">
    <h2 class="pb-1 text-lg">Para usar hoy</h2>

    <div x-show="! fallo" class="flex flex-col" aria-busy="true" aria-label="Cargando tus beneficios">
        @for ($fila = 0; $fila < 3; $fila++)
            <div class="flex h-14 items-center gap-3 border-b border-line">
                <x-skeleton class="size-10 rounded-sm" />
                <div class="flex flex-1 flex-col gap-1.5">
                    <x-skeleton class="h-4 w-3/5" />
                    <x-skeleton class="h-3 w-2/5" />
                </div>
            </div>
        @endfor
    </div>

    <div x-show="fallo" x-cloak class="flex flex-col gap-3 rounded-lg bg-sunken p-4" role="alert">
        <p class="text-md font-semibold">No pudimos cargar tus beneficios.</p>
        {{-- Mismo estilo que el botón secundario; x-button no pasa atributos de Alpine. --}}
        <button type="button" x-on:click="window.location.reload()" class="flex h-touch items-center justify-center rounded-md border border-ink font-sans text-md font-bold text-ink">Reintentar</button>
    </div>
</section>
