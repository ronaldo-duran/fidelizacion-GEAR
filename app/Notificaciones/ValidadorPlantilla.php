<?php

declare(strict_types=1);

namespace App\Notificaciones;

use App\Enums\EventoNotificacion;

/**
 * Revisa una plantilla antes de guardarla (E7-01, regla dura 3.8): solo
 * marcadores `{variable}` de la lista blanca del evento, sin llaves sueltas
 * ni textos vacíos. No hay expresiones: todo lo que esté entre llaves es un
 * nombre de variable o un error.
 */
final class ValidadorPlantilla
{
    public const ASUNTO_VACIO = 'Escribe el asunto.';

    public const CUERPO_VACIO = 'Escribe el cuerpo del mensaje.';

    public const LLAVE_SUELTA = 'Hay una llave sin cerrar o sin abrir. Las variables se escriben así: {nombre}.';

    /**
     * @return array<string, string> campo → mensaje; vacío si la plantilla es válida
     */
    public function errores(EventoNotificacion $evento, string $asunto, string $cuerpo): array
    {
        $errores = [];

        foreach (['asunto' => $asunto, 'cuerpo' => $cuerpo] as $campo => $texto) {
            $error = trim($texto) === ''
                ? ($campo === 'asunto' ? self::ASUNTO_VACIO : self::CUERPO_VACIO)
                : $this->error($evento, $texto);

            if ($error !== null) {
                $errores[$campo] = $error;
            }
        }

        return $errores;
    }

    private function error(EventoNotificacion $evento, string $texto): ?string
    {
        preg_match_all('/\{([^{}]*)\}/', $texto, $marcadores);
        $resto = (string) preg_replace('/\{[^{}]*\}/', '', $texto);

        if (str_contains($resto, '{') || str_contains($resto, '}')) {
            return self::LLAVE_SUELTA;
        }

        $permitidas = $evento->variables();

        foreach ($marcadores[1] as $nombre) {
            if (! in_array($nombre, $permitidas, true)) {
                return 'La variable {' . $nombre . '} no existe para este aviso. Puedes usar: '
                    . implode(', ', array_map(self::marcador(...), $permitidas))
                    . '.';
            }
        }

        return null;
    }

    private static function marcador(string $variable): string
    {
        return '{' . $variable . '}';
    }
}
