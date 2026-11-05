<?php

declare(strict_types=1);

namespace App\Notificaciones;

/**
 * Arma el texto de un aviso (E7-01). La sustitución es texto por texto y en
 * una sola pasada: un valor que parezca un marcador no se vuelve a
 * sustituir, y el texto del administrador nunca se compila como Blade
 * (regla dura 3.8).
 */
final class RenderPlantilla
{
    /**
     * @param  array<string, string>  $valores  variable → valor
     */
    public function texto(string $plantilla, array $valores): string
    {
        $reemplazos = [];

        foreach ($valores as $variable => $valor) {
            $reemplazos['{' . $variable . '}'] = $valor;
        }

        return strtr($plantilla, $reemplazos);
    }

    /**
     * El cuerpo ya armado como HTML del correo: todo escapado, un párrafo
     * por bloque separado con una línea en blanco y los enlaces clicables.
     */
    public function html(string $cuerpo): string
    {
        $parrafos = preg_split('/\R\s*\R/', trim($cuerpo));

        if ($parrafos === false) {
            return '';
        }

        return implode("\n", array_map(static function (string $parrafo): string {
            $escapado = nl2br(e(trim($parrafo)), false);
            $enlazado = preg_replace('~https?://[^\s<]+~', '<a href="$0">$0</a>', $escapado);

            return '<p>' . ($enlazado ?? $escapado) . '</p>';
        }, $parrafos));
    }
}
