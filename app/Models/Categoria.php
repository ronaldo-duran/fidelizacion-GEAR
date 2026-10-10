<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditoriaObserver;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría comercial. Solo clasifica; las tasas de acumulación por categoría
 * viven en el motor de reglas (E3-01), nunca aquí.
 *
 * @property string $nombre
 * @property string $slug
 */
#[ObservedBy(AuditoriaObserver::class)]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    /** @var list<string> */
    protected $fillable = ['nombre', 'slug'];

    /**
     * @return HasMany<Comercio, $this>
     */
    public function comercios(): HasMany
    {
        return $this->hasMany(Comercio::class);
    }

    /**
     * El slug se guarda y se busca en minúsculas (regla 3.13: SQL Server
     * compara sin distinguir mayúsculas).
     *
     * @return Attribute<string, string>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => mb_strtolower(trim($value)),
        );
    }
}
