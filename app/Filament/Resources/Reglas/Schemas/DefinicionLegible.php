<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Enums\TipoRegla;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\Sede;
use App\Support\FormatoColombiano;

/**
 * La definición de una regla en frases para la pestaña "Vista": nombres en vez
 * de ids y slugs, y cifras con formato colombiano. Solo presenta; el JSON de
 * "Modo desarrollo" sigue siendo la fuente.
 */
final class DefinicionLegible
{
    private const OPERADORES = [
        'igual' => 'es',
        'distinto' => 'no es',
        'mayor' => 'es mayor que',
        'mayor_igual' => 'es mayor o igual que',
        'menor' => 'es menor que',
        'menor_igual' => 'es menor o igual que',
        'en' => 'está en',
    ];

    private const CAMPOS = [
        'monto' => 'El monto',
        'categoria_slug' => 'La categoría',
        'comercio_id' => 'El comercio',
        'sede_id' => 'La sede',
        'dia_semana' => 'El día',
        'hora' => 'La hora',
    ];

    private const DIAS = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
        7 => 'domingo',
    ];

    private const TOPES = [
        'puntos_por_transaccion' => 'por compra',
        'puntos_por_dia' => 'por día',
        'puntos_por_mes' => 'por mes',
    ];

    /**
     * @return list<string>
     */
    public static function lineas(Regla $regla): array
    {
        $definicion = $regla->definicion;

        return match ($regla->tipo) {
            TipoRegla::Acumulacion => self::acumulacion($definicion),
            TipoRegla::Niveles => self::niveles($definicion),
            TipoRegla::Bonificacion => self::bonificacion($definicion),
        };
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return list<string>
     */
    private static function acumulacion(array $definicion): array
    {
        $lineas = ['Un punto base por cada ' . self::pesos($definicion['moneda_por_punto'] ?? null) . '.'];

        foreach (self::lista($definicion['tasas_por_categoria'] ?? null) as $tasa) {
            $nombre = self::valor('categoria_slug', $tasa['categoria_slug'] ?? null);
            $puntos = self::cantidad($tasa['puntos_por_unidad'] ?? null, 'punto', 'puntos');
            $lineas[] = "{$nombre}: {$puntos} por unidad.";
        }

        $topes = is_array($definicion['topes'] ?? null) ? $definicion['topes'] : [];

        foreach (self::TOPES as $clave => $etiqueta) {
            $tope = $topes[$clave] ?? null;
            $lineas[] = $tope === null
                ? "Sin tope {$etiqueta}."
                : "Tope {$etiqueta}: " . self::cantidad($tope, 'punto', 'puntos') . '.';
        }

        $vigencia = self::cantidad($definicion['vigencia_puntos_meses'] ?? null, 'mes', 'meses');
        $lineas[] = "Los puntos vencen a los {$vigencia}.";

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return list<string>
     */
    private static function niveles(array $definicion): array
    {
        $lineas = [];

        foreach (self::lista($definicion['umbrales'] ?? null) as $umbral) {
            $lineas[] = ucfirst(self::texto($umbral['nivel'] ?? null)) . ' desde '
                . self::cantidad($umbral['umbral_puntos'] ?? null, 'punto', 'puntos') . '.';
        }

        $avisos = is_array($definicion['avisos_dias_antes'] ?? null) ? $definicion['avisos_dias_antes'] : [];
        $proteccion = self::cantidad($definicion['proteccion_meses'] ?? null, 'mes', 'meses');
        $lineas[] = "Protección antes de bajar: {$proteccion}.";
        $lineas[] = 'Avisos de descenso: ' . implode(', ', array_map(self::numero(...), $avisos)) . ' días antes.';

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return list<string>
     */
    private static function bonificacion(array $definicion): array
    {
        $multiplicador = self::numero($definicion['multiplicador'] ?? null);
        $lineas = ["Multiplica los puntos por {$multiplicador} cuando se cumple todo esto:"];

        foreach (self::lista($definicion['condiciones'] ?? null) as $condicion) {
            $campo = self::texto($condicion['campo'] ?? null);
            $valor = $condicion['valor'] ?? null;
            $valores = array_map(
                static fn (mixed $uno): string => self::valor($campo, $uno),
                is_array($valor) ? $valor : [$valor],
            );
            $lineas[] = (self::CAMPOS[$campo] ?? $campo) . ' '
                . (self::OPERADORES[self::texto($condicion['operador'] ?? null)] ?? '') . ' '
                . implode(', ', $valores) . '.';
        }

        return $lineas;
    }

    private static function valor(string $campo, mixed $valor): string
    {
        $nombre = match ($campo) {
            'monto' => self::pesos($valor),
            'categoria_slug' => Categoria::query()->toBase()->where('slug', self::texto($valor))->value('nombre'),
            'comercio_id' => Comercio::query()->toBase()->where('id', $valor)->value('razon_social'),
            'sede_id' => Sede::query()->toBase()->where('id', $valor)->value('nombre'),
            'dia_semana' => self::DIAS[is_int($valor) ? $valor : 0] ?? null,
            'hora' => sprintf('%02d:00', is_int($valor) ? $valor : 0),
            default => null,
        };

        return is_string($nombre) ? $nombre : self::texto($valor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lista(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $lista = [];

        foreach ($valor as $item) {
            if (is_array($item)) {
                $mapa = [];

                foreach ($item as $clave => $contenido) {
                    $mapa[(string) $clave] = $contenido;
                }

                $lista[] = $mapa;
            }
        }

        return $lista;
    }

    private static function pesos(mixed $valor): string
    {
        return is_int($valor) ? FormatoColombiano::pesos($valor) : self::texto($valor);
    }

    /**
     * La cifra con su unidad en singular o plural: "1 punto", "12 meses".
     */
    private static function cantidad(mixed $valor, string $singular, string $plural): string
    {
        return self::numero($valor) . ' ' . ($valor === 1 ? $singular : $plural);
    }

    private static function numero(mixed $valor): string
    {
        return is_int($valor) ? number_format($valor, 0, ',', '.') : self::texto($valor);
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) || is_int($valor) ? (string) $valor : '';
    }
}
