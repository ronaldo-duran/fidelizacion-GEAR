<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nivel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A demotion notice owed to a customer: their points expiring on
 * `fecha_vencimiento` would lower their level. E7-01 sends it and fills
 * `enviado_at`.
 *
 * @property int $id
 * @property int $cliente_id
 * @property CarbonImmutable $fecha_vencimiento
 * @property int $dias_antes
 * @property int $puntos_que_vencen
 * @property Nivel $nivel_actual
 * @property Nivel $nivel_resultante
 * @property CarbonImmutable|null $enviado_at
 * @property CarbonImmutable $created_at
 */
class AvisoDescenso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'avisos_descenso';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'fecha_vencimiento',
        'dias_antes',
        'puntos_que_vencen',
        'nivel_actual',
        'nivel_resultante',
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
            'fecha_vencimiento' => 'immutable_date',
            'dias_antes' => 'integer',
            'puntos_que_vencen' => 'integer',
            'nivel_actual' => Nivel::class,
            'nivel_resultante' => Nivel::class,
            'enviado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
