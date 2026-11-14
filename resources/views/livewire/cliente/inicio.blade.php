{{-- Inicio del cliente (E8-03, pantalla 1f sin QR personal). --}}
<div class="flex flex-col">
    @php
        $nivel = $resumen->nivel->value;
        $formato = \App\Support\FormatoColombiano::class;
        $fechaHasta = $resumen->hasta === null
            ? null
            : ($resumen->hasta->year === now()->year ? $formato::fecha($resumen->hasta) : $formato::fechaConAnio($resumen->hasta));
    @endphp

    <section style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink)">
        <x-client-header :nivel="$nivel" />
        <x-tab-nav activa="inicio" :en-nivel="true" />

        <div class="flex items-end justify-between gap-3 px-5 pt-5 pb-7">
            <div class="flex min-w-0 flex-col gap-1">
                <p class="text-md font-semibold">{{ $resumen->primerNombre ? "{$resumen->primerNombre}, eres" : 'Eres' }}</p>
                <h1 class="font-display text-nivel">{{ $resumen->nivel->getLabel() }}</h1>
                @if ($fechaHasta)
                    <p class="text-md font-semibold">
                        {{ $resumen->origen === \App\Enums\OrigenNivel::Membresia ? 'Por tu suscripción' : 'Por tu compra' }}, hasta el {{ $fechaHasta }}
                    </p>
                @endif
            </div>
            <span class="pb-3"><x-tier-diamonds :nivel="$nivel" /></span>
        </div>
    </section>

    <main class="flex flex-col gap-6 px-5 pt-5 pb-6">
        @if ($resumen->siguiente !== null)
            <div class="flex flex-col gap-2">
                <p class="flex items-baseline gap-2.5">
                    <span class="font-display text-cifra tabular-nums">{{ number_format((int) $resumen->faltan, 0, ',', '.') }}</span>
                    <span class="text-lg font-normal">puntos más para <b>{{ $resumen->siguiente->getLabel() }}</b></span>
                </p>
                <x-progress-bar :porcentaje="$resumen->porcentaje" />
            </div>
        @else
            <p class="text-lg font-normal">Estás en el nivel más alto del Club.</p>
        @endif

        <livewire:cliente.para-usar-hoy />

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
    </main>
</div>
