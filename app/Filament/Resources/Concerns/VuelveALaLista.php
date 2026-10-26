<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

/**
 * Después de crear o de guardar cambios, vuelve a la lista del recurso con el
 * aviso de Filament, en vez de quedarse en la edición (lo que hace Filament
 * por defecto y parece que el formulario siguiera abierto). "Crear y crear
 * otro" sigue dejando el formulario vacío.
 */
trait VuelveALaLista
{
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
