<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\CodigoVerificacion;

/**
 * 5. Deletes verification codes that expired before the cutoff. They live for
 * minutes and keep a phone and an IP, so they are not kept longer than needed
 * (data minimization, Ley 1581). The send limits live in the cache, not here.
 */
class PurgeCodigosVerificacion extends NightlyJob
{
    public function handle(): void
    {
        CodigoVerificacion::query()->toBase()
            ->where('vence_at', '<', $this->corteDate()->toDateTimeString())
            ->delete();
    }
}
