<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transacciones\Pages;

use App\Filament\Resources\Transacciones\TransaccionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransacciones extends ListRecords
{
    protected static string $resource = TransaccionResource::class;
}
