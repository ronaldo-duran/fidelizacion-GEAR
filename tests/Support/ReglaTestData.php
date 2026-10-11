<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Reglas\CreateRegla;
use App\Enums\TipoRegla;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Test data for the rule engine (E3-01).
 */
final class ReglaTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function bonificacion(int $multiplicador = 2): array
    {
        return [
            'multiplicador' => $multiplicador,
            'condiciones' => [['campo' => 'monto', 'operador' => 'mayor_igual', 'valor' => 50000]],
        ];
    }

    public static function createBonificacion(User $admin, string $codigo = 'doble-fin-de-semana'): Regla
    {
        return app(CreateRegla::class)($admin, $codigo, TipoRegla::Bonificacion, self::bonificacion());
    }

    /**
     * Version 1 inserted straight through the model, skipping the actions.
     */
    public static function createVersionOne(string $codigo = 'Doble-Fin-De-Semana '): Regla
    {
        return Regla::query()->create([
            'codigo' => $codigo,
            'tipo' => TipoRegla::Bonificacion,
            'version' => 1,
            'vigente_desde' => CarbonImmutable::parse('2026-10-12 09:00:00'),
            'definicion' => self::bonificacion(),
        ]);
    }

    /**
     * Cases of a rule fixture as a Pest dataset: [tipo, definicion].
     *
     * @return array<string, array{0: string, 1: array<array-key, mixed>}>
     */
    public static function cases(string $file): array
    {
        $path = __DIR__ . "/../Fixtures/{$file}";
        $contents = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];

        foreach ($contents['casos'] as $index => $case) {
            $name = ($index + 1) . '. ' . ($case['_descripcion'] ?? $case['_motivo']);
            $cases[$name] = [$case['tipo'], $case['definicion']];
        }

        return $cases;
    }
}
