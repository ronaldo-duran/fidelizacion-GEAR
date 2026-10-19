<?php

declare(strict_types=1);

namespace App\Reglas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A purchase as the points calculator sees it: no database row behind it,
 * so a simulation and a real registration feed it the same way. The date is
 * kept in the application time zone (America/Bogota, hard rule 3.9) because
 * day and month caps and the weekday condition depend on it.
 */
final class CompraData
{
    public readonly int $monto;

    public readonly string $categoriaSlug;

    public readonly int $comercioId;

    public readonly ?int $sedeId;

    public readonly CarbonImmutable $fecha;

    public function __construct(
        int $monto,
        string $categoriaSlug,
        int $comercioId,
        ?int $sedeId,
        CarbonInterface $fecha,
    ) {
        $this->monto = $monto;
        $this->categoriaSlug = $categoriaSlug;
        $this->comercioId = $comercioId;
        $this->sedeId = $sedeId;
        $this->fecha = CarbonImmutable::instance($fecha)->setTimezone(date_default_timezone_get());
    }

    /**
     * Value of a whitelisted condition field (`resources/schemas/regla.json`).
     */
    public function fact(string $campo): int|string|null
    {
        return match ($campo) {
            'monto' => $this->monto,
            'categoria_slug' => $this->categoriaSlug,
            'comercio_id' => $this->comercioId,
            'sede_id' => $this->sedeId,
            'dia_semana' => $this->fecha->isoWeekday(),
            'hora' => $this->fecha->hour,
            default => null,
        };
    }

    public function dayKey(): string
    {
        return $this->fecha->format('Y-m-d');
    }

    public function monthKey(): string
    {
        return $this->fecha->format('Y-m');
    }
}
