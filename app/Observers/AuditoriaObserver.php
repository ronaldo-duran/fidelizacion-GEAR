<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Observa los modelos que usan el trait EsAuditable y deja constancia de
 * quién hizo qué y cuándo, con el estado antes y después del cambio.
 */
class AuditoriaObserver
{
    public function created(Model $model): void
    {
        $this->registrar($model, 'creado', null, $this->atributosVisibles($model));
    }

    public function updated(Model $model): void
    {
        $cambios = array_keys($model->getChanges());
        $antes = array_intersect_key($model->getOriginal(), array_flip($cambios));
        $despues = array_intersect_key($model->getAttributes(), array_flip($cambios));

        $this->registrar($model, 'actualizado', $this->ocultarSensibles($antes), $this->ocultarSensibles($despues));
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, 'eliminado', $this->atributosVisibles($model), null);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private function registrar(Model $model, string $evento, ?array $antes, ?array $despues): void
    {
        Auditoria::query()->create([
            'usuario_id' => Auth::id(),
            'evento' => $evento,
            'modelo' => $model::class,
            'modelo_id' => $model->getKey(),
            'antes' => $antes,
            'despues' => $despues,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosVisibles(Model $model): array
    {
        return $this->ocultarSensibles($model->attributesToArray());
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function ocultarSensibles(array $atributos): array
    {
        unset($atributos['password'], $atributos['remember_token']);

        return $atributos;
    }
}
