<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;

/**
 * Las opciones de los selectores de la definición de una regla: nombres para
 * mostrar y, como clave, el mismo id, slug o número que guarda la definición.
 */
final class OpcionesCondicion
{
    private const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @return array<int|string, string>
     */
    public static function para(mixed $campo): array
    {
        return match ($campo) {
            'categoria_slug' => self::categorias(),
            'comercio_id' => self::comercios(),
            'sede_id' => self::sedes(),
            'dia_semana' => self::DIAS,
            'hora' => self::horas(),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function categorias(): array
    {
        return Categoria::query()->get()
            ->sortBy('nombre')
            ->mapWithKeys(static fn (Categoria $categoria): array => [$categoria->slug => $categoria->nombre])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function comercios(): array
    {
        return Comercio::query()->get()
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }

    /**
     * Cada sede con su comercio, porque varios comercios tienen una "Sede principal".
     *
     * @return array<int, string>
     */
    private static function sedes(): array
    {
        return Sede::query()->with('comercio')->get()
            ->map(static fn (Sede $sede): array => [
                'id' => $sede->id,
                'nombre' => ($sede->comercio->razon_social ?? '') . ' · ' . $sede->nombre,
            ])
            ->sortBy('nombre')
            ->mapWithKeys(static fn (array $sede): array => [$sede['id'] => $sede['nombre']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function horas(): array
    {
        $horas = [];

        foreach (range(0, 23) as $hora) {
            $horas[$hora] = sprintf('%02d:00 a %02d:59', $hora, $hora);
        }

        return $horas;
    }
}
