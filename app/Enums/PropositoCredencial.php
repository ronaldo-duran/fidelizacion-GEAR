<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a QR credential is for: the cashier recognizing the customer to
 * register a purchase, or the customer using one benefit. A code for one
 * never works for the other.
 */
enum PropositoCredencial: string
{
    case Identificar = 'identificar';
    case Beneficio = 'beneficio';
}
