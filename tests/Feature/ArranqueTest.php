<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('responde en la página de inicio', function (): void {
    $this->get('/')->assertOk();
});

it('muestra el login del panel administrativo', function (): void {
    $this->get('/admin/login')->assertOk();
});

it('usa la hora de Bogotá en la aplicación', function (): void {
    expect(config('app.timezone'))->toBe('America/Bogota')
        ->and(now()->tzName)->toBe('America/Bogota');
});

it('usa la hora de Bogotá en la sesión de PostgreSQL', function (): void {
    expect(DB::selectOne('show timezone')->TimeZone)->toBe('America/Bogota');
})->skip(
    fn (): bool => DB::getDriverName() !== 'pgsql',
    'SQL Server no tiene zona horaria de sesión: la hora la pone la aplicación.',
);

it('guarda y lee fechas en hora de Bogotá en cualquier motor', function (): void {
    $this->travelTo(now()->setDateTime(2026, 10, 31, 23, 30));

    $usuario = User::factory()->create();

    expect($usuario->refresh()->created_at?->format('Y-m-d H:i'))->toBe('2026-10-31 23:30');
});
