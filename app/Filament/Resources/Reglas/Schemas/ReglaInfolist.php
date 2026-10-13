<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Models\Regla;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReglaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Versión vigente')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('codigo')->label('Código'),
                        TextEntry::make('tipo')->label('Tipo')->badge(),
                        TextEntry::make('version')->label('Versión'),
                        TextEntry::make('vigente_desde')->label('Vigente desde')->dateTime('Y-m-d H:i'),
                        IconEntry::make('activa')->label('Activa')->boolean(),
                        TextEntry::make('creator.name')->label('Guardada por')->placeholder('Sistema'),
                    ]),
                Section::make('Definición')
                    ->schema([
                        TextEntry::make('definicion_json')
                            ->hiddenLabel()
                            ->html()
                            ->state(static fn (Regla $record): string => DefinicionAsJson::html($record)),
                    ]),
            ]);
    }
}
