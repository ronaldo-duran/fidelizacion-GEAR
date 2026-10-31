<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transacciones\Tables;

use App\Actions\Compras\AnnulCompra;
use App\Enums\EstadoTransaccion;
use App\Models\Transaccion;
use App\Models\User;
use App\Panel\AnulacionPanel;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransaccionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('created_at')->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i'),
                TextColumn::make('comercio.razon_social')->label('Comercio'),
                TextColumn::make('sede.nombre')->label('Sede')->placeholder('—'),
                TextColumn::make('cliente_compra')
                    ->label('Cliente')
                    ->state(static fn (Transaccion $record): string => self::cliente($record)),
                TextColumn::make('monto')
                    ->label('Monto')
                    ->alignEnd()
                    ->formatStateUsing(static fn (int $state): string => FormatoColombiano::pesos($state)),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(static fn (EstadoTransaccion $state): string => match ($state) {
                        EstadoTransaccion::Acreditada => 'success',
                        EstadoTransaccion::Pendiente => 'warning',
                        EstadoTransaccion::Anulada => 'danger',
                    }),
                TextColumn::make('canal')->label('Canal'),
                TextColumn::make('referencia_externa')->label('Referencia'),
                TextColumn::make('registrador.name')->label('Registró')->placeholder('API'),
            ])
            ->filters(self::filtros())
            ->recordActions([self::anular()]);
    }

    /**
     * El documento de una compra pendiente, o el cliente con su nombre.
     */
    private static function cliente(Transaccion $transaccion): string
    {
        $documento = "{$transaccion->tipo_documento_cliente->value} {$transaccion->documento_cliente}";
        $cliente = $transaccion->cliente;

        if ($cliente === null) {
            return "{$documento} · pendiente";
        }

        return $cliente->nombre_completo === null ? $documento : "{$documento} · {$cliente->nombre_completo}";
    }

    /**
     * Periodo (en America/Bogota, la zona de la aplicación), comercio, sede y
     * estado. El administrador de comercio no ve el filtro de comercio y sus
     * sedes son solo las de su comercio.
     *
     * @return list<Filter|SelectFilter>
     */
    private static function filtros(): array
    {
        return [
            Filter::make('periodo')
                ->schema([
                    DatePicker::make('desde')->label('Desde'),
                    DatePicker::make('hasta')->label('Hasta'),
                ])
                ->query(static function (Builder $query, array $data): Builder {
                    $desde = is_string($data['desde'] ?? null) ? $data['desde'] : null;
                    $hasta = is_string($data['hasta'] ?? null) ? $data['hasta'] : null;

                    if ($desde !== null) {
                        $query->getQuery()->where('created_at', '>=', CarbonImmutable::parse($desde)->startOfDay());
                    }

                    if ($hasta !== null) {
                        $hastaFin = CarbonImmutable::parse($hasta)->addDay()->startOfDay();
                        $query->getQuery()->where('created_at', '<', $hastaFin);
                    }

                    return $query;
                }),
            SelectFilter::make('comercio_id')
                ->label('Comercio')
                ->relationship('comercio', 'razon_social')
                ->visible(static fn (): bool => self::usuario()->esAdministradorDelGrupo()),
            SelectFilter::make('sede_id')
                ->label('Sede')
                ->relationship('sede', 'nombre', static function (Builder $query): Builder {
                    $user = self::usuario();

                    if (! $user->esAdministradorDelGrupo()) {
                        $query->getQuery()->where('comercio_id', $user->comercio_id ?? 0);
                    }

                    return $query;
                }),
            SelectFilter::make('estado')->label('Estado')->options(EstadoTransaccion::class),
        ];
    }

    /**
     * "Anular" con un motivo escrito (decisión 5). Fuera de plazo, de otro
     * comercio o ya anulada no aparece; `AnnulCompra` vuelve a verificarlo.
     */
    private static function anular(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(static fn (Transaccion $record): bool => AnulacionPanel::puedeAnular(self::usuario(), $record))
            ->modalDescription('Si la compra dio puntos, se descuentan del cliente con una reversa en el libro.')
            ->schema([
                Textarea::make('motivo')->label('Motivo de la anulación')->required()->maxLength(255),
            ])
            ->action(static function (Transaccion $record, array $data, Action $action): void {
                try {
                    app(AnnulCompra::class)(
                        self::usuario(),
                        $record->id,
                        is_string($data['motivo'] ?? null) ? $data['motivo'] : '',
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se anuló la compra')
                        ->body(implode(' ', $exception->validator->errors()->all()))
                        ->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Compra anulada')->send();
            });
    }

    private static function usuario(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
