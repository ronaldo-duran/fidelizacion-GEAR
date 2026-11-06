<?php

declare(strict_types=1);

namespace App\Filament\Resources\PagosMembresia\Pages;

use App\Filament\Resources\PagosMembresia\PagoMembresiaResource;
use Filament\Resources\Pages\ListRecords;

class ListPagosMembresia extends ListRecords
{
    protected static string $resource = PagoMembresiaResource::class;
}
