<?php

declare(strict_types=1);

use App\Enums\Nivel;
use App\Enums\TipoEventoCalificado;
use App\Models\EventoCalificado;
use App\Niveles\EventoNivelCalculator;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->today = CarbonImmutable::parse('2026-10-17');
    $this->rules = config('niveles.event_rules');
    $this->evento = static fn (string $tipo, string $desde, string $hasta): EventoCalificado => app(
        EventoCalificado::class,
    )->forceFill([
        'tipo' => TipoEventoCalificado::from($tipo),
        'vigente_desde' => $desde,
        'vigente_hasta' => $hasta,
    ]);
});

it('ignores events that have not started or already ended', function (): void {
    $eventos = [
        ($this->evento)('compra_inmueble', '2026-11-01', '2027-11-01'),
        ($this->evento)('compra_inmueble', '2025-10-01', '2026-10-16'),
    ];

    expect(app(EventoNivelCalculator::class)->porEventos($eventos, $this->rules, $this->today))->toBe([null, null]);
});

it('takes the highest level across event types', function (): void {
    $eventos = [
        ($this->evento)('matricula_colegio', '2026-01-01', '2027-06-30'),
        ($this->evento)('compra_inmueble', '2026-01-01', '2027-01-01'),
    ];

    [$nivel, $hasta] = app(EventoNivelCalculator::class)->porEventos($eventos, $this->rules, $this->today);

    expect($nivel)->toBe(Nivel::Diamante)->and($hasta?->toDateString())->toBe('2027-01-01');
});

it('guarantees the school level until the count falls below its minimum', function (): void {
    $eventos = [
        ($this->evento)('matricula_colegio', '2026-01-01', '2027-06-30'),
        ($this->evento)('matricula_colegio', '2026-01-01', '2026-12-15'),
        ($this->evento)('matricula_colegio', '2026-01-01', '2027-03-01'),
    ];

    [$nivel, $hasta] = app(EventoNivelCalculator::class)->porEventos($eventos, $this->rules, $this->today);

    expect($nivel)->toBe(Nivel::Diamante)->and($hasta?->toDateString())->toBe('2026-12-15');
});

it('counts the last day of an event', function (): void {
    $eventos = [($this->evento)('matricula_colegio', '2026-01-01', '2026-10-17')];

    [$nivel] = app(EventoNivelCalculator::class)->porEventos($eventos, $this->rules, $this->today);

    expect($nivel)->toBe(Nivel::Oro);
});
