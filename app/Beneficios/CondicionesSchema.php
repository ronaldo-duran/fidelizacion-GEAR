<?php

declare(strict_types=1);

namespace App\Beneficios;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Valida las condiciones de un beneficio contra el esquema cerrado
 * `resources/schemas/beneficio-condiciones.json` (regla dura 3.8): solo las
 * claves de la lista blanca y sus valores. Solo valida datos; nunca evalúa un
 * texto que venga del panel. Los mensajes los lee el administrador.
 */
class CondicionesSchema
{
    private ?object $schema = null;

    /**
     * @param  array<array-key, mixed>  $condiciones
     * @return list<string>
     */
    public function errors(array $condiciones): array
    {
        $json = json_encode((object) $condiciones, JSON_THROW_ON_ERROR);
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $error = (new Validator(max_errors: 1))->validate($data, $this->schema())->error();

        if ($error === null) {
            return [];
        }

        $errores = [];

        foreach (app(ErrorFormatter::class)->format($error, false) as $path => $message) {
            $detalle = is_string($message) ? $message : 'valor no permitido';
            $errores[] = sprintf('Las condiciones no cumplen el esquema en «%s»: %s', $path, $detalle);
        }

        return $errores;
    }

    private function schema(): object
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $contents = file_get_contents(resource_path('schemas/beneficio-condiciones.json'));
        $schema = $contents === false ? null : json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

        if (! is_object($schema)) {
            throw new RuntimeException('Could not read the benefit conditions schema.');
        }

        return $this->schema = $schema;
    }
}
