<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a purchase was registered from: the cashier screen (E4-02), the bulk
 * upload (E4-03) or the public API (E9-01).
 */
enum CanalCompra: string implements HasLabel
{
    case Cajero = 'cajero';
    case CargaMasiva = 'carga_masiva';
    case Api = 'api';

    /**
     * The cashier and the bulk upload always know the branch; a POS may not.
     */
    public function requiresSede(): bool
    {
        return $this !== self::Api;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Cajero => 'Cajero',
            self::CargaMasiva => 'Carga masiva',
            self::Api => 'API',
        };
    }
}
