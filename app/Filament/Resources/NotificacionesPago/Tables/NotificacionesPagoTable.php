<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificacionesPago\Tables;

use App\Enums\ResultadoNotificacionPago;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificacionesPagoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i:s'),
                TextColumn::make('referencia')->label('Referencia')->placeholder('—')->searchable(),
                TextColumn::make('estado_informado')->label('Estado informado')->placeholder('—'),
                IconColumn::make('firma_valida')->label('Firma')->boolean(),
                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->color(static fn (ResultadoNotificacionPago $state): string => match ($state) {
                        ResultadoNotificacionPago::Aplicado => 'success',
                        ResultadoNotificacionPago::Repetido, ResultadoNotificacionPago::Rechazado => 'gray',
                        ResultadoNotificacionPago::TransicionInvalida,
                        ResultadoNotificacionPago::MontoDistinto => 'warning',
                        ResultadoNotificacionPago::FirmaInvalida,
                        ResultadoNotificacionPago::ReferenciaDesconocida => 'danger',
                    }),
                TextColumn::make('id_externo')->label('Id en la pasarela')->placeholder('—')->toggleable(),
                TextColumn::make('pasarela')->label('Pasarela')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resultado')->label('Resultado')->options(ResultadoNotificacionPago::class),
                TernaryFilter::make('firma_valida')->label('Firma válida'),
            ]);
    }
}
