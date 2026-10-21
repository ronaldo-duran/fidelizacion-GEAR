<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base of the nightly jobs: they carry the cutoff date and process each
 * customer in its own transaction. A customer that fails is logged as an
 * alert and the rest go on; every process is idempotent, so the next run
 * picks it up.
 */
abstract class NightlyJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK = 500;

    /** Cutoff date, `Y-m-d`. Not readonly: the queue restores it on unserialize. */
    public string $corte;

    public function __construct(string $corte)
    {
        $this->corte = $corte;
        $this->onQueue(self::queueName());
    }

    public static function queueName(): string
    {
        $cola = config('nocturno.cola');

        return is_string($cola) ? $cola : 'nocturno';
    }

    /**
     * @param  iterable<mixed>  $values  ids as the database returns them
     * @return list<int>
     */
    protected static function ids(iterable $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    protected function corteDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->corte)->startOfDay();
    }

    /**
     * @param  list<int>  $clienteIds
     * @param  Closure(int): mixed  $process
     */
    protected function eachCliente(array $clienteIds, Closure $process): void
    {
        foreach (array_chunk($clienteIds, self::CHUNK) as $chunk) {
            foreach ($chunk as $clienteId) {
                try {
                    $process($clienteId);
                } catch (Throwable $exception) {
                    Log::channel('alertas')->error('Nightly process failed for a customer.', [
                        'job' => static::class,
                        'corte' => $this->corte,
                        'cliente_id' => $clienteId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }
}
