<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Pages;

use App\Actions\Puntos\ApplyAjusteManual;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * La ficha del cliente. "Ajustar puntos" solo la ve el administrador del
 * grupo; la Action vuelve a autorizar, así que llamarla igual da 403.
 */
class ViewCliente extends ViewRecord
{
    protected static string $resource = ClienteResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajustar')
                ->label('Ajustar puntos')
                ->icon(Heroicon::OutlinedAdjustmentsVertical)
                ->visible(static fn (): bool => Gate::allows('recordAjusteManual', MovimientoPuntos::class))
                ->modalDescription('Un ajuste positivo puede subir el nivel ahora. Uno negativo baja el saldo; '
                    . 'el nivel se revisa en la evaluación nocturna.')
                ->schema([
                    TextInput::make('puntos')
                        ->label('Puntos')
                        ->helperText('Positivo para sumar, negativo para restar.')
                        ->integer()
                        ->required()
                        ->notIn([0])
                        ->validationMessages(['not_in' => 'El ajuste no puede ser de cero puntos.']),
                    Textarea::make('motivo')->label('Motivo del ajuste')->required()->maxLength(255),
                ])
                ->action(function (array $data, Action $action): void {
                    $this->ajustar($data, $action);
                }),
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    private function ajustar(array $data, Action $action): void
    {
        $user = Auth::user();
        $cliente = $this->getRecord();

        abort_unless($user instanceof User && $cliente instanceof Cliente, 403);

        try {
            app(ApplyAjusteManual::class)(
                $user,
                $cliente->id,
                is_numeric($data['puntos'] ?? null) ? (int) $data['puntos'] : 0,
                is_string($data['motivo'] ?? null) ? $data['motivo'] : '',
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se ajustaron los puntos')
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->send();
            $action->halt();
        }

        $cliente->refresh();
        Notification::make()->success()->title('Puntos ajustados')->send();
    }
}
