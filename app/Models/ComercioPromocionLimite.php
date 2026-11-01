<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditoriaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que el grupo le habilita a un comercio en promociones (E3-06). Lo
 * administra solo el grupo y sus cambios quedan en la auditoría (E2-01).
 *
 * @property int $id
 * @property int $comercio_id
 * @property bool $permite_misiones
 * @property int $puntos_max_por_mision
 */
#[ObservedBy(AuditoriaObserver::class)]
class ComercioPromocionLimite extends Model
{
    protected $table = 'comercio_promocion_limites';

    /** @var list<string> */
    protected $fillable = ['comercio_id', 'permite_misiones', 'puntos_max_por_mision'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permite_misiones' => 'boolean',
            'puntos_max_por_mision' => 'integer',
        ];
    }
}
