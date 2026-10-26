{{-- Clientes por nivel (1z): barra apilada de 28 px con los colores de nivel, leyenda y origen de los Diamante. --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Clientes por nivel">
        <x-slot name="description">{{ $resumen }}</x-slot>

        <div class="flex flex-col gap-4">
            <div class="flex h-7 gap-0.5 overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5" role="img" aria-label="Distribución de clientes por nivel">
                @foreach ($niveles as $nivel)
                    @if ($nivel['clientes'] > 0)
                        <span class="h-full" style="flex: {{ $nivel['clientes'] }}; background: var(--color-tier-{{ $nivel['nivel'] }}-bg)"></span>
                    @endif
                @endforeach
            </div>

            <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @foreach ($niveles as $nivel)
                    <li class="flex items-center gap-2">
                        <span class="size-3 rounded-sm" style="background: var(--color-tier-{{ $nivel['nivel'] }}-bg)" aria-hidden="true"></span>
                        <span class="font-semibold">{{ $nivel['nombre'] }}</span>
                        <span class="tabular-nums text-gray-600 dark:text-gray-400">
                            {{ number_format($nivel['clientes'], 0, ',', '.') }} · {{ $nivel['porcentaje'] }} %
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
