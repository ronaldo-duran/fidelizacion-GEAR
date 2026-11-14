<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnviosNotificacion\Tables;

use App\Enums\CanalNotificacion;
use App\Enums\EstadoEnvio;
use App\Enums\EventoNotificacion;
use App\Models\EnvioNotificacion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnviosNotificacionTable
{
    private const MOTIVOS = [
        EnvioNotificacion::SIN_CORREO => 'Sin correo',
        EnvioNotificacion::SUPRIMIDO => 'Correo suprimido',
        EnvioNotificacion::PLANTILLA_INACTIVA => 'Plantilla inactiva',
        EnvioNotificacion::PREFERENCIA_CLIENTE => 'El cliente lo apagó',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i'),
                TextColumn::make('cliente.documento')
                    ->label('Cliente')
                    ->searchable()
                    ->formatStateUsing(static fn (string $state, EnvioNotificacion $record): string => trim(
                        "{$record->cliente?->tipo_documento->value} {$state} · {$record->cliente?->nombre_completo}",
                        ' ·',
                    )),
                TextColumn::make('evento')->label('Aviso'),
                TextColumn::make('canal')->label('Canal'),
                TextColumn::make('destinatario')->label('Correo')->placeholder('—')->searchable(),
                TextColumn::make('asunto')
                    ->label('Asunto')
                    ->limit(40)
                    ->tooltip(static fn (EnvioNotificacion $record): string => $record->asunto),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(static fn (EstadoEnvio $state): string => match ($state) {
                        EstadoEnvio::Enviado => 'success',
                        EstadoEnvio::Pendiente => 'warning',
                        EstadoEnvio::Omitido => 'gray',
                        EstadoEnvio::Fallido => 'danger',
                    }),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->formatStateUsing(static fn (string $state): string => self::MOTIVOS[$state] ?? $state),
                TextColumn::make('error')
                    ->label('Error')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('evento')->label('Aviso')->options(EventoNotificacion::class),
                SelectFilter::make('canal')->label('Canal')->options(CanalNotificacion::class),
                SelectFilter::make('estado')->label('Estado')->options(EstadoEnvio::class),
            ]);
    }
}
