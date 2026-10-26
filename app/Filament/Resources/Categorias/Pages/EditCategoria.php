<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Concerns\VuelveALaLista;
use Filament\Resources\Pages\EditRecord;

class EditCategoria extends EditRecord
{
    use VuelveALaLista;

    protected static string $resource = CategoriaResource::class;
}
