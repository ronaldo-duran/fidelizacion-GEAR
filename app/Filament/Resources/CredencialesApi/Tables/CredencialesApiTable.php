<?php

declare(strict_types=1);

namespace App\Filament\Resources\CredencialesApi\Tables;

use App\Actions\CredencialesApi\RevokeCredencialApi;
use App\Actions\CredencialesApi\RotateCredencialApi;
use App\Enums\AlcanceApi;
use App\Filament\Resources\CredencialesApi\Pages\ListCredencialesApi;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * La lista de credenciales con su último uso y sus acciones. Rotar muestra el
 * token nuevo una sola vez, igual que crear (página de la lista).
 */
class CredencialesApiTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('comercio.razon_social')->label('Comercio'),
                TextColumn::make('sede.nombre')->label('Sede'),
                TextColumn::make('alcances')
                    ->label('Permisos')
                    ->state(static fn (User $record): array => self::alcances($record))
                    ->badge(),
                TextColumn::make('ultimo_uso')
                    ->label('Último uso')
                    ->state(static fn (User $record): ?CarbonImmutable => self::ultimoUso($record))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('Nunca'),
                TextColumn::make('activo')
                    ->label('Estado')
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Activa' : 'Revocada')
                    ->badge()
                    ->color(static fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordActions([self::rotar(), self::revocar()]);
    }

    private static function rotar(): Action
    {
        $horas = config('api.rotation_grace_hours');

        return Action::make('rotar')
            ->label('Rotar')
            ->icon('heroicon-o-arrow-path')
            ->visible(static fn (User $record): bool => $record->activo)
            ->requiresConfirmation()
            ->modalHeading('Rotar el token')
            ->modalDescription('El token actual sigue sirviendo ' . (is_int($horas) ? $horas : 0)
                . ' horas. Después solo sirve el nuevo.')
            ->action(static function (User $record, ListCredencialesApi $livewire, Action $action): void {
                try {
                    $emitida = app(RotateCredencialApi::class)(self::admin(), $record->id);
                } catch (ValidationException $error) {
                    Notification::make()->danger()->title(implode(' ', $error->validator->errors()->all()))->send();
                    $action->halt();

                    return;
                }

                $livewire->replaceMountedAction('mostrarToken', ['token' => $emitida->token]);
            });
    }

    private static function revocar(): Action
    {
        return Action::make('revocar')
            ->label('Revocar')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(static fn (User $record): bool => $record->activo)
            ->requiresConfirmation()
            ->modalHeading('Revocar la credencial')
            ->modalDescription('El POS que use este token deja de registrar compras de inmediato.')
            ->action(static function (User $record): void {
                app(RevokeCredencialApi::class)(self::admin(), $record->id);

                Notification::make()->success()->title('Credencial revocada')->send();
            });
    }

    /**
     * Los permisos del token que no vence (el vigente).
     *
     * @return list<string>
     */
    private static function alcances(User $record): array
    {
        $token = $record->tokens->first(static fn (PersonalAccessToken $token): bool => $token->expires_at === null);
        $valores = $token === null ? [] : (array) $token->abilities;

        $etiquetas = [];

        foreach ($valores as $valor) {
            $alcance = is_string($valor) ? AlcanceApi::tryFrom($valor) : null;

            if ($alcance !== null) {
                $etiquetas[] = $alcance->getLabel();
            }
        }

        return $etiquetas;
    }

    private static function ultimoUso(User $record): ?CarbonImmutable
    {
        $ultimo = null;

        foreach ($record->tokens as $token) {
            $uso = $token->last_used_at === null ? null : CarbonImmutable::instance($token->last_used_at);
            $ultimo = $uso !== null && ($ultimo === null || $uso->gt($ultimo)) ? $uso : $ultimo;
        }

        return $ultimo;
    }

    private static function admin(): User
    {
        $admin = Auth::user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
