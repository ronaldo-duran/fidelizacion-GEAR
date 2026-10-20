<?php

declare(strict_types=1);

namespace App\Reglas;

/**
 * Checks the structured conditions of a bonus rule against a purchase. Only
 * the whitelisted fields and operators of `resources/schemas/regla.json`
 * exist here; anything else never holds (hard rule 3.8). Nothing is parsed
 * or evaluated as code.
 */
class CondicionesEvaluator
{
    /**
     * Every condition must hold (AND).
     *
     * @param  array<array-key, mixed>  $condiciones
     */
    public function holdAll(array $condiciones, CompraData $compra): bool
    {
        foreach ($condiciones as $condicion) {
            if (! is_array($condicion) || ! $this->holds($condicion, $compra)) {
                return false;
            }
        }

        return $condiciones !== [];
    }

    /**
     * @param  array<array-key, mixed>  $condicion
     */
    private function holds(array $condicion, CompraData $compra): bool
    {
        $campo = $condicion['campo'] ?? null;
        $fact = is_string($campo) ? $compra->fact($campo) : null;
        $valor = $condicion['valor'] ?? null;

        return match ($condicion['operador'] ?? null) {
            'igual' => $this->same($fact, $valor),
            'distinto' => ! $this->same($fact, $valor),
            'en' => is_array($valor) && $this->inList($fact, $valor),
            default => $this->compare($condicion['operador'] ?? null, $fact, $valor),
        };
    }

    /**
     * Ordering operators, only between whole numbers.
     */
    private function compare(mixed $operador, int|string|null $fact, mixed $valor): bool
    {
        if (! is_int($fact) || ! is_int($valor)) {
            return false;
        }

        return match ($operador) {
            'mayor' => $fact > $valor,
            'mayor_igual' => $fact >= $valor,
            'menor' => $fact < $valor,
            'menor_igual' => $fact <= $valor,
            default => false,
        };
    }

    /**
     * Ids may arrive as text from the JSON editor, so equality compares the
     * text form. A missing fact (a purchase without sede) never equals.
     */
    private function same(int|string|null $fact, mixed $valor): bool
    {
        return $fact !== null && (is_int($valor) || is_string($valor)) && (string) $fact === (string) $valor;
    }

    /**
     * @param  array<array-key, mixed>  $valores
     */
    private function inList(int|string|null $fact, array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($this->same($fact, $valor)) {
                return true;
            }
        }

        return false;
    }
}
