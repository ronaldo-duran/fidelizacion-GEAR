<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Program customer. `saldo_puntos` is derived from the ledger and is only
 * written by the points actions, inside the same transaction as the
 * movement (hard rule 3.4). The level columns keep the three sources apart
 * and are only written by the level actions (hard rule 3.5).
 *
 * Inicia sesión en la PWA con su propio guard (`cliente`), separado del de
 * los usuarios del panel; su contraseña es el hash del PIN (E8-02).
 *
 * @property int $id
 * @property TipoDocumento $tipo_documento
 * @property string $documento
 * @property CarbonImmutable|null $fecha_nacimiento
 * @property string|null $celular
 * @property string|null $email
 * @property string|null $nombre_completo
 * @property string|null $pin_hash
 * @property CarbonImmutable|null $celular_verificado_at
 * @property int $saldo_puntos
 * @property Nivel $nivel_por_puntos
 * @property Nivel|null $nivel_por_membresia
 * @property CarbonImmutable|null $membresia_vigente_hasta
 * @property Nivel|null $nivel_por_evento
 * @property CarbonImmutable|null $evento_vigente_hasta
 * @property Nivel $nivel_efectivo
 * @property CarbonImmutable|null $nivel_efectivo_desde
 * @property CarbonImmutable|null $proteccion_hasta
 */
class Cliente extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = ['tipo_documento', 'documento', 'fecha_nacimiento', 'celular', 'email'];

    /** @var list<string> */
    protected $hidden = ['pin_hash', 'remember_token'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'nivel_por_puntos' => 'bronce',
        'nivel_efectivo' => 'bronce',
    ];

    /**
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class);
    }

    /**
     * @return HasMany<EventoCalificado, $this>
     */
    public function eventosCalificados(): HasMany
    {
        return $this->hasMany(EventoCalificado::class);
    }

    /**
     * @return HasMany<CambioNivel, $this>
     */
    public function cambiosNivel(): HasMany
    {
        return $this->hasMany(CambioNivel::class);
    }

    /**
     * @return HasMany<AutorizacionDatos, $this>
     */
    public function autorizaciones(): HasMany
    {
        return $this->hasMany(AutorizacionDatos::class);
    }

    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    /**
     * The document as it is stored and looked up: uppercase and without
     * spaces, so `ppt123` and `PPT 123` are the same document on every engine
     * (hard rule 3.13). Lookups must normalize with this too.
     */
    public static function normalizeDocumento(string $documento): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($documento)));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function documento(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => self::normalizeDocumento($value),
        );
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'fecha_nacimiento' => 'immutable_date',
            'saldo_puntos' => 'integer',
            'nivel_por_puntos' => Nivel::class,
            'nivel_por_membresia' => Nivel::class,
            'membresia_vigente_hasta' => 'immutable_date',
            'nivel_por_evento' => Nivel::class,
            'evento_vigente_hasta' => 'immutable_date',
            'nivel_efectivo' => Nivel::class,
            'nivel_efectivo_desde' => 'immutable_datetime',
            'proteccion_hasta' => 'immutable_date',
            'celular_verificado_at' => 'immutable_datetime',
        ];
    }
}
