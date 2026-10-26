<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalRegistro;
use App\Enums\TipoDocumento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un registro que espera la verificación del celular (E8-02). No es un
 * cliente: no acredita compras ni da acceso. El PIN viaja solo como hash.
 *
 * @property int $id
 * @property TipoDocumento $tipo_documento
 * @property string $documento
 * @property string $nombre_completo
 * @property CarbonImmutable $fecha_nacimiento
 * @property string $celular
 * @property string|null $email
 * @property string $pin_hash
 * @property string $autorizacion_version
 * @property string|null $ip
 * @property CanalRegistro $canal
 * @property CarbonImmutable $vence_at
 * @property CarbonImmutable $created_at
 */
class RegistroPendiente extends Model
{
    protected $table = 'registros_pendientes';

    /** @var list<string> */
    protected $hidden = ['pin_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'fecha_nacimiento' => 'immutable_date',
            'canal' => CanalRegistro::class,
            'vence_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
