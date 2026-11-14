<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Beneficios\CheckBeneficioCredencial;
use App\Http\Resources\Api\V1\ChequeoCodigoJson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Valida el código de un beneficio sin gastarlo (E9-01, operación 6), igual
 * que el escáner de la caja (E5-02). Un código de otro comercio es "no
 * válido".
 */
class CodigosController extends ApiController
{
    /**
     * @throws AuthorizationException
     */
    public function show(Request $request, CheckBeneficioCredencial $check, string $codigo): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('La API siempre tiene credencial.');
        }

        return self::datos(ChequeoCodigoJson::datos($check($user, $codigo)));
    }
}
