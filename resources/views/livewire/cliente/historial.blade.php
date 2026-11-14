{{-- Historial de puntos del cliente (E8-05, pantalla 1q). --}}
<div class="flex flex-col">
    <x-client-header />
    <x-tab-nav activa="historial" />

    <main class="flex flex-col gap-3 pt-4 pb-6">
        <div class="flex flex-col gap-0.5 px-5">
            <h1 class="text-md font-semibold text-ink-2">{{ $historial->titulo }}</h1>
            <p class="flex items-baseline gap-2">
                <span class="font-display text-cifra tabular-nums">{{ number_format($historial->saldo, 0, ',', '.') }}</span>
                <span class="text-md text-ink-2">cuentan para subir de nivel</span>
            </p>
        </div>

        @if ($historial->avisoVencimiento !== null)
            <div class="px-4">
                <x-notice icono="heroicon-o-clock"><b>{{ $historial->avisoVencimiento[0] }}</b> {{ $historial->avisoVencimiento[1] }}</x-notice>
            </div>
        @endif

        @if ($historial->meses === [])
            <div class="px-4">
                <x-empty-state
                    titulo="Todavía no tienes movimientos"
                    descripcion="Tus compras en los comercios del Club suman puntos."
                    icono="heroicon-o-clock"
                />
            </div>
        @else
            <div class="flex flex-col px-5">
                @foreach ($historial->meses as $mes => $filas)
                    <section class="flex flex-col" aria-label="{{ $mes }}">
                        <h2 class="pt-2 pb-0.5 text-overline uppercase text-ink-2">{{ $mes }}</h2>
                        <ul class="flex flex-col">
                            @foreach ($filas as $fila)
                                <li class="flex min-h-14 items-center gap-3 border-b border-line">
                                    <span class="flex min-w-0 flex-1 flex-col">
                                        <span class="text-md font-bold">{{ $fila->titulo }}</span>
                                        <span class="text-sm font-normal text-ink-2">{{ $fila->linea }}</span>
                                    </span>
                                    <span class="text-lg font-bold tabular-nums {{ $fila->suma ? 'text-success-ink' : 'text-danger' }}">{{ $fila->puntos }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </main>
</div>
