<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Models\Auditoria;
use App\Models\Regla;
use App\Models\User;

/**
 * Records every rule save in `auditorias` with the author, the date and the
 * previous and new definition (E3-01, audit acceptance criterion).
 */
class ReglaAuditLogger
{
    public function log(User $author, string $evento, ?Regla $previous, Regla $new): void
    {
        Auditoria::query()->create([
            'usuario_id' => $author->id,
            'evento' => $evento,
            'modelo' => Regla::class,
            'modelo_id' => $new->id,
            'antes' => $previous === null ? null : $this->snapshot($previous),
            'despues' => $this->snapshot($new),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Regla $regla): array
    {
        return [
            'codigo' => $regla->codigo,
            'version' => $regla->version,
            'vigente_desde' => $regla->vigente_desde->toDateTimeString(),
            'activa' => $regla->activa,
            'definicion' => $regla->definicion,
        ];
    }
}
