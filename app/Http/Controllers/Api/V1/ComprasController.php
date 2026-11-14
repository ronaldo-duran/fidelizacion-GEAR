<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\AnnulCompraApi;
use App\Actions\Compras\RegisterCompra;
use App\Api\ErrorApi;
use App\Compras\CompraEnConflicto;
use App\Compras\NuevaCompra;
use App\Http\Requests\Api\V1\AnularCompraRequest;
use App\Http\Requests\Api\V1\RegistrarCompraRequest;
use App\Http\Resources\Api\V1\CompraJson;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Compras por la API (E9-01, operaciones 3, 4 y 5). Registrar y anular usan
 * las mismas Actions de la caja (E4-01); la referencia del POS y su
 * restricción única evitan los duplicados (regla dura 3.7).
 */
class ComprasController extends ApiController
{
    /**
     * @throws ValidationException
     * @throws CompraEnConflicto
     */
    public function store(RegistrarCompraRequest $request, RegisterCompra $register): JsonResponse
    {
        $registro = $register($request->credencial(), $request->compra());

        return self::escrito(CompraJson::datos($registro->transaccion), $registro->repetida);
    }

    public function show(Request $request, string $referenciaExterna): JsonResponse
    {
        $user = $request->user();

        $compra = $user instanceof User
            ? Transaccion::query()
                ->where('comercio_id', $user->comercio_id)
                ->where('referencia_externa', NuevaCompra::normalizeReferencia($referenciaExterna))
                ->first()
            : null;

        return self::datos(CompraJson::datos($compra ?? throw ErrorApi::compraNoEncontrada()));
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function anular(AnularCompraRequest $request, AnnulCompraApi $annul, string $referenciaExterna): JsonResponse
    {
        $resultado = $annul($request->credencial(), $referenciaExterna, $request->motivo());

        return self::escrito(CompraJson::datos($resultado->recurso), $resultado->repetido, 200);
    }
}
