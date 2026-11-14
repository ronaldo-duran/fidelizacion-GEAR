<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

/**
 * Consulta por documento (E9-01, operaciones 1 y 2): tipo y número, en la
 * consulta de la URL.
 */
class ConsultarClienteRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::documentoRules();
    }
}
