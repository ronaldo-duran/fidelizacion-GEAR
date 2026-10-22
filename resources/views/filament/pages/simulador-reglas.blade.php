@php
    $topes = [
        'puntos_por_transaccion' => 'Tope por transacción',
        'puntos_por_dia' => 'Tope por día',
        'puntos_por_mes' => 'Tope por mes',
    ];
    $numero = static fn (int $valor): string => number_format($valor, 0, ',', '.');
@endphp

<x-filament-panels::page>
    <form wire:submit="simulate" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-play">
                Simular
            </x-filament::button>
        </div>
    </form>

    @if ($errores !== [])
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">El borrador no se puede simular</x-slot>

            <ul class="flex list-disc flex-col gap-1 ps-5 text-sm text-danger-600 dark:text-danger-400">
                @foreach ($errores as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($resultado !== null)
        <x-filament::section>
            <x-slot name="heading">Resultado</x-slot>
            <x-slot name="description">
                Vigentes: {{ $numero($resultado['total_vigente']) }} pts · Borrador:
                {{ $numero($resultado['total_borrador']) }} pts · Diferencia:
                {{ $numero($resultado['total_borrador'] - $resultado['total_vigente']) }} pts
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm tabular-nums">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="px-3 py-2 text-start font-medium">Compra</th>
                            <th class="px-3 py-2 text-start font-medium">Reglas vigentes</th>
                            <th class="px-3 py-2 text-start font-medium">Borrador</th>
                            <th class="px-3 py-2 text-end font-medium">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultado['lineas'] as $linea)
                            <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                <td class="px-3 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">
                                        ${{ $numero($linea['monto']) }}
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $linea['fecha'] }}</div>
                                </td>
                                @foreach (['vigente', 'borrador'] as $lado)
                                    @php($desglose = $linea[$lado])
                                    <td class="px-3 py-3">
                                        <div class="font-medium text-gray-950 dark:text-white">
                                            {{ $numero($desglose['puntos']) }} pts
                                        </div>
                                        <div class="flex flex-col gap-0.5 text-gray-500 dark:text-gray-400">
                                            <span>Base: {{ $numero($desglose['base']) }} · {{ $desglose['regla'] ?? 'sin regla de acumulación' }}</span>
                                            @if ($desglose['bonificacion'] !== null)
                                                <span>Bonificación: {{ $desglose['bonificacion'] }}</span>
                                            @endif
                                            @if ($desglose['tope'] !== null)
                                                <span class="text-warning-600 dark:text-warning-400">
                                                    {{ $topes[$desglose['tope']] ?? $desglose['tope'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-end font-medium text-gray-950 dark:text-white">
                                    {{ $linea['diferencia'] > 0 ? '+' : '' }}{{ $numero($linea['diferencia']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
