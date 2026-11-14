<?php

declare(strict_types=1);

namespace App\Actions\Notificaciones;

use App\Enums\CanalNotificacion;
use App\Enums\EstadoEnvio;
use App\Enums\EventoNotificacion;
use App\Jobs\SendEnvioCorreo;
use App\Models\Cliente;
use App\Models\EnvioNotificacion;
use App\Models\PlantillaNotificacion;
use App\Models\PreferenciaAviso;
use App\Models\SupresionCorreo;
use App\Notificaciones\RenderPlantilla;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Avisa un evento a un cliente (E7-01): arma el texto con la plantilla del
 * evento y crea un envío por canal. El aviso en la app nace enviado; el
 * correo queda pendiente y sale en la cola cuando la transacción que lo
 * originó se confirma, o queda omitido con su motivo (sin correo,
 * suprimido, plantilla inactiva).
 *
 * Se llama dentro de la transacción del origen: si esa transacción se
 * deshace, no queda envío ni sale correo. La restricción única por evento,
 * origen y canal hace que repetir el disparo no mande nada dos veces.
 */
class Notify
{
    private readonly RenderPlantilla $render;

    public function __construct(RenderPlantilla $render)
    {
        $this->render = $render;
    }

    /**
     * @param  array<string, string>  $valores  variables del evento, sin `nombre` (lo pone esta Action)
     */
    public function __invoke(EventoNotificacion $evento, Cliente $cliente, int $origenId, array $valores): void
    {
        $plantilla = PlantillaNotificacion::query()->where('evento', $evento->value)->first();
        $valores = ['nombre' => self::nombre($cliente), ...$valores];
        $base = [
            'cliente_id' => $cliente->id,
            'evento' => $evento,
            'origen_id' => $origenId,
            'asunto' => $this->render->texto($plantilla->asunto ?? $evento->asuntoInicial(), $valores),
            'cuerpo' => $this->render->texto($plantilla->cuerpo ?? $evento->cuerpoInicial(), $valores),
        ];
        $inactiva = $plantilla !== null && ! $plantilla->activa;
        $app = $inactiva
            ? self::omitido(EnvioNotificacion::PLANTILLA_INACTIVA)
            : ['estado' => EstadoEnvio::Enviado, 'enviado_at' => CarbonImmutable::now()];

        $this->create([...$base, 'canal' => CanalNotificacion::App, ...$app]);

        $correo = $this->create([
            ...$base,
            'canal' => CanalNotificacion::Correo,
            'destinatario' => self::email($cliente),
            ...($inactiva
                ? self::omitido(EnvioNotificacion::PLANTILLA_INACTIVA)
                : $this->estadoCorreo($evento, $cliente)),
        ]);

        if ($correo?->estado === EstadoEnvio::Pendiente) {
            SendEnvioCorreo::dispatch($correo->id)->afterCommit();
        }
    }

    /**
     * El primer nombre, para el saludo. Sin nombre registrado, "cliente".
     */
    public static function nombre(Cliente $cliente): string
    {
        $nombre = strtok(trim((string) $cliente->nombre_completo), ' ');

        return $nombre === false ? 'cliente' : $nombre;
    }

    private static function email(Cliente $cliente): ?string
    {
        $email = trim((string) $cliente->email);

        return $email === '' ? null : SupresionCorreo::normalizeEmail($email);
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoCorreo(EventoNotificacion $evento, Cliente $cliente): array
    {
        $email = self::email($cliente);

        if ($email === null) {
            return self::omitido(EnvioNotificacion::SIN_CORREO);
        }

        // El cliente apagó este correo en su perfil (E8-05, regla 10); el aviso en la app sí se crea.
        if (PreferenciaAviso::correoApagado($cliente->id, $evento)) {
            return self::omitido(EnvioNotificacion::PREFERENCIA_CLIENTE);
        }

        return SupresionCorreo::motivoPara($email)?->bloquea($evento) === true
            ? self::omitido(EnvioNotificacion::SUPRIMIDO)
            : ['estado' => EstadoEnvio::Pendiente];
    }

    /**
     * @return array<string, mixed>
     */
    private static function omitido(string $motivo): array
    {
        return ['estado' => EstadoEnvio::Omitido, 'motivo' => $motivo];
    }

    /**
     * Inserta en un savepoint: en PostgreSQL una restricción violada aborta
     * la transacción entera, y la del origen debe seguir viva.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function create(array $atributos): ?EnvioNotificacion
    {
        try {
            return DB::transaction(static fn (): EnvioNotificacion => EnvioNotificacion::query()->create($atributos));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
