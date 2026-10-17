<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Reglas\SimulateReglas;
use App\Enums\TipoRegla;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\CompraData;
use App\Reglas\ReglaAplicada;
use App\Reglas\SimulacionResultado;
use Carbon\CarbonImmutable;

/**
 * Test data for the rule simulator and the points calculator (E3-04).
 */
final class SimuladorTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/simulador-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The fixture's rules in force, as saved versions with made-up ids.
     *
     * @return list<ReglaAplicada>
     */
    public static function currentRules(): array
    {
        $rules = [];

        foreach (self::fixture()['current_rules'] as $index => $rule) {
            $rules[] = new ReglaAplicada(
                $index + 1,
                $rule['codigo'],
                $rule['version'],
                TipoRegla::from($rule['tipo']),
                $rule['definicion'],
            );
        }

        return $rules;
    }

    public static function draft(string $key = 'draft'): ReglaAplicada
    {
        $draft = self::fixture()[$key];

        return ReglaAplicada::draft($draft['codigo'], TipoRegla::from($draft['tipo']), $draft['definicion']);
    }

    /**
     * The fixture's sample purchases. `$comercioIds` maps the fixture ids to
     * real ones when the merchants exist in the database.
     *
     * @param  array<int, int>  $comercioIds
     * @return list<CompraData>
     */
    public static function purchases(array $comercioIds = []): array
    {
        $fixture = self::fixture();
        $categorias = array_column($fixture['comercios'], 'categoria_slug', 'id');
        $purchases = [];

        foreach ($fixture['purchases'] as $purchase) {
            $purchases[] = new CompraData(
                $purchase['monto'],
                $categorias[$purchase['comercio_id']],
                $comercioIds[$purchase['comercio_id']] ?? $purchase['comercio_id'],
                null,
                CarbonImmutable::parse($purchase['fecha']),
            );
        }

        return $purchases;
    }

    /**
     * Saves the fixture's merchants and rules in force. Returns the map of
     * fixture merchant ids to real ones.
     *
     * @return array<int, int>
     */
    public static function seed(): array
    {
        $fixture = self::fixture();
        $comercioIds = [];

        foreach ($fixture['comercios'] as $comercio) {
            $categoria = Categoria::query()->firstOrCreate(
                ['slug' => $comercio['categoria_slug']],
                ['nombre' => ucfirst($comercio['categoria_slug'])],
            );
            $comercioIds[$comercio['id']] = Comercio::factory()->create(['categoria_id' => $categoria->id])->id;
        }

        foreach ($fixture['current_rules'] as $rule) {
            Regla::query()->create([
                'codigo' => $rule['codigo'],
                'tipo' => TipoRegla::from($rule['tipo']),
                'version' => $rule['version'],
                'vigente_desde' => CarbonImmutable::now()->subMonth(),
                'activa' => true,
                'definicion' => $rule['definicion'],
            ]);
        }

        return $comercioIds;
    }

    /**
     * Runs the simulator with a draft of the fixture over its purchases.
     *
     * @param  array<int, int>  $comercioIds
     */
    public static function simulate(User $user, array $comercioIds, string $draftKey = 'draft'): SimulacionResultado
    {
        $draft = self::fixture()[$draftKey];

        return app(SimulateReglas::class)(
            $user,
            $draft['codigo'],
            TipoRegla::from($draft['tipo']),
            $draft['definicion'],
            self::purchases($comercioIds),
        );
    }

    /**
     * @param  array<string, mixed>  $definicion  keys replacing the defaults
     */
    public static function acumulacion(array $definicion = []): ReglaAplicada
    {
        return new ReglaAplicada(1, 'acumulacion-base', 1, TipoRegla::Acumulacion, array_replace([
            'moneda_por_punto' => 1000,
            'tasas_por_categoria' => [['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 1]],
            'vigencia_puntos_meses' => 12,
        ], $definicion));
    }

    public static function bonus(int $id, string $codigo, int $multiplicador, int $montoMinimo = 0): ReglaAplicada
    {
        return new ReglaAplicada($id, $codigo, 1, TipoRegla::Bonificacion, [
            'multiplicador' => $multiplicador,
            'condiciones' => [['campo' => 'monto', 'operador' => 'mayor_igual', 'valor' => $montoMinimo]],
        ]);
    }

    public static function restaurantPurchase(int $monto, string $fecha = '2026-10-13 10:00'): CompraData
    {
        return new CompraData($monto, 'restaurante', 1, null, CarbonImmutable::parse($fecha));
    }

    /**
     * Restaurant purchase of merchant 3 on Saturday 2026-10-17 at 18:30 in Bogota.
     */
    public static function saturdayPurchase(?int $sedeId = 7): CompraData
    {
        return new CompraData(60_000, 'restaurante', 3, $sedeId, CarbonImmutable::parse('2026-10-17 18:30'));
    }
}
