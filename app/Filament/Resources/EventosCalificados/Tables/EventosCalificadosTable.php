<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventosCalificados\Tables;

use App\Actions\Niveles\CloseEventoCalificado;
use App\Models\EventoCalificado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventosCalificadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('cliente.documento')->label('Cliente')->searchable(),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('comercio.razon_social')->label('Comercio'),
                TextColumn::make('vigente_desde')->label('Desde')->date('Y-m-d'),
                TextColumn::make('vigente_hasta')->label('Hasta')->date('Y-m-d')->sortable(),
                TextColumn::make('motivo_cierre')->label('Cierre')->placeholder('Abierto'),
            ])
            ->recordActions([self::closeAction()]);
    }

    /**
     * Closing brings the end date forward; the level drops in the nightly
     * evaluation, not here.
     */
    private static function closeAction(): Action
    {
        return Action::make('cerrar')
            ->label('Cerrar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(static fn (EventoCalificado $record): bool => $record->motivo_cierre === null
                && Gate::allows('close', $record))
            ->modalDescription('El nivel del cliente se ajusta en la evaluación nocturna.')
            ->schema([
                DatePicker::make('fecha_cierre')->label('Fecha de cierre')->default(now())->required(),
                TextInput::make('motivo_cierre')->label('Motivo')->required()->maxLength(255),
            ])
            ->action(static function (EventoCalificado $record, array $data, Action $action): void {
                $user = Auth::user();

                abort_unless($user instanceof User, 403);

                $fecha = is_string($data['fecha_cierre'] ?? null) ? $data['fecha_cierre'] : 'today';

                try {
                    app(CloseEventoCalificado::class)(
                        $user,
                        $record->id,
                        CarbonImmutable::parse($fecha),
                        is_string($data['motivo_cierre'] ?? null) ? $data['motivo_cierre'] : '',
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se cerró el evento')
                        ->body(implode(' ', $exception->validator->errors()->all()))
                        ->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Evento cerrado')->send();
            });
    }
}
