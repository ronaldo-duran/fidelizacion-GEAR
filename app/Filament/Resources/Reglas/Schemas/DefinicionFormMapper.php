<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Models\Regla;
use Illuminate\Validation\ValidationException;

/**
 * Maps between the Filament form state and a rule `definicion`. It only
 * converts types (fields arrive as text); validating and saving is the
 * engine's job (`App\Actions\Reglas`).
 */
class DefinicionFormMapper
{
    /**
     * @param  array<string, mixed>  $data  form state
     * @return array{0: array<string, mixed>, 1: DefinicionSource}
     *
     * @throws ValidationException
     */
    public static function fromForm(TipoRegla $tipo, array $data): array
    {
        if (($data['use_json_editor'] ?? false) === true) {
            return [self::fromJson($data['definicion_json'] ?? null), DefinicionSource::JsonEditor];
        }

        $state = self::toMap($data['definicion'] ?? null);

        $definicion = match ($tipo) {
            TipoRegla::Acumulacion => self::acumulacion($state),
            TipoRegla::Niveles => self::niveles($state),
            TipoRegla::Bonificacion => self::bonificacion($state),
        };

        return [$definicion, DefinicionSource::Form];
    }

    /**
     * Initial form state for saving a new version.
     *
     * @return array<string, mixed>
     */
    public static function toForm(Regla $regla): array
    {
        $definicion = $regla->definicion;

        if ($regla->tipo === TipoRegla::Bonificacion) {
            $definicion['condiciones'] = array_map(
                static function (array $condicion): array {
                    $valor = $condicion['valor'] ?? null;
                    $condicion['valor'] = is_array($valor)
                        ? implode(', ', array_map(self::toText(...), $valor))
                        : $valor;

                    return $condicion;
                },
                self::toListOfMaps($definicion['condiciones'] ?? null),
            );
        }

        return [
            'codigo' => $regla->codigo,
            'tipo' => $regla->tipo->value,
            'activa' => $regla->activa,
            'definicion' => $definicion,
            'definicion_json' => json_encode($regla->definicion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function fromJson(mixed $json): array
    {
        $definicion = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($definicion) || array_is_list($definicion)) {
            throw ValidationException::withMessages(['definicion' => 'El JSON no es un objeto válido.']);
        }

        return self::toMap($definicion);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function acumulacion(array $state): array
    {
        $topes = self::toMap($state['topes'] ?? null);

        return [
            'moneda_por_punto' => self::toInteger($state['moneda_por_punto'] ?? null),
            'tasas_por_categoria' => array_map(
                static fn (array $tasa): array => [
                    'categoria_slug' => $tasa['categoria_slug'] ?? null,
                    'puntos_por_unidad' => self::toInteger($tasa['puntos_por_unidad'] ?? null),
                ],
                self::toListOfMaps($state['tasas_por_categoria'] ?? null),
            ),
            'topes' => [
                'puntos_por_transaccion' => self::toInteger($topes['puntos_por_transaccion'] ?? null),
                'puntos_por_dia' => self::toInteger($topes['puntos_por_dia'] ?? null),
                'puntos_por_mes' => self::toInteger($topes['puntos_por_mes'] ?? null),
            ],
            'vigencia_puntos_meses' => self::toInteger($state['vigencia_puntos_meses'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function niveles(array $state): array
    {
        $avisos = $state['avisos_dias_antes'] ?? null;

        return [
            'umbrales' => array_map(
                static fn (array $umbral): array => [
                    'nivel' => $umbral['nivel'] ?? null,
                    'umbral_puntos' => self::toInteger($umbral['umbral_puntos'] ?? null),
                ],
                self::toListOfMaps($state['umbrales'] ?? null),
            ),
            'proteccion_meses' => self::toInteger($state['proteccion_meses'] ?? null),
            'avisos_dias_antes' => array_map(self::toInteger(...), is_array($avisos) ? array_values($avisos) : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function bonificacion(array $state): array
    {
        return [
            'multiplicador' => self::toInteger($state['multiplicador'] ?? null),
            'condiciones' => array_map(
                static fn (array $condicion): array => [
                    'campo' => $condicion['campo'] ?? null,
                    'operador' => $condicion['operador'] ?? null,
                    'valor' => self::conditionValue($condicion['operador'] ?? null, $condicion['valor'] ?? null),
                ],
                self::toListOfMaps($state['condiciones'] ?? null),
            ),
        ];
    }

    /**
     * The `en` operator receives its values separated by commas.
     */
    private static function conditionValue(mixed $operador, mixed $valor): mixed
    {
        if ($operador !== 'en' || ! is_string($valor)) {
            return self::toInteger($valor);
        }

        $parts = array_filter(
            array_map(trim(...), explode(',', $valor)),
            static fn (string $part): bool => $part !== '',
        );

        return array_map(self::toInteger(...), array_values($parts));
    }

    /**
     * Converts to integer only what is an integer written as text; anything
     * else passes through so the schema rejects it (e.g. "49999.99").
     */
    private static function toInteger(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $value;
    }

    private static function toText(mixed $value): string
    {
        return is_int($value) || is_string($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function toMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $contents) {
            $map[(string) $key] = $contents;
        }

        return $map;
    }

    /**
     * Filament repeaters key items by UUID; the schema expects lists.
     *
     * @return list<array<string, mixed>>
     */
    private static function toListOfMaps(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(self::toMap(...), $value));
    }
}
