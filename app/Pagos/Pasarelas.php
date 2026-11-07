<?php

declare(strict_types=1);

namespace App\Pagos;

/**
 * Resuelve el adaptador de una pasarela por su nombre (config/pagos.php).
 */
final class Pasarelas
{
    public function para(string $nombre): ?PasarelaPagos
    {
        $clase = config("pagos.pasarelas.{$nombre}");

        if (! is_string($clase) || ! is_a($clase, PasarelaPagos::class, true)) {
            return null;
        }

        $adaptador = app($clase);

        return $adaptador instanceof PasarelaPagos ? $adaptador : null;
    }

    /**
     * La pasarela con la que se inician los pagos nuevos.
     */
    public function nombreActual(): string
    {
        $nombre = config('pagos.pasarela');

        return is_string($nombre) ? $nombre : 'wompi';
    }
}
