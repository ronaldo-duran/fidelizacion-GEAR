<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditoriaObserver;
use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sede de un comercio.
 *
 * @property int $comercio_id
 * @property string $nombre
 * @property string $direccion
 * @property bool $activa
 */
#[ObservedBy(AuditoriaObserver::class)]
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory;

    protected $table = 'sedes';

    /** @var list<string> */
    protected $fillable = ['comercio_id', 'nombre', 'direccion', 'activa'];

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
