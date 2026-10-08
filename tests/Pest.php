<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso de prueba base
|--------------------------------------------------------------------------
|
| Las pruebas de Feature corren contra PostgreSQL y reinician la base en cada
| prueba. Las de Unit no tocan base de datos.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
