<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Enums\TipoRegla;
use App\Models\Regla;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Solo puede regir un bono de registro a la vez (E3-06, decisión 6). Las
 * Actions del motor lo verifican al crear o reactivar un bono, con las filas
 * de los bonos bloqueadas, y el abono toma siempre el más antiguo vigente.
 */
class BonoRegistroVigente
{
    /**
     * El bono que rige en ese instante, si hay.
     */
    public function enFecha(CarbonInterface $instant): ?Regla
    {
        return Regla::inForceAt($instant)
            ->where('tipo', TipoRegla::BonoRegistro->value)
            ->oldest('id')
            ->first();
    }

    /**
     * Rechaza un bono que regiría desde `$start` mientras otro bono, de otro
     * código, sigue activo y sin cerrar después de esa fecha. Va dentro de la
     * transacción del guardado.
     *
     * @throws ValidationException
     */
    public function assertNingunOtro(string $codigo, CarbonImmutable $start): void
    {
        $query = Regla::query()->where('tipo', TipoRegla::BonoRegistro->value);
        $query->getQuery()->where('codigo', '<>', $codigo)->where('activa', true)->lockForUpdate();

        $otro = $query->get()->first(
            static fn (Regla $regla): bool => $regla->vigente_hasta === null || $regla->vigente_hasta->gt($start),
        );

        if ($otro !== null) {
            throw ValidationException::withMessages([
                'vigente_desde' => "Ya rige otro bono de registro («{$otro->codigo}»). "
                    . 'Desactívalo antes de activar uno nuevo.',
            ]);
        }
    }
}
