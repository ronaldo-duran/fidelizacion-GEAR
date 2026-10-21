<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use App\Enums\TipoEventoCalificado;
use App\Models\EventoCalificado;
use Carbon\CarbonImmutable;

/**
 * The level a customer's qualifying events give on a given day, and the date
 * up to which that level is guaranteed. Pure: the caller hands in the events
 * and the event rules (`config/niveles.php`). Several events of one type
 * never add up levels.
 */
class EventoNivelCalculator
{
    /**
     * @param  iterable<EventoCalificado>  $eventos
     * @param  array<array-key, mixed>  $eventRules
     * @return array{0: Nivel|null, 1: CarbonImmutable|null}
     */
    public function porEventos(iterable $eventos, array $eventRules, CarbonImmutable $day): array
    {
        $endDates = ['compra_inmueble' => [], 'matricula_colegio' => []];

        foreach ($eventos as $evento) {
            if ($evento->isVigenteOn($day)) {
                $endDates[$evento->tipo->value][] = $evento->vigente_hasta;
            }
        }

        $best = [null, null];

        foreach ($endDates as $tipo => $dates) {
            [$nivel, $hasta] = $this->levelOf(TipoEventoCalificado::from($tipo), $dates, $eventRules);
            $best = $this->better($best, $nivel, $hasta);
        }

        return $best;
    }

    /**
     * Property purchases give their level until the latest end date. School
     * enrollments give the level of their count, guaranteed until the count
     * would fall below that level's minimum.
     *
     * @param  list<CarbonImmutable>  $dates
     * @param  array<array-key, mixed>  $eventRules
     * @return array{0: Nivel|null, 1: CarbonImmutable|null}
     */
    private function levelOf(TipoEventoCalificado $tipo, array $dates, array $eventRules): array
    {
        if ($dates === []) {
            return [null, null];
        }

        usort($dates, static fn (CarbonImmutable $first, CarbonImmutable $second): int => $second <=> $first);
        $rules = is_array($eventRules[$tipo->value] ?? null) ? $eventRules[$tipo->value] : [];

        return match ($tipo) {
            TipoEventoCalificado::CompraInmueble => $this->better([null, null], $this->nivelOf($rules), $dates[0]),
            TipoEventoCalificado::MatriculaColegio => $this->levelByCount($rules, $dates),
        };
    }

    /**
     * @param  array<array-key, mixed>  $rules
     * @param  list<CarbonImmutable>  $dates  end dates, latest first
     * @return array{0: Nivel|null, 1: CarbonImmutable|null}
     */
    private function levelByCount(array $rules, array $dates): array
    {
        $best = [null, null];
        $levels = is_array($rules['levels_by_count'] ?? null) ? $rules['levels_by_count'] : [];

        foreach ($levels as $level) {
            $min = is_array($level) ? ($level['min_enrollments'] ?? null) : null;

            if (is_array($level) && is_int($min) && $min >= 1 && count($dates) >= $min) {
                $best = $this->better($best, $this->nivelOf($level), $dates[$min - 1]);
            }
        }

        return $best;
    }

    /**
     * @param  array<array-key, mixed>  $rule
     */
    private function nivelOf(array $rule): ?Nivel
    {
        return is_string($rule['nivel'] ?? null) ? Nivel::tryFrom($rule['nivel']) : null;
    }

    /**
     * @param  array{0: Nivel|null, 1: CarbonImmutable|null}  $current
     * @return array{0: Nivel|null, 1: CarbonImmutable|null}
     */
    private function better(array $current, ?Nivel $nivel, ?CarbonImmutable $hasta): array
    {
        [$currentNivel, $currentHasta] = $current;

        if ($nivel === null || $hasta === null) {
            return $current;
        }

        $wins = $currentNivel === null
            || $nivel->isAbove($currentNivel)
            || ($nivel === $currentNivel && $currentHasta !== null && $hasta->gt($currentHasta));

        return $wins ? [$nivel, $hasta] : $current;
    }
}
