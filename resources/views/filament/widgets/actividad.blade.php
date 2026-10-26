{{-- Comercios (o sedes) por actividad (1z): filas de 44 px tabulares; las inactivas en warn-soft con "Sin registros hace N días". --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$titulo">
        <x-slot name="description">
            {{ count($filas) }} {{ $unidad }} · ordenado por transacciones
        </x-slot>

        @if ($filas === [])
            <p class="text-sm text-gray-600 dark:text-gray-400">Todavía no hay {{ $unidad }} activos.</p>
        @else
            <div x-data="{ todos: false }" class="flex flex-col gap-3">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">{{ $unidad === 'sedes' ? 'Sede' : 'Comercio' }}</th>
                                <th class="px-3 py-2 text-right">Transacc.</th>
                                <th class="px-3 py-2 text-right">Beneficios</th>
                                <th class="px-3 py-2 text-right">Valor ben.</th>
                                <th class="px-3 py-2 text-right">vs. {{ $vsMes }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($filas as $posicion => $fila)
                                <tr
                                    @class(['h-11', 'bg-warn-soft text-warn-ink' => $fila['inactivo']])
                                    @unless ($fila['visible']) x-show="todos" x-cloak @endunless
                                >
                                    <td class="px-3">{{ $posicion + 1 }}</td>
                                    <td class="px-3">
                                        <span class="font-semibold">{{ $fila['nombre'] }}</span>
                                        <span @class(['text-gray-500 dark:text-gray-400' => ! $fila['inactivo']])>· {{ $fila['detalle'] }}</span>
                                    </td>
                                    <td class="px-3 text-right">{{ number_format($fila['transacciones'], 0, ',', '.') }}</td>
                                    <td class="px-3 text-right">{{ number_format($fila['beneficios'], 0, ',', '.') }}</td>
                                    <td class="px-3 text-right">{{ $fila['valor'] }}</td>
                                    <td @class([
                                        'px-3 text-right font-semibold',
                                        'text-success-600' => ! $fila['inactivo'] && $fila['tendencia'] > 0,
                                        'text-danger-600' => ! $fila['inactivo'] && $fila['tendencia'] < 0,
                                    ])>
                                        {{ $fila['inactivo'] ? $fila['aviso'] : $fila['variacion'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($ocultas > 0)
                    <div x-show="! todos">
                        <x-filament::button color="gray" size="sm" x-on:click="todos = true">
                            Ver los {{ count($filas) }} {{ $unidad }}
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
