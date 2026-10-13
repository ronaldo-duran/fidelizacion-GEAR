<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Enums\TipoRegla;
use App\Models\Categoria;
use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Validates a rule definition in two layers (hard rule 3.8):
 *
 * 1. Closed JSON Schema (`resources/schemas/regla.json`): whitelist of keys,
 *    fields and operators. No free-form expressions.
 * 2. Checks JSON Schema cannot express: ordering of thresholds and notices,
 *    operator/value consistency, no repeated categories.
 *
 * It only validates data; it never evaluates strings coming from the panel.
 * Error messages are shown to the administrator, so they stay in Spanish.
 */
class ReglaDefinitionValidator
{
    private const NIVELES = ['bronce', 'plata', 'oro', 'diamante'];

    private const ORDERING_OPERATORS = ['mayor', 'mayor_igual', 'menor', 'menor_igual'];

    private ?object $schema = null;

    /**
     * Validates before saving: besides the shape, the categories must exist.
     * Throws a ValidationException with the errors under `definicion`.
     *
     * @param  array<array-key, mixed>  $definicion
     *
     * @throws ValidationException
     */
    public function validate(TipoRegla $tipo, array $definicion): void
    {
        $errors = $this->errors($tipo->value, $definicion);

        if ($errors === [] && $tipo === TipoRegla::Acumulacion) {
            $errors = $this->missingCategorias($definicion);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['definicion' => $errors]);
        }
    }

    /**
     * Shape errors, without touching the database.
     *
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    public function errors(string $tipo, array $definicion): array
    {
        $errors = $this->schemaErrors($tipo, $definicion);

        if ($errors !== []) {
            return $errors;
        }

        return match (TipoRegla::from($tipo)) {
            TipoRegla::Acumulacion => $this->acumulacionErrors($definicion),
            TipoRegla::Niveles => $this->nivelesErrors($definicion),
            TipoRegla::Bonificacion => $this->bonificacionErrors($definicion),
        };
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function schemaErrors(string $tipo, array $definicion): array
    {
        $data = json_decode(
            json_encode(['tipo' => $tipo, 'definicion' => $definicion], JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $result = (new Validator(max_errors: 1))->validate($data, $this->schema());
        $error = $result->error();

        if ($error === null) {
            return [];
        }

        $errors = [];

        foreach (app(ErrorFormatter::class)->format($error, false) as $path => $message) {
            $detail = is_string($message) ? $message : 'valor no permitido';
            $errors[] = sprintf('La definición no cumple el esquema en «%s»: %s', $path, $detail);
        }

        return $errors;
    }

    private function schema(): object
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $contents = file_get_contents(resource_path('schemas/regla.json'));
        $schema = $contents === false ? null : json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

        if (! is_object($schema)) {
            throw new RuntimeException('Could not read the rule schema.');
        }

        return $this->schema = $schema;
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function acumulacionErrors(array $definicion): array
    {
        $slugs = $this->slugs($definicion);
        $errors = [];

        foreach ($slugs as $slug) {
            if ($slug !== mb_strtolower(trim($slug))) {
                $errors[] = "La categoría «{$slug}» debe escribirse en minúsculas y sin espacios.";
            }
        }

        if (count($slugs) !== count(array_unique($slugs))) {
            $errors[] = 'Cada categoría puede aparecer una sola vez en las tasas.';
        }

        return $errors;
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function nivelesErrors(array $definicion): array
    {
        /** @var list<array{nivel: string, umbral_puntos: int}> $umbrales */
        $umbrales = $definicion['umbrales'];
        /** @var list<int> $avisos */
        $avisos = $definicion['avisos_dias_antes'];

        $errors = [];
        $points = array_column($umbrales, 'umbral_puntos');

        if (array_column($umbrales, 'nivel') !== self::NIVELES) {
            $errors[] = 'Los umbrales deben ir en orden bronce, plata, oro y diamante, cada nivel una sola vez.';
        }

        if ($points[0] !== 0) {
            $errors[] = 'Bronce siempre arranca en 0 puntos.';
        }

        if (! $this->isStrictlyIncreasing($points)) {
            $errors[] = 'Cada umbral debe ser mayor que el del nivel anterior.';
        }

        if (! $this->isStrictlyIncreasing(array_reverse($avisos))) {
            $errors[] = 'Los avisos de descenso van de mayor a menor, sin repetir días.';
        }

        return $errors;
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function bonificacionErrors(array $definicion): array
    {
        /** @var list<array{campo: string, operador: string, valor: int|string|list<int|string>}> $condiciones */
        $condiciones = $definicion['condiciones'];
        $errors = [];

        foreach ($condiciones as $index => $condicion) {
            $position = $index + 1;
            $valor = $condicion['valor'];

            if ($condicion['operador'] === 'en' && (! is_array($valor) || $valor === [])) {
                $errors[] = "Condición {$position}: el operador «en» necesita una lista de valores.";
            }

            if ($condicion['operador'] !== 'en' && is_array($valor)) {
                $errors[] = "Condición {$position}: solo el operador «en» admite una lista.";
            }

            if (in_array($condicion['operador'], self::ORDERING_OPERATORS, true) && ! is_int($valor)) {
                $errors[] = "Condición {$position}: las comparaciones de mayor y menor necesitan un número entero.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function missingCategorias(array $definicion): array
    {
        $slugs = $this->slugs($definicion);
        $query = Categoria::query();
        $query->getQuery()->whereIn('slug', $slugs);
        $existing = $query->get()->map(static fn (Categoria $categoria): string => $categoria->slug)->all();
        $missing = array_diff($slugs, $existing);

        if ($missing === []) {
            return [];
        }

        return ['No existen estas categorías: ' . implode(', ', $missing) . '.'];
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @return list<string>
     */
    private function slugs(array $definicion): array
    {
        /** @var list<array{categoria_slug: string, puntos_por_unidad: int}> $tasas */
        $tasas = $definicion['tasas_por_categoria'];

        return array_column($tasas, 'categoria_slug');
    }

    /**
     * @param  list<int>  $values
     */
    private function isStrictlyIncreasing(array $values): bool
    {
        for ($i = 1, $total = count($values); $i < $total; $i++) {
            if ($values[$i] <= $values[$i - 1]) {
                return false;
            }
        }

        return true;
    }
}
