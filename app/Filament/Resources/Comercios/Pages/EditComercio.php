<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Pages;

use App\Filament\Resources\Comercios\ComercioResource;
use Filament\Resources\Pages\EditRecord;

class EditComercio extends EditRecord
{
    protected static string $resource = ComercioResource::class;
}
