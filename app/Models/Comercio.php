<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditoriaObserver;
use Database\Factories\ComercioFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comercio del grupo. Se desactiva, no se borra: un comercio inactivo no
 * puede registrar compras (se verifica en E4-01).
 *
 * @property string $razon_social
 * @property string $nit
 * @property int $categoria_id
 * @property bool $activo
 */
#[ObservedBy(AuditoriaObserver::class)]
class Comercio extends Model
{
    /** @use HasFactory<ComercioFactory> */
    use HasFactory;

    protected $table = 'comercios';

    /** @var list<string> */
    protected $fillable = ['razon_social', 'nit', 'categoria_id', 'activo'];

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return HasMany<Sede, $this>
     */
    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    public function puedeRegistrarCompras(): bool
    {
        return $this->activo;
    }

    /**
     * @param  Builder<Comercio>  $query
     * @return Builder<Comercio>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
