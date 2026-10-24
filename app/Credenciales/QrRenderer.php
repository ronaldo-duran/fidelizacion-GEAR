<?php

declare(strict_types=1);

namespace App\Credenciales;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

/**
 * Draws a credential token as a real QR image (SVG), generated on the server.
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
            throw new RuntimeException('The QR could not be drawn.');
        }

        return $svg;
    }
}
