<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropositoVerificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un código de verificación por SMS. Solo se guarda su huella y se oculta al
 * serializar, para que nunca llegue a una respuesta ni a un log.
 *
 * @property int $id
 * @property PropositoVerificacion $proposito
 * @property string $celular
 * @property string $codigo_hash
 * @property int $intentos
 * @property CarbonImmutable $vence_at
 * @property CarbonImmutable|null $usado_at
 * @property CarbonImmutable|null $invalidado_at
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 */
class CodigoVerificacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'codigos_verificacion';

    /** @var list<string> */
    protected $fillable = ['proposito', 'celular', 'codigo_hash', 'vence_at', 'ip'];

    /** @var list<string> */
    protected $hidden = ['codigo_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposito' => PropositoVerificacion::class,
            'intentos' => 'integer',
            'vence_at' => 'immutable_datetime',
            'usado_at' => 'immutable_datetime',
            'invalidado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
