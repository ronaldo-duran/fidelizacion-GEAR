<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventoNotificacion;
use App\Observers\AuditoriaObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * El asunto y el cuerpo de un evento (E7-01), editables en el panel. Se
 * editan en sitio y la auditoría guarda el antes y el después; el envío
 * guarda el texto ya armado.
 *
 * @property int $id
 * @property EventoNotificacion $evento
 * @property string $asunto
 * @property string $cuerpo
 * @property bool $activa
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[ObservedBy(AuditoriaObserver::class)]
class PlantillaNotificacion extends Model
{
    protected $table = 'plantillas_notificacion';

    /** @var list<string> */
    protected $fillable = ['asunto', 'cuerpo', 'activa'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoNotificacion::class,
            'activa' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
