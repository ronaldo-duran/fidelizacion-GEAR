<?php

declare(strict_types=1);

namespace App\Credenciales;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

/**
 * Dibuja el token de una credencial como una imagen QR real (SVG), generada en
 * el servidor.
 */
class QrRenderer
{
    public function svg(string $token): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
        ]);

        $svg = (new QRCode($options))->render($token);

        if (! is_string($svg)) {
            throw new RuntimeException('No se pudo dibujar el QR.');
        }

        return $svg;
    }
}
