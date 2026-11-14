<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

/**
 * Anulación por la API (E9-01, regla 10): solo el motivo; la compra va en la
 * ruta.
 */
class AnularCompraRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'max:255']];
    }

    public function motivo(): string
    {
        return $this->string('motivo')->trim()->toString();
    }
}
