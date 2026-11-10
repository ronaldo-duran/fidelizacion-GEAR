<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Actions\Compras\AnnulCompra;
use App\Api\ErrorApi;
use App\Api\ResultadoApi;
use App\Compras\NuevaCompra;
use App\Enums\EstadoTransaccion;
use App\Models\Transaccion;
use App\Models\User;
use App\Puntos\PlazoReversa;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Anula por la API una compra del comercio de la credencial (E9-01, regla 10
 * y decisión 4): solo compras de la API, con el plazo de la reversa del libro
 * (E3-02), con la anulación de la caja (`AnnulCompra`). Anular una compra ya
 * anulada es un reintento del POS: responde la misma compra (regla 7).
 */
class AnnulCompraApi
{
    private readonly AnnulCompra $annul;

    public function __construct(AnnulCompra $annul)
    {
        $this->annul = $annul;
    }

    /**
     * @return ResultadoApi<Transaccion>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, string $referenciaExterna, string $motivo): ResultadoApi
    {
        // Una compra de otro comercio no existe para esta credencial (regla 2).
        $transaccion = Transaccion::query()
            ->where('comercio_id', $user->comercio_id)
            ->where('referencia_externa', NuevaCompra::normalizeReferencia($referenciaExterna))
            ->first() ?? throw ErrorApi::compraNoEncontrada();

        Gate::forUser($user)->authorize('annul', $transaccion);

        if ($transaccion->estado === EstadoTransaccion::Anulada) {
            return new ResultadoApi($transaccion, true);
        }

        if (PlazoReversa::vencido($transaccion->acreditada_at ?? $transaccion->created_at)) {
            throw new ErrorApi(422, 'fuera_de_plazo', 'La compra ya está fuera del plazo para anularla.');
        }

        try {
            return new ResultadoApi(($this->annul)($user, $transaccion->id, $motivo), false);
        } catch (ValidationException $error) {
            // Dos anulaciones a la vez: la segunda ve la reversa de la primera.
            $actual = $transaccion->refresh();

            if ($actual->estado === EstadoTransaccion::Anulada) {
                return new ResultadoApi($actual, true);
            }

            throw $error;
        }
    }
}
