<?php

declare(strict_types=1);

use App\Jobs\Nocturno\NightlyJob;
use App\Jobs\Nocturno\ProcesosNocturnos;
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

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->when(static fn (): bool => config('queue.default') === 'redis');
