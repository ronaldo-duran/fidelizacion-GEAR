<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Api\EntregaApi;
use App\Http\Middleware\RequireIdempotencyKey;

/**
 * Entrega de un beneficio por la API (E9-01, operación 7): por el código que
 * muestra el cliente, o por su documento y el beneficio. El monto de la
 * cuenta (para un porcentaje) y el soporte los exige la entrega según el
 * beneficio.
 */
class RedimirBeneficioRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required_without:documento', 'nullable', 'string', 'max:500'],
            ...self::documentoRules('required_without:codigo'),
            'beneficio_id' => ['required_without:codigo', 'nullable', 'integer', self::enteroJson()],
            'monto_cuenta_cop' => ['nullable', 'integer', self::enteroJson(), 'min:1'],
            'soporte' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function entrega(): EntregaApi
    {
        $codigo = $this->input('codigo');
        $soporte = $this->input('soporte');

        return new EntregaApi(
            is_string($codigo) && $codigo !== '' ? $codigo : null,
            $this->tipoDocumento(),
            $this->documento(),
            $this->filled('beneficio_id') ? $this->integer('beneficio_id') : null,
            $this->filled('monto_cuenta_cop') ? $this->integer('monto_cuenta_cop') : null,
            is_string($soporte) ? $soporte : null,
            RequireIdempotencyKey::llave($this),
        );
    }
}
