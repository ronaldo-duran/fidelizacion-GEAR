<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Enums\TipoEventoCalificado;
use App\Enums\TipoRegla;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Test data for the effective level (E3-03).
 */
final class NivelesTestData
{
    /** Balance that gives each level by points with the confirmed thresholds. */
    private const SALDO_POR_NIVEL = ['bronce' => 0, 'plata' => 500, 'oro' => 1500, 'diamante' => 3000];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/niveles-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Fixture cases that describe a customer's sources, as a dataset.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function sourceCases(): array
    {
        $cases = [];

        foreach (self::fixture()['cases'] as $case) {
            if (isset($case['sources'])) {
                $cases[$case['_e3_03_criterion']] = [$case];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        foreach (self::fixture()['cases'] as $case) {
            if ($case['_e3_03_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }

    /**
     * Turns `hoy`, `hoy+2m`, `hoy-1d` or `hoy+20d` into a date.
     */
    public static function day(string $relative): CarbonImmutable
    {
        if (preg_match('/^hoy(?:([+-])(\d+)([dm]))?$/', $relative, $parts) !== 1) {
            throw new RuntimeException("Unknown relative date: {$relative}");
        }

        $today = CarbonImmutable::today();

        if (! isset($parts[1])) {
            return $today;
        }

        $amount = (int) $parts[2] * ($parts[1] === '-' ? -1 : 1);

        return $parts[3] === 'm' ? $today->addMonths($amount) : $today->addDays($amount);
    }

    public static function nivelesRegla(int $proteccionMeses = 0): Regla
    {
        return Regla::query()->create([
            'codigo' => 'niveles',
            'tipo' => TipoRegla::Niveles,
            'version' => 1,
            'vigente_desde' => CarbonImmutable::now()->subYear(),
            'activa' => true,
            'definicion' => [
                'umbrales' => array_map(
                    static fn (string $nivel, int $puntos): array => ['nivel' => $nivel, 'umbral_puntos' => $puntos],
                    array_keys(self::SALDO_POR_NIVEL),
                    array_values(self::SALDO_POR_NIVEL),
                ),
                'proteccion_meses' => $proteccionMeses,
                'avisos_dias_antes' => [30, 7, 1],
            ],
        ]);
    }

    /**
     * A customer with the fixture sources written straight into the columns.
     * The balance matches the points level unless the case gives one; the
     * current effective level is the given one or the highest source.
     *
     * @param  array<string, mixed>  $sources
     */
    public static function cliente(array $sources): Cliente
    {
        $porPuntos = $sources['nivel_por_puntos'] ?? 'bronce';
        $hasta = static fn (?string $value): ?CarbonImmutable => $value === null ? null : self::day($value);

        $cliente = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::CC,
            'documento' => (string) fake()->unique()->numerify('##########'),
        ]);
        $cliente->forceFill([
            'saldo_puntos' => $sources['saldo_puntos'] ?? self::SALDO_POR_NIVEL[$porPuntos],
            'nivel_por_puntos' => $porPuntos,
            'nivel_por_membresia' => $sources['nivel_por_membresia'] ?? null,
            'membresia_vigente_hasta' => $hasta($sources['membresia_vigente_hasta'] ?? null),
            'nivel_por_evento' => $sources['nivel_por_evento'] ?? null,
            'evento_vigente_hasta' => $hasta($sources['evento_vigente_hasta'] ?? null),
            'nivel_efectivo' => $sources['nivel_efectivo'] ?? Nivel::highest(
                Nivel::from($porPuntos),
                Nivel::tryFrom($sources['nivel_por_membresia'] ?? ''),
                Nivel::tryFrom($sources['nivel_por_evento'] ?? ''),
            )->value,
            'proteccion_hasta' => $hasta($sources['proteccion_hasta'] ?? null),
        ])->save();

        return $cliente;
    }

    /**
     * @param  array{tipo: string, vigente_hasta: string, motivo_cierre?: string}  $evento
     */
    public static function evento(
        Cliente $cliente,
        array $evento,
        ?Comercio $comercio = null,
        ?User $user = null,
    ): EventoCalificado {
        $created = EventoCalificado::query()->create([
            'cliente_id' => $cliente->id,
            'comercio_id' => ($comercio ?? Comercio::factory()->create())->id,
            'tipo' => TipoEventoCalificado::from($evento['tipo']),
            'vigente_desde' => CarbonImmutable::today()->subYear(),
            'vigente_hasta' => self::day($evento['vigente_hasta']),
            'soporte_path' => 'soportes/prueba.pdf',
            'registrado_por' => ($user ?? User::factory()->administradorDelGrupo()->create())->id,
        ]);

        if (isset($evento['motivo_cierre'])) {
            $created->forceFill(['motivo_cierre' => $evento['motivo_cierre']])->save();
        }

        return $created;
    }
}
