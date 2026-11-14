<?php

declare(strict_types=1);

namespace App\Filament\Resources\CredencialesApi\Pages;

use App\Actions\CredencialesApi\CreateCredencialApi;
use App\Enums\AlcanceApi;
use App\Filament\Resources\CredencialesApi\CredencialApiResource;
use App\Models\Sede;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * La lista de credenciales con "Nueva credencial". El token recién emitido
 * (al crear o rotar) se muestra una sola vez en `mostrarToken`, con el botón
 * de copiar.
 */
class ListCredencialesApi extends ListRecords
{
    protected static string $resource = CredencialApiResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('crear')
                ->label('Nueva credencial')
                ->icon('heroicon-o-plus')
                ->modalHeading('Nueva credencial de la API')
                ->modalSubmitActionLabel('Crear')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->placeholder('POS caja 1')
                        ->required()
                        ->maxLength(100),
                    Select::make('sede_id')
                        ->label('Sede')
                        ->options(self::sedes(...))
                        ->searchable()
                        ->required(),
                    CheckboxList::make('alcances')
                        ->label('Permisos')
                        ->options(AlcanceApi::class)
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $emitida = app(CreateCredencialApi::class)(
                            self::admin(),
                            is_string($data['nombre'] ?? null) ? $data['nombre'] : '',
                            is_numeric($data['sede_id'] ?? null) ? (int) $data['sede_id'] : 0,
                            self::alcancesElegidos($data['alcances'] ?? []),
                        );
                    } catch (ValidationException $error) {
                        Notification::make()->danger()->title(implode(' ', $error->validator->errors()->all()))->send();
                        $action->halt();

                        return;
                    }

                    $this->replaceMountedAction('mostrarToken', ['token' => $emitida->token]);
                }),
        ];
    }

    /**
     * El token en claro, una sola vez (regla 4).
     */
    public function mostrarTokenAction(): Action
    {
        return Action::make('mostrarToken')
            ->modalHeading('Token de la credencial')
            ->modalDescription('Guárdalo ahora. No lo vas a poder ver otra vez.')
            ->fillForm(static fn (array $arguments): array => ['token' => $arguments['token'] ?? ''])
            ->schema([
                TextInput::make('token')
                    ->label('Token')
                    ->readOnly()
                    ->copyable(copyMessage: 'Copiado')
                    ->extraInputAttributes(['class' => 'font-mono']),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Listo');
    }

    /**
     * Las sedes activas de los comercios activos que el administrador alcanza.
     *
     * @return array<int, string>
     */
    private static function sedes(): array
    {
        $admin = self::admin();
        $query = Sede::query()->with('comercio')->whereRelation('comercio', 'activo', true);
        $query->getQuery()->where('activa', true)->orderBy('comercio_id')->orderBy('nombre');

        if (! $admin->esAdministradorDelGrupo()) {
            $query->getQuery()->where('comercio_id', $admin->comercio_id ?? 0);
        }

        $opciones = [];

        foreach ($query->get() as $sede) {
            $opciones[$sede->id] = ($sede->comercio->razon_social ?? '') . ' · ' . $sede->nombre;
        }

        return $opciones;
    }

    /**
     * @return list<AlcanceApi>
     */
    private static function alcancesElegidos(mixed $valores): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $valor): ?AlcanceApi => $valor instanceof AlcanceApi
                ? $valor
                : (is_string($valor) ? AlcanceApi::tryFrom($valor) : null),
            is_array($valores) ? $valores : [],
        )));
    }

    private static function admin(): User
    {
        $admin = Auth::user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
