<?php

declare(strict_types=1);

use App\Support\FormatoColombiano;
use Illuminate\Support\Carbon;

it('formatea pesos colombianos sin centavos', function (): void {
    expect(FormatoColombiano::pesos(0))->toBe('$0')
        ->and(FormatoColombiano::pesos(1160))->toBe('$1.160')
        ->and(FormatoColombiano::pesos(85000))->toBe('$85.000')
        ->and(FormatoColombiano::pesos(1000000))->toBe('$1.000.000');
});

it('formatea puntos con separador de miles', function (): void {
    expect(FormatoColombiano::puntos(0))->toBe('0 pts')
        ->and(FormatoColombiano::puntos(1160))->toBe('1.160 pts')
        ->and(FormatoColombiano::puntos(3000))->toBe('3.000 pts');
});

it('formatea la hora en estilo colombiano de 12 horas', function (): void {
    expect(FormatoColombiano::hora(Carbon::create(2026, 10, 17, 19, 42)))->toBe('7:42 p. m.')
        ->and(FormatoColombiano::hora(Carbon::create(2026, 10, 17, 7, 5)))->toBe('7:05 a. m.')
        ->and(FormatoColombiano::hora(Carbon::create(2026, 10, 17, 0, 0)))->toBe('12:00 a. m.')
        ->and(FormatoColombiano::hora(Carbon::create(2026, 10, 17, 12, 0)))->toBe('12:00 p. m.');
});
