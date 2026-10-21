<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Program levels, in ascending order.
 */
enum Nivel: string implements HasLabel
{
    case Bronce = 'bronce';
    case Plata = 'plata';
    case Oro = 'oro';
    case Diamante = 'diamante';

    /**
     * The highest of the given levels; empty sources are skipped and nothing
     * at all means Bronce.
     */
    public static function highest(?self ...$niveles): self
    {
        $highest = self::Bronce;

        foreach ($niveles as $nivel) {
            if ($nivel !== null && $nivel->isAbove($highest)) {
                $highest = $nivel;
            }
        }

        return $highest;
    }

    public function rank(): int
    {
        return match ($this) {
            self::Bronce => 0,
            self::Plata => 1,
            self::Oro => 2,
            self::Diamante => 3,
        };
    }

    public function isAbove(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
