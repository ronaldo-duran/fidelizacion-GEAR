<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\CredencialQr;

/**
 * 5. Deletes QR credentials that expired before the cutoff. They live for
 * seconds and hold no history: a benefit redemption keeps its own record
 * (E5-02).
 */
class PurgeCredenciales extends NightlyJob
{
    public function handle(): void
    {
        CredencialQr::query()->toBase()
            ->where('vence_at', '<', $this->corteDate()->toDateTimeString())
            ->delete();
    }
}
