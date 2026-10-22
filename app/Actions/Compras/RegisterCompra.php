<?php

declare(strict_types=1);

namespace App\Actions\Compras;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Compras\AcreditarCompra;
use App\Compras\CompraEnConflicto;
use App\Compras\NuevaCompra;
use App\Compras\RegistroCompra;
use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\MotivoCambioNivel;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\Transaccion;
use App\Models\User;
use App\Puntos\Ledger;
use App\Reglas\PuntosDesglose;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Registers a purchase and grants its points in one database transaction:
 * the purchase, the accrual movements with their rule snapshots and the
 * synchronous promotion (hard rules 3.3, 3.4 and 3.6). If anything fails,
 * nothing stays.
 *
 * A purchase happens once (hard rule 3.7): the unique (comercio_id,
 * referencia_externa) stops a retry in the database. A retry with the same
 * customer and amount returns the existing purchase without new points;
 * with different data it is a conflict. A document with no customer leaves
 * the purchase pending, without points, until the customer registers.
 */
class RegisterCompra
{
    private readonly Ledger $ledger;

    private readonly AcreditarCompra $acreditar;

    private readonly RaiseNivelEfectivo $raise;

    public function __construct(Ledger $ledger, AcreditarCompra $acreditar, RaiseNivelEfectivo $raise)
    {
        $this->ledger = $ledger;
        $this->acreditar = $acreditar;
        $this->raise = $raise;
    }

    /**
     * @param  User|null  $user  who registers it; null only for the public API, authorized in E9-01
     *
     * @throws ValidationException
     * @throws CompraEnConflicto
     */
    public function __invoke(?User $user, NuevaCompra $compra): RegistroCompra
    {
        if ($user === null && $compra->canal !== CanalCompra::Api) {
            throw new LogicException('Only the public API registers purchases without a user.');
        }

        if ($user !== null) {
            Gate::forUser($user)->authorize('register', [Transaccion::class, $compra->comercioId, $compra->sedeId]);
        }

        $categoria = $this->validate($compra);

        try {
            return DB::transaction(fn (): RegistroCompra => $this->register($user, $compra, $categoria));
        } catch (UniqueConstraintViolationException) {
            return $this->retry($compra);
        }
    }

    private function register(?User $user, NuevaCompra $compra, string $categoria): RegistroCompra
    {
        $cliente = $this->cliente($compra);
        $nivelAntes = $cliente?->nivel_efectivo;

        $transaccion = Transaccion::query()->create([
            'cliente_id' => $cliente?->id,
            'tipo_documento_cliente' => $compra->tipoDocumento,
            'documento_cliente' => $compra->documento,
            'comercio_id' => $compra->comercioId,
            'sede_id' => $compra->sedeId,
            'monto' => $compra->monto,
            'referencia_externa' => $compra->referenciaExterna,
            'idempotency_key' => $compra->idempotencyKey,
            'canal' => $compra->canal,
            'estado' => $cliente === null ? EstadoTransaccion::Pendiente : EstadoTransaccion::Acreditada,
            'acreditada_at' => $cliente === null ? null : CarbonImmutable::now(),
            'registrada_por' => $user?->id,
        ]);

        if ($cliente === null) {
            return new RegistroCompra($transaccion, PuntosDesglose::none(), null, null, false);
        }

        $puntos = ($this->acreditar)($cliente, $transaccion, $categoria);
        $nivelDespues = ($this->raise)($cliente->id, MotivoCambioNivel::AscensoCompra)->nivel_efectivo;

        return new RegistroCompra($transaccion, $puntos, $nivelAntes, $nivelDespues, false);
    }

    /**
     * The customer of that document type and number, with their row locked.
     */
    private function cliente(NuevaCompra $compra): ?Cliente
    {
        $id = Cliente::query()
            ->where('tipo_documento', $compra->tipoDocumento->value)
            ->where('documento', $compra->documento)
            ->value('id');

        return is_int($id) ? $this->ledger->lockCliente($id) : null;
    }

    /**
     * @throws CompraEnConflicto
     */
    private function retry(NuevaCompra $compra): RegistroCompra
    {
        $existente = Transaccion::query()
            ->where('comercio_id', $compra->comercioId)
            ->where('referencia_externa', $compra->referenciaExterna)
            ->firstOrFail();

        $same = $existente->monto === $compra->monto
            && $existente->tipo_documento_cliente === $compra->tipoDocumento
            && $existente->documento_cliente === $compra->documento;

        if (! $same) {
            throw new CompraEnConflicto($existente);
        }

        $nivel = $existente->cliente_id === null
            ? null
            : Cliente::query()->findOrFail($existente->cliente_id)->nivel_efectivo;

        return new RegistroCompra($existente, PuntosDesglose::none(), $nivel, $nivel, true);
    }

    /**
     * @return string the merchant's category slug
     *
     * @throws ValidationException
     */
    private function validate(NuevaCompra $compra): string
    {
        $comercio = Comercio::query()->with('categoria')->find($compra->comercioId);
        $categoria = $comercio instanceof Comercio && $comercio->activo ? $comercio->categoria?->slug : null;
        $errors = array_filter([
            'monto' => $compra->monto > 0 ? null : 'El monto debe ser mayor que cero.',
            'referencia_externa' => $compra->referenciaExterna !== '' ? null : 'Falta la referencia de la compra.',
            'comercio_id' => $categoria !== null ? null : 'El comercio no está activo.',
            'sede_id' => $this->sedeError($compra),
        ], static fn (?string $error): bool => $error !== null);

        if ($errors !== [] || $categoria === null) {
            throw ValidationException::withMessages($errors);
        }

        return $categoria;
    }

    private function sedeError(NuevaCompra $compra): ?string
    {
        $sede = $compra->sedeId === null ? null : Sede::query()->find($compra->sedeId);

        if ($sede === null) {
            return $compra->canal->requiresSede() ? 'Elige la sede de la compra.' : null;
        }

        return $sede->activa && $sede->comercio_id === $compra->comercioId
            ? null
            : 'La sede no está activa o no es de este comercio.';
    }
}
