<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nivel;
use App\Observers\AuditoriaObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan de membresía (E6-01): el nivel que otorga, por cuántos meses y a qué
 * precio en pesos enteros. No se borra: se desactiva. Los cambios quedan en
 * la auditoría.
 *
 * @property int $id
 * @property string $nombre
 * @property Nivel $nivel
 * @property int $duracion_meses
 * @property int $precio_cop
 * @property bool $activo
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[ObservedBy(AuditoriaObserver::class)]
class PlanMembresia extends Model
{
    protected $table = 'planes_membresia';

    /** @var list<string> */
    protected $fillable = ['nombre', 'nivel', 'duracion_meses', 'precio_cop', 'activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
            'duracion_meses' => 'integer',
            'precio_cop' => 'integer',
            'activo' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
