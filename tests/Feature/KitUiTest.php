<?php

declare(strict_types=1);

it('muestra el kit de interfaz fuera de producción', function (): void {
    $this->get('/_ui')
        ->assertOk()
        ->assertSee('Catálogo de las primitivas', false);
});

it('oculta el kit de interfaz en producción', function (): void {
    $this->app['env'] = 'production';

    $this->get('/_ui')->assertNotFound();
});

it('sirve la página sin conexión de la PWA', function (): void {
    $this->get('/sin-conexion')
        ->assertOk()
        ->assertSee('No hay conexión', false);
});

it('publica el manifest y el service worker de la PWA', function (): void {
    expect(public_path('manifest.webmanifest'))->toBeFile()
        ->and(public_path('sw.js'))->toBeFile();
});
