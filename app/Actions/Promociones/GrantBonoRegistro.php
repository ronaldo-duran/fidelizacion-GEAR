<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Actions\Puntos\RecordPromocion;
use App\Enums\MotivoCambioNivel;
use App\Enums\TipoRegla;
use App\Models\MovimientoPuntos;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use App\Reglas\BonoRegistroVigente;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Abona el bono de registro que rige al inscribirse el cliente (E3-06). El
 * registro (E8-02) la llama dentro de su propia transacción.
 *
 * Los puntos entran por el libro como un movimiento `promocion` con el
 * snapshot de la regla (reglas duras 3.3 y 3.4) y pueden subir el nivel en
 * la misma petición (regla dura 3.6). Que se abone una sola vez lo garantiza
 * la restricción única de `promociones_otorgadas` (regla dura 3.7): un abono
 * repetido choca en la base de datos, se deshace completo y devuelve null.
 *
 * Sin regla de acumulación vigente no se sabe cuándo vencerían los puntos: no
 * se abona y queda un aviso en el log, pero el registro del cliente sigue.
 * Guardar el bono ya exige esa regla; esto cubre un hueco posterior.
 */
class GrantBonoRegistro
{
    private readonly BonoRegistroVigente $bonos;

    private readonly RecordPromocion $record;

    private readonly RaiseNivelEfectivo $raise;

    public function __construct(BonoRegistroVigente $bonos, RecordPromocion $record, RaiseNivelEfectivo $raise)
    {
        $this->bonos = $bonos;
        $this->record = $record;
        $this->raise = $raise;
    }

    /**
     * @return MovimientoPuntos|null el movimiento abonado, o null si no rige un bono, no hay regla de
     *                               acumulación o ya se abonó
     */
    public function __invoke(int $clienteId): ?MovimientoPuntos
    {
        $now = CarbonImmutable::now();
        $bono = $this->bonos->enFecha($now);

        if ($bono === null) {
            return null;
        }

        $puntos = $bono->definicion['puntos'] ?? null;

        if (! is_int($puntos)) {
            throw new LogicException("The registration bonus {$bono->codigo} has no points.");
        }

        $vigenciaHasta = $this->vigenciaHasta($now);

        if ($vigenciaHasta === null) {
            Log::warning('Bono de registro sin abonar: no hay regla de acumulación vigente.', [
                'cliente_id' => $clienteId,
                'codigo' => $bono->codigo,
            ]);

            return null;
        }

        try {
            return DB::transaction(function () use ($clienteId, $bono, $puntos, $vigenciaHasta): MovimientoPuntos {
                $movimiento = ($this->record)($clienteId, null, $puntos, $bono, $vigenciaHasta);

                PromocionOtorgada::query()->create([
                    'cliente_id' => $clienteId,
                    'codigo' => $bono->codigo,
                    'ocurrencia' => 1,
                    'movimiento_id' => $movimiento->id,
                ]);

                ($this->raise)($clienteId, MotivoCambioNivel::AscensoPromocion);

                return $movimiento;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Los puntos del bono vencen como los de una compra: a los meses de la
     * regla de acumulación vigente (E3-05). Null si no rige ninguna.
     */
    private function vigenciaHasta(CarbonImmutable $now): ?CarbonImmutable
    {
        $acumulacion = Regla::inForceAt($now)->where('tipo', TipoRegla::Acumulacion->value)->first();
        $meses = $acumulacion?->definicion['vigencia_puntos_meses'] ?? null;

        if (! is_int($meses)) {
            return null;
        }

        return $now->startOfDay()->addMonths($meses);
    }
}
