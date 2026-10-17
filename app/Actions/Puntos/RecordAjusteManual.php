<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\User;
use App\Puntos\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a mistake with a manual adjustment: it carries no rule, but always
 * a reason and the user who made it. A positive adjustment expires like an
 * accrual, with the validity of the accrual rule in force today (E3-05).
 */
class RecordAjusteManual
{
    private readonly Ledger $ledger;

    public function __construct(Ledger $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, int $clienteId, int $puntos, string $motivo): MovimientoPuntos
    {
        Gate::forUser($user)->authorize('recordAjusteManual', MovimientoPuntos::class);

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo del ajuste.']);
        }

        if ($puntos === 0) {
            throw ValidationException::withMessages(['puntos' => 'El ajuste no puede ser de cero puntos.']);
        }

        $vigenciaHasta = $puntos > 0 ? $this->vigenciaHasta() : null;

        return DB::transaction(fn (): MovimientoPuntos => $this->ledger->append(
            $this->ledger->lockCliente($clienteId),
            [
                'tipo' => TipoMovimiento::AjusteManual,
                'puntos' => $puntos,
                'motivo' => $motivo,
                'usuario_id' => $user->id,
                'vigencia_hasta' => $vigenciaHasta,
            ],
        ));
    }

    /**
     * @throws ValidationException
     */
    private function vigenciaHasta(): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $regla = Regla::inForceAt($now)
            ->where('tipo', TipoRegla::Acumulacion->value)
            ->first();
        $meses = $regla?->definicion['vigencia_puntos_meses'] ?? null;

        if (! is_int($meses)) {
            throw ValidationException::withMessages([
                'puntos' => 'No hay una regla de acumulación vigente para fijar el vencimiento de estos puntos.',
            ]);
        }

        return $now->startOfDay()->addMonths($meses);
    }
}
