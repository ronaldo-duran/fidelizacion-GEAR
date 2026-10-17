<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\TipoRegla;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Test data for the points ledger (E3-02).
 */
final class LedgerTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/movimientos-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        foreach (self::fixture()['cases'] as $case) {
            if ($case['_e3_02_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }

    public static function cliente(string $documento = '1010101010'): Cliente
    {
        return Cliente::query()->create(['documento' => $documento]);
    }

    public static function acumulacionRegla(): Regla
    {
        return Regla::query()->create([
            'codigo' => 'acumulacion-base',
            'tipo' => TipoRegla::Acumulacion,
            'version' => 1,
            'vigente_desde' => CarbonImmutable::now()->subYear(),
            'definicion' => [
                'moneda_por_punto' => 1000,
                'tasas_por_categoria' => [['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 1]],
                'vigencia_puntos_meses' => 12,
            ],
        ]);
    }

    /**
     * Unsaved movement for pure lot calculations.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function movimiento(int $id, array $attributes): MovimientoPuntos
    {
        return app(MovimientoPuntos::class)->forceFill(['id' => $id, ...$attributes]);
    }
}
