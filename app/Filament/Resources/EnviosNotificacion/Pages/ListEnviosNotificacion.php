<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnviosNotificacion\Pages;

use App\Filament\Resources\EnviosNotificacion\EnvioNotificacionResource;
use Filament\Resources\Pages\ListRecords;

class ListEnviosNotificacion extends ListRecords
{
    protected static string $resource = EnvioNotificacionResource::class;
}
