<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Schemas;

use App\Models\Cliente;
use App\Support\FormatoColombiano;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Los datos del cliente y las tres fuentes del nivel por separado (regla
 * dura 3.5), cada una con su vigencia.
 */
class ClienteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Cliente')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('documento')
                            ->label('Documento')
                            ->state(static fn (Cliente $record): string => "{$record->tipo_documento->value} "
                                . $record->documento),
                        TextEntry::make('nombre_completo')->label('Nombre')->placeholder('Sin nombre'),
                        TextEntry::make('celular')
                            ->label('Celular')
                            ->placeholder('Sin celular')
                            ->helperText(static fn (Cliente $record): string => $record->celular_verificado_at === null
                                ? 'Sin verificar'
                                : 'Verificado'),
                        TextEntry::make('created_at')->label('Registro')->date('Y-m-d'),
                        TextEntry::make('saldo_puntos')
                            ->label('Saldo')
                            ->formatStateUsing(static fn (int $state): string => FormatoColombiano::puntos($state)),
                        TextEntry::make('proteccion_hasta')
                            ->label('Protegido hasta')
                            ->date('Y-m-d')
                            ->placeholder('Sin protección'),
                    ]),
                Section::make('Nivel')
                    ->description('El nivel efectivo es el más alto de las tres fuentes vigentes.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('nivel_efectivo')
                            ->label('Nivel efectivo')
                            ->html()
                            ->state(static fn (Cliente $record): HtmlString => NivelPastilla::html(
                                $record->nivel_efectivo,
                            ))
                            ->helperText(static fn (Cliente $record): ?string => $record->nivel_efectivo_desde === null
                                ? null
                                : 'Desde el ' . $record->nivel_efectivo_desde->format('Y-m-d')),
                        TextEntry::make('nivel_por_puntos')
                            ->label('Por puntos')
                            ->html()
                            ->state(static fn (Cliente $record): HtmlString => NivelPastilla::html(
                                $record->nivel_por_puntos,
                            )),
                        TextEntry::make('nivel_por_membresia')
                            ->label('Por membresía')
                            ->html()
                            ->state(static fn (Cliente $record): HtmlString => NivelPastilla::conVigencia(
                                $record->nivel_por_membresia,
                                $record->membresia_vigente_hasta,
                                'Sin membresía',
                            )),
                        TextEntry::make('nivel_por_evento')
                            ->label('Por evento')
                            ->html()
                            ->state(static fn (Cliente $record): HtmlString => NivelPastilla::conVigencia(
                                $record->nivel_por_evento,
                                $record->evento_vigente_hasta,
                                'Sin evento',
                            )),
                    ]),
            ]);
    }
}
