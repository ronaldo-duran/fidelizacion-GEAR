<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\RedeemBeneficioApi;
use App\Beneficios\EntregaRechazada;
use App\Http\Requests\Api\V1\RedimirBeneficioRequest;
use App\Http\Resources\Api\V1\RedencionJson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Entrega de beneficios por la API (E9-01, operación 7). La llave de
 * idempotencia es la referencia de la redención.
 */
class RedencionesController extends ApiController
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws EntregaRechazada
     */
    public function store(RedimirBeneficioRequest $request, RedeemBeneficioApi $redeem): JsonResponse
    {
        $resultado = $redeem($request->credencial(), $request->entrega());

        return self::escrito(RedencionJson::datos($resultado->recurso), $resultado->repetido);
    }
}
