<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalRegistro;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * La autorización de tratamiento de datos que aceptó un cliente (Ley 1581).
 * Append-only: es la prueba ante un reclamo, nunca se edita ni se borra.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $version_texto
 * @property CarbonImmutable $aceptada_at
 * @property string|null $ip
 * @property CanalRegistro $canal
 */
class AutorizacionDatos extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'autorizaciones_datos';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'version_texto', 'aceptada_at', 'ip', 'canal'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aceptada_at' => 'immutable_datetime',
            'canal' => CanalRegistro::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
