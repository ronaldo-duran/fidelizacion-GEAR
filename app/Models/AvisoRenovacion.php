<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso de renovación que se le debe a un cliente (E6-01, regla 10): su
 * membresía termina en `vigente_hasta` y no tiene otro periodo pagado
 * después. E7-01 lo envía y llena `enviado_at`.
 *
 * @property int $id
 * @property int $cliente_id
 * @property CarbonImmutable $vigente_hasta
 * @property int $dias_antes
 * @property CarbonImmutable|null $enviado_at
 * @property CarbonImmutable $created_at
 * @property-read Cliente|null $cliente
 */
class AvisoRenovacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'avisos_renovacion';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'vigente_hasta', 'dias_antes'];

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
            'vigente_hasta' => 'immutable_date',
            'dias_antes' => 'integer',
            'enviado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
