<?php

declare(strict_types=1);

namespace App\Filament\Resources\CumplimientosMision\Tables;

use App\Actions\Promociones\ApproveCumplimientoMision;
use App\Actions\Promociones\RejectCumplimientoMision;
use App\Enums\EstadoCumplimiento;
use App\Models\CumplimientoMision;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ver el soporte, aprobar y rechazar. Las decisiones las toman las Actions
 * de promociones; aquí solo se muestran sus errores sin guardar nada.
 */
class AccionesCumplimiento
{
    /**
     * El disco privado donde la PWA guarda los pantallazos.
     */
    private const DISCO = 'local';

    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::soporte(), self::aprobar(), self::rechazar()];
    }

    private static function soporte(): Action
    {
        return Action::make('soporte')
            ->label('Ver soporte')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->authorize('view')
            ->visible(static fn (CumplimientoMision $record): bool => $record->soporte_path !== null)
            ->action(static function (CumplimientoMision $record): ?StreamedResponse {
                $path = (string) $record->soporte_path;

                if (! Storage::disk(self::DISCO)->exists($path)) {
                    Notification::make()->danger()->title('No se encontró el archivo del soporte.')->send();

                    return null;
                }

                return Storage::disk(self::DISCO)->download($path);
            });
    }

    private static function aprobar(): Action
    {
        return Action::make('aprobar')
            ->label('Aprobar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('review')
            ->visible(self::pendiente(...))
            ->requiresConfirmation()
            ->modalHeading('Aprobar el cumplimiento')
            ->modalDescription(static function (CumplimientoMision $record): string {
                $puntos = $record->regla?->definicion['puntos'] ?? 0;

                return 'Se abonan ' . (is_int($puntos) ? $puntos : 0) . ' puntos al cliente. No se puede deshacer.';
            })
            ->action(static function (CumplimientoMision $record, Action $action): void {
                self::ejecutar(
                    $action,
                    'No se aprobó',
                    static fn (User $user): mixed => app(ApproveCumplimientoMision::class)($user, $record->id),
                );

                Notification::make()->success()->title('Cumplimiento aprobado: se abonaron los puntos.')->send();
            });
    }

    private static function rechazar(): Action
    {
        return Action::make('rechazar')
            ->label('Rechazar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('review')
            ->visible(self::pendiente(...))
            ->modalHeading('Rechazar el cumplimiento')
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo')
                    ->helperText('El cliente lo verá.')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(static function (CumplimientoMision $record, array $data, Action $action): void {
                $motivo = is_string($data['motivo'] ?? null) ? $data['motivo'] : '';

                self::ejecutar(
                    $action,
                    'No se rechazó',
                    static fn (User $user): mixed => app(RejectCumplimientoMision::class)($user, $record->id, $motivo),
                );

                Notification::make()->success()->title('Cumplimiento rechazado.')->send();
            });
    }

    private static function pendiente(CumplimientoMision $record): bool
    {
        return $record->estado === EstadoCumplimiento::Pendiente;
    }

    /**
     * @param  Closure(User): mixed  $decision
     */
    private static function ejecutar(Action $action, string $titulo, Closure $decision): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        try {
            $decision($user);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title($titulo)
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->send();
            $action->halt();
        }
    }
}
