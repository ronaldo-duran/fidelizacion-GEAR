<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Actions\Beneficios\CheckBeneficioCredencial;
use App\Actions\Beneficios\RedeemBeneficio;
use App\Actions\Cajeros\FindCliente;
use App\Api\EntregaApi;
use App\Api\ErrorApi;
use App\Api\RespuestaError;
use App\Api\ResultadoApi;
use App\Beneficios\EntregaBeneficio;
use App\Beneficios\EntregaRechazada;
use App\Credenciales\TokenSigner;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\Redencion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Entrega un beneficio por la API (E9-01, operación 7) con la entrega de la
 * caja (`RedeemBeneficio`, E5-02), que guarda el valor otorgado (regla dura
 * 3.11) y no toca los puntos (regla dura 3.1).
 *
 * La llave de idempotencia es la `referencia` de la redención, única en la
 * base (regla dura 3.7):
 *
 * - la misma llave con la misma entrega devuelve la redención ya hecha;
 * - con otro beneficio, otro cliente, otro código, otro monto de la cuenta u
 *   otro soporte es un conflicto (409);
 * - una llave de otro comercio también es un conflicto, sin decir de quién es.
 */
class RedeemBeneficioApi
{
    private readonly CheckBeneficioCredencial $check;

    private readonly FindCliente $findCliente;

    private readonly RedeemBeneficio $redeem;

    private readonly TokenSigner $signer;

    public function __construct(
        CheckBeneficioCredencial $check,
        FindCliente $findCliente,
        RedeemBeneficio $redeem,
        TokenSigner $signer,
    ) {
        $this->check = $check;
        $this->findCliente = $findCliente;
        $this->redeem = $redeem;
        $this->signer = $signer;
    }

    /**
     * @return ResultadoApi<Redencion>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws EntregaRechazada
     */
    public function __invoke(User $user, EntregaApi $entrega): ResultadoApi
    {
        $existente = Redencion::query()->where('referencia', $entrega->llave)->first();

        if ($existente !== null) {
            return new ResultadoApi($this->comoRepeticion($user, $existente, $entrega), true);
        }

        $redencion = $this->entregar($user, $entrega);

        if ($redencion->wasRecentlyCreated) {
            return new ResultadoApi($redencion, false);
        }

        // Otro intento con la misma llave ganó mientras este esperaba el bloqueo.
        return new ResultadoApi($this->comoRepeticion($user, $redencion, $entrega), true);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws EntregaRechazada
     */
    private function entregar(User $user, EntregaApi $entrega): Redencion
    {
        [$clienteId, $beneficioId, $credencialId] = $entrega->porCodigo()
            ? $this->porCodigo($user, (string) $entrega->codigo)
            : $this->porDocumento($user, $entrega);

        try {
            return ($this->redeem)($user, new EntregaBeneficio(
                $clienteId,
                $beneficioId,
                $credencialId,
                $entrega->montoCuenta,
                $entrega->soporte,
                $entrega->llave,
            ));
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(self::camposApi($error));
        } catch (AuthorizationException $error) {
            $deOtroComercio = Redencion::query()->toBase()->where('referencia', $entrega->llave)->exists();

            throw $deOtroComercio ? ErrorApi::redencionEnConflicto() : $error;
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int|null}
     *
     * @throws AuthorizationException
     * @throws EntregaRechazada
     */
    private function porCodigo(User $user, string $codigo): array
    {
        $chequeo = ($this->check)($user, $codigo);

        if (! $chequeo->esValido() || $chequeo->cliente === null || $chequeo->beneficio === null) {
            throw new EntregaRechazada($chequeo);
        }

        return [$chequeo->cliente->id, $chequeo->beneficio->id, $chequeo->credencialId];
    }

    /**
     * @return array{0: int, 1: int, 2: null}
     *
     * @throws ValidationException
     */
    private function porDocumento(User $user, EntregaApi $entrega): array
    {
        $cliente = $entrega->tipoDocumento === null || $entrega->documento === null
            ? null
            : ($this->findCliente)($user, $entrega->tipoDocumento, $entrega->documento);

        if ($cliente === null) {
            throw ErrorApi::clienteNoRegistrado();
        }

        return [$cliente->id, (int) $entrega->beneficioId, null];
    }

    /**
     * La redención que ya existe con esa llave, si la entrega pedida es la
     * misma. Si no, un conflicto que no escribe nada.
     */
    private function comoRepeticion(User $user, Redencion $existente, EntregaApi $entrega): Redencion
    {
        $misma = $existente->comercio_id === $user->comercio_id
            && self::mismosDatos($existente, $entrega)
            && ($entrega->porCodigo()
                ? $this->mismoCodigo($existente, (string) $entrega->codigo)
                : $this->mismoDocumento($existente, $entrega));

        return $misma ? $existente : throw ErrorApi::redencionEnConflicto();
    }

    /**
     * El monto de la cuenta y el soporte que se guardaron. Solo cuentan si la
     * redención los guardó: en un beneficio de monto fijo el monto que mande
     * el POS no se usa, y sin soporte exigido el soporte tampoco.
     */
    private static function mismosDatos(Redencion $existente, EntregaApi $entrega): bool
    {
        $monto = $existente->monto_cuenta_cop === null || $existente->monto_cuenta_cop === $entrega->montoCuenta;
        $soporte = $existente->soporte === null || $existente->soporte === trim((string) $entrega->soporte);

        return $monto && $soporte;
    }

    private function mismoCodigo(Redencion $existente, string $codigo): bool
    {
        $credencial = $existente->credencial_qr_id === null
            ? null
            : CredencialQr::query()->find($existente->credencial_qr_id);

        if ($credencial === null) {
            return false;
        }

        $codigo = trim($codigo);

        if (str_contains($codigo, '.')) {
            return ($this->signer->read($codigo)['n'] ?? null) === $credencial->nonce;
        }

        return mb_strtoupper(str_replace(' ', '', $codigo)) === $credencial->codigo_corto;
    }

    private function mismoDocumento(Redencion $existente, EntregaApi $entrega): bool
    {
        $clienteId = $entrega->tipoDocumento === null || $entrega->documento === null
            ? null
            : Cliente::query()
                ->where('tipo_documento', $entrega->tipoDocumento->value)
                ->where('documento', Cliente::normalizeDocumento($entrega->documento))
                ->value('id');

        return $existente->credencial_qr_id === null
            && $existente->beneficio_id === $entrega->beneficioId
            && $existente->cliente_id === $clienteId;
    }

    /**
     * Los errores de la entrega con los nombres de campo de la API.
     *
     * @return array<string, list<string>>
     */
    private static function camposApi(ValidationException $error): array
    {
        $campos = [];

        foreach (RespuestaError::campos($error) as $campo => $mensajes) {
            $campos[$campo === 'monto_cuenta' ? 'monto_cuenta_cop' : $campo] = $mensajes;
        }

        return $campos;
    }
}
