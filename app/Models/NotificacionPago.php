<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoNotificacionPago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro append-only de una notificación de la pasarela (E6-01), válida o
 * no, con lo que se hizo con ella.
 *
 * @property int $id
 * @property string $pasarela
 * @property string|null $referencia
 * @property string|null $id_externo
 * @property string|null $estado_informado
 * @property bool $firma_valida
 * @property ResultadoNotificacionPago $resultado
 * @property array<array-key, mixed> $payload
 * @property CarbonImmutable $created_at
 */
class NotificacionPago extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notificaciones_pago';

    /** @var list<string> */
    protected $fillable = [
        'pasarela',
        'referencia',
        'id_externo',
        'estado_informado',
        'firma_valida',
        'resultado',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'firma_valida' => 'boolean',
            'resultado' => ResultadoNotificacionPago::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
