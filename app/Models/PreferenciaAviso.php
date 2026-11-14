<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventoNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Si el cliente quiere recibir por correo un tipo de aviso (E8-05, regla 9).
 * Sin fila, el aviso está encendido. El aviso dentro de la PWA no depende de
 * esto: siempre se crea.
 *
 * @property int $id
 * @property int $cliente_id
 * @property EventoNotificacion $evento
 * @property bool $correo
 * @property CarbonImmutable $updated_at
 */
class PreferenciaAviso extends Model
{
    public const CREATED_AT = null;

    protected $table = 'preferencias_avisos';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'evento', 'correo'];

    /**
     * Los eventos que el cliente puede apagar, con su texto (el resto se
     * manda siempre).
     *
     * @return array<string, string> evento => texto del interruptor
     */
    public static function interruptores(): array
    {
        $avisos = config('perfil.avisos_correo');
        $interruptores = [];

        foreach (is_array($avisos) ? $avisos : [] as $evento => $texto) {
            if (is_string($evento) && is_string($texto)) {
                $interruptores[$evento] = $texto;
            }
        }

        return $interruptores;
    }

    public static function correoApagado(int $clienteId, EventoNotificacion $evento): bool
    {
        if (! array_key_exists($evento->value, self::interruptores())) {
            return false;
        }

        return self::query()->toBase()
            ->where('cliente_id', $clienteId)
            ->where('evento', $evento->value)
            ->where('correo', false)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoNotificacion::class,
            'correo' => 'boolean',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
