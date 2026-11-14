<?php

declare(strict_types=1);

use App\Jobs\Nocturno\NightlyJob;
use App\Jobs\Nocturno\ProcesosNocturnos;
use App\Jobs\SendAvisosNocturnos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly processes (E3-05), chained so each one starts after the previous.
Schedule::call(static function (): void {
    Bus::chain(ProcesosNocturnos::for(CarbonImmutable::today('America/Bogota')))
        ->onQueue(NightlyJob::queueName())
        ->dispatch();
})
    ->name('procesos-nocturnos')
    ->dailyAt(is_string(config('nocturno.hora')) ? config('nocturno.hora') : '01:30')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->onOneServer();

// Reruns a night by hand (a failed or missed run). Every process is idempotent.
Artisan::command('nocturno:correr {fecha? : Fecha de corte, AAAA-MM-DD; hoy si se omite}', function (): void {
    $fecha = $this->argument('fecha');
    $corte = is_string($fecha) ? CarbonImmutable::parse($fecha) : CarbonImmutable::today('America/Bogota');

    foreach (ProcesosNocturnos::for($corte) as $job) {
        Bus::dispatchSync($job);
    }

    $this->info("Procesos nocturnos del {$corte->toDateString()} terminados.");
})->purpose('Corre los procesos nocturnos de una fecha de corte');

// Avisos registrados de noche (E7-01): salen de mañana, no de madrugada.
Schedule::job(SendAvisosNocturnos::class)
    ->name('avisos-nocturnos')
    ->dailyAt(is_string(config('notificaciones.hora_avisos')) ? config('notificaciones.hora_avisos') : '08:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->when(static fn (): bool => config('queue.default') === 'redis');

// Sin worker permanente (hosting compartido, E1-03 regla 1): el cron vacía la cola de la
// base de datos cada minuto. Va al final para que las demás tareas del minuto se encolen antes.
$maxTime = config('queue.cron_worker.max_time_seconds');

Schedule::command('queue:work', [
    'database',
    '--queue=' . implode(',', [
        NightlyJob::queueName(),
        is_string(config('notificaciones.cola')) ? config('notificaciones.cola') : 'notificaciones',
        'default',
    ]),
    '--stop-when-empty',
    '--max-time=' . (is_int($maxTime) ? $maxTime : 50),
])
    ->name('cola-por-cron')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->when(static fn (): bool => config('queue.default') === 'database');
