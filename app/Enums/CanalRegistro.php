<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por dónde llegó el cliente al registro: la PWA directamente o el QR que le
 * muestra el cajero cuando su documento no existe.
 */
enum CanalRegistro: string
{
    case Pwa = 'pwa';
    case CajaQr = 'caja_qr';
}
