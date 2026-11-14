<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\TipoDocumento;
use App\Http\Middleware\RequireIdempotencyKey;

/**
 * Compra por la API (E9-01, regla 9): documento, monto en pesos enteros y la
 * referencia del POS. El comercio y la sede salen de la credencial, nunca del
 * cuerpo (regla 2).
 */
class RegistrarCompraRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maximo = config('api.max_amount');

        return [
            ...self::documentoRules(),
            'monto' => ['required', 'integer', self::enteroJson(), 'min:1', 'max:' . (is_int($maximo) ? $maximo : 0)],
            'referencia_externa' => ['required', 'string', 'max:100'],
        ];
    }

    public function compra(): NuevaCompra
    {
        $credencial = $this->credencial();

        return new NuevaCompra(
            (int) $credencial->comercio_id,
            $credencial->sede_id,
            $this->tipoDocumento() ?? TipoDocumento::CC,
            (string) $this->documento(),
            $this->integer('monto'),
            $this->string('referencia_externa')->toString(),
            CanalCompra::Api,
            RequireIdempotencyKey::llave($this),
        );
    }
}
