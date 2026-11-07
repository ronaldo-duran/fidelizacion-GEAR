<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificacionesPago\Pages;

use App\Filament\Resources\NotificacionesPago\NotificacionPagoResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificacionesPago extends ListRecords
{
    protected static string $resource = NotificacionPagoResource::class;
}
