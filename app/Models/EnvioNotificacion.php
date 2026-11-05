<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalNotificacion;
use App\Enums\EstadoEnvio;
use App\Enums\EventoNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso a un cliente por un canal (E7-01), con el texto ya armado. Es el
 * registro para soporte y la bandeja de avisos de la PWA.
 *
 * @property int $id
 * @property int $cliente_id
 * @property EventoNotificacion $evento
 * @property CanalNotificacion $canal
 * @property int $origen_id
 * @property string|null $destinatario
 * @property string $asunto
 * @property string $cuerpo
 * @property EstadoEnvio $estado
 * @property string|null $motivo
 * @property int $intentos
 * @property string|null $error
 * @property CarbonImmutable|null $enviado_at
 * @property CarbonImmutable|null $leido_at
 * @property CarbonImmutable $created_at
 */
class EnvioNotificacion extends Model
{
    public const UPDATED_AT = null;

    public const SIN_CORREO = 'sin_correo';

    public const SUPRIMIDO = 'suprimido';

    public const PLANTILLA_INACTIVA = 'plantilla_inactiva';

    protected $table = 'envios_notificacion';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'evento',
        'canal',
        'origen_id',
        'destinatario',
        'asunto',
        'cuerpo',
        'estado',
        'motivo',
        'enviado_at',
    ];

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoNotificacion::class,
            'canal' => CanalNotificacion::class,
            'origen_id' => 'integer',
            'estado' => EstadoEnvio::class,
            'intentos' => 'integer',
            'enviado_at' => 'immutable_datetime',
            'leido_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
