{{-- Pantalla de niveles del cliente (E8-03, pantalla 1r). --}}
<div class="flex flex-col">
    @php
        $formato = \App\Support\FormatoColombiano::class;
        $miles = static fn (int $n): string => number_format($n, 0, ',', '.');
        $periodo = static fn (\App\Models\PlanMembresia $plan): string => match ($plan->duracion_meses) {
            1 => '/mes',
            12 => '/año',
            default => " por {$plan->duracion_meses} meses",
        };
    @endphp

    <x-client-header />
    <x-tab-nav activa="niveles" />

    <main class="flex flex-col gap-3 px-4 pt-4 pb-6">
        <h1 class="sr-only">Niveles</h1>
        <p class="px-1 text-md text-ink-2">Todos los niveles se ganan con tus compras, sin pagar nada extra. Los beneficios no gastan puntos.</p>

        @foreach ($tarjetas as $tarjeta)
            @php
                $umbral = $tarjeta->umbral === null ? 'Al registrarte' : $formato::puntos($tarjeta->umbral);
                $cantidad = $tarjeta->beneficios === 1 ? '1 beneficio' : "{$tarjeta->beneficios} beneficios";
            @endphp

            <x-tier-card
                wire:key="nivel-{{ $tarjeta->nivel->value }}"
                :nivel="$tarjeta->nivel->value"
                :actual="$tarjeta->actual"
                :meta="$umbral . ' · ' . $cantidad"
            >
                @if ($tarjeta->faltan !== null)
                    <p class="text-sm font-semibold tabular-nums">Te faltan {{ $formato::puntos($tarjeta->faltan) }}</p>
                @endif

                @if ($tarjeta->nivel === \App\Enums\Nivel::Diamante)
                    @php
                        $vias = array_filter([
                            $tarjeta->umbral === null ? null : 'acumular ' . $formato::puntos($tarjeta->umbral),
                            'suscribirte',
                            'o comprar un inmueble con la inmobiliaria del grupo' . match (true) {
                                $mesesInmueble === null => '',
                                $mesesInmueble === 12 => ' (un año de Diamante)',
                                default => " ({$mesesInmueble} meses de Diamante)",
                            },
                        ]);
                    @endphp
                    <p class="text-md">Tres formas de llegar: {{ implode(', ', $vias) }}.</p>

                    @if ($conMembresia || $planDesde !== null)
                        <a
                            href="{{ route('cliente.membresia') }}"
                            class="flex min-h-touch items-center justify-center rounded-md px-4 py-3 text-md font-bold"
                            style="background: var(--color-tier-diamante-ink); color: var(--color-tier-diamante-bg)"
                        >
                            @if ($conMembresia)
                                Ver mi suscripción
                            @else
                                Suscripción desde @pesos($planDesde->precio_cop){{ $periodo($planDesde) }}
                            @endif
                        </a>
                    @endif
                @endif
            </x-tier-card>
        @endforeach
    </main>
</div>
