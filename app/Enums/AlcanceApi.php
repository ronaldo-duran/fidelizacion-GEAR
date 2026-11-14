<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Los alcances de una credencial de la API pública (E9-01, regla 3). Cada
 * operación exige uno; sin él la API responde 403. Son las `abilities` del
 * token de Sanctum. La entrega por documento exige además su propio alcance,
 * para que el comercio la active a propósito.
 */
enum AlcanceApi: string implements HasLabel
{
    case ClientesLeer = 'clientes:leer';
    case ComprasRegistrar = 'compras:registrar';
    case ComprasAnular = 'compras:anular';
    case BeneficiosRedimir = 'beneficios:redimir';
    case BeneficiosRedimirDocumento = 'beneficios:redimir_documento';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClientesLeer => 'Consultar clientes y sus beneficios',
            self::ComprasRegistrar => 'Registrar y consultar compras',
            self::ComprasAnular => 'Anular compras',
            self::BeneficiosRedimir => 'Validar códigos y entregar beneficios',
            self::BeneficiosRedimirDocumento => 'Entregar beneficios por documento, sin código',
        };
    }

    /**
     * El parámetro del middleware `abilities:` de la ruta.
     */
    public function middleware(): string
    {
        return "abilities:{$this->value}";
    }
}
