<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Comercio;

/**
 * Test data for the customer home and the levels screen (E8-03): the customers and the today list
 * limit come from tests/Fixtures/inicio-niveles-escenarios.json.
 */
final class InicioTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function inicioFixture(): array
    {
        $json = file_get_contents(dirname(__DIR__) . '/Fixtures/inicio-niveles-escenarios.json');

        return json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Un cliente del fixture, con su nombre y sus fuentes de nivel.
     *
     * @param  array<string, mixed>  $caso
     */
    public static function clienteDelFixture(array $caso): Cliente
    {
        $cliente = NivelesTestData::cliente([...$caso['sources'], 'saldo_puntos' => $caso['saldo_puntos']]);
        $cliente->forceFill(['nombre_completo' => $caso['nombre_completo']])->save();

        return $cliente->refresh();
    }

    /**
     * Un beneficio vigente sin condiciones ni límites, con valor para los niveles dados.
     *
     * @param  array<string, int>  $valores
     * @param  array<string, mixed>  $extra
     */
    public static function beneficioDeInicio(
        Comercio $comercio,
        string $nombre,
        array $valores,
        array $extra = [],
    ): Beneficio {
        return Beneficio::query()->forceCreate([
            'comercio_id' => $comercio->id,
            'nombre' => $nombre,
            'tipo' => 'porcentaje',
            'valores_por_nivel' => $valores,
            'condiciones' => [],
            'vigente_desde' => '2026-01-01',
            'activo' => true,
            ...$extra,
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function clientesDelFixture(): array
    {
        $casos = [];

        foreach (self::inicioFixture()['customers'] as $caso) {
            $casos[$caso['_description']] = [$caso];
        }

        return $casos;
    }
}
