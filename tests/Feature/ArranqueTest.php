<?php

use Illuminate\Support\Facades\DB;

it('responde en la página de inicio', function () {
    $this->get('/')->assertOk();
});

it('muestra el login del panel administrativo', function () {
    $this->get('/admin/login')->assertOk();
});

it('usa la hora de Bogotá en la aplicación y en la base de datos', function () {
    expect(config('app.timezone'))->toBe('America/Bogota')
        ->and(now()->tzName)->toBe('America/Bogota')
        ->and(DB::selectOne('show timezone')->TimeZone)->toBe('America/Bogota');
});
