<?php

declare(strict_types=1);

namespace App\Filament\Resources\CumplimientosMision\Pages;

use App\Filament\Resources\CumplimientosMision\CumplimientoMisionResource;
use Filament\Resources\Pages\ListRecords;

class ListCumplimientosMision extends ListRecords
{
    protected static string $resource = CumplimientoMisionResource::class;
}
