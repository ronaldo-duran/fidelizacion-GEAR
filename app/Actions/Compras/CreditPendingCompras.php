<?php

declare(strict_types=1);

namespace App\Actions\Compras;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Compras\AcreditarCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\MotivoCambioNivel;
use App\Models\Comercio;
use App\Models\Transaccion;
use App\Puntos\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Credits a newly registered customer's pending purchases, found by their
 * document type and number (hard rule 3.10), in purchase order and with the
 * rules of each purchase date (hard rule 3.3). The registration (E8-02) calls
 * it in its own transaction.
 *
 * Each purchase is claimed with an UPDATE conditioned on `estado =
 * pendiente`; if it affects no row, another run already took it and it is
 * skipped, so a pending purchase is never credited twice. Points that already
 * expired leave with that night's expiry (E3-05).
 */
class CreditPendingCompras
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
     * @return list<Transaccion> the purchases credited by this run
     */
    public function __invoke(int $clienteId): array
    {
        return DB::transaction(function () use ($clienteId): array {
            $cliente = $this->ledger->lockCliente($clienteId);
            $pendientes = Transaccion::query()
                ->where('tipo_documento_cliente', $cliente->tipo_documento->value)
                ->where('documento_cliente', $cliente->documento)
                ->where('estado', EstadoTransaccion::Pendiente->value)
                ->oldest()
                ->oldest('id')
                ->get();
            $acreditadas = [];

            foreach ($pendientes as $transaccion) {
                if (! $this->claim($transaccion, $cliente->id)) {
                    continue;
                }

                $comercio = Comercio::query()->with('categoria')->findOrFail($transaccion->comercio_id);
                ($this->acreditar)($cliente, $transaccion->refresh(), $comercio->categoria->slug ?? '');
                $acreditadas[] = $transaccion;
            }

            if ($acreditadas !== []) {
                ($this->raise)($cliente->id, MotivoCambioNivel::AscensoCompra);
            }

            return $acreditadas;
        });
    }

    private function claim(Transaccion $transaccion, int $clienteId): bool
    {
        $claimed = Transaccion::query()->toBase()
            ->where('id', $transaccion->id)
            ->where('estado', EstadoTransaccion::Pendiente->value)
            ->update([
                'estado' => EstadoTransaccion::Acreditada->value,
                'cliente_id' => $clienteId,
                'acreditada_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $claimed === 1;
    }
}
