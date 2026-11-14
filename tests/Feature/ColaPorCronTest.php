<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

// Cola procesada por el cron en el hosting compartido (E1-03, regla 1): sin worker
// permanente, el scheduler vacía la cola de la base de datos cada minuto.

beforeEach(function (): void {
    // Se lee al llamarlo: cada prueba cambia la conexión de la cola antes de buscar la tarea.
    $this->colaPorCron = static fn (): ?Event => collect(app(Schedule::class)->events())
        ->first(static fn (Event $event): bool => $event->description === 'cola-por-cron');
});

it('works the database queue every minute without overlapping', function (): void {
    config(['queue.default' => 'database']);
    $event = ($this->colaPorCron)();

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('* * * * *')
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->filtersPass(app()))->toBeTrue()
        ->and($event?->command)->toContain('queue:work')
        ->and($event?->command)->toContain('database')
        ->and($event?->command)->toContain('--queue=nocturno,notificaciones,default')
        ->and($event?->command)->toContain('--stop-when-empty');
});

it('stops before the next minute and before a job is retried', function (): void {
    $maxTime = config('queue.cron_worker.max_time_seconds');
    $retryAfter = config('queue.connections.database.retry_after');

    expect(($this->colaPorCron)()?->command)->toContain("--max-time={$maxTime}")
        ->and($maxTime)->toBeInt()->toBeLessThan(60)
        ->and($maxTime)->toBeLessThan($retryAfter);
});

it('leaves the queue to Horizon when it runs on Redis', function (): void {
    config(['queue.default' => 'redis']);

    expect(($this->colaPorCron)()?->filtersPass(app()))->toBeFalse();
});
