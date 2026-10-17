<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventosCalificados\Schemas;

use App\Enums\TipoEventoCalificado;
use App\Models\Comercio;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Registration form. A merchant administrator only sees their merchant and
 * only school enrollments; the action checks it again.
 */
class EventoCalificadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Evento')
                ->columns(2)
                ->schema([
                    TextInput::make('documento')
                        ->label('Documento del cliente')
                        ->required()
                        ->maxLength(20),
                    Select::make('tipo')
                        ->label('Tipo de evento')
                        ->options(self::tipoOptions(...))
                        ->required()
                        ->live(),
                    Select::make('comercio_id')
                        ->label('Comercio donde ocurrió')
                        ->options(self::comercioOptions(...))
                        ->required(),
                    DatePicker::make('vigente_desde')
                        ->label('Vigente desde')
                        ->default(now())
                        ->required(),
                    DatePicker::make('vigente_hasta')
                        ->label('Vigente hasta')
                        ->helperText(static fn (Get $get): string => self::isMatricula($get)
                            ? 'La fecha de fin de la matrícula la define el colegio.'
                            : 'Vacío: 12 meses desde el inicio.')
                        ->required(self::isMatricula(...)),
                    FileUpload::make('soporte_path')
                        ->label('Soporte (PDF o imagen)')
                        ->disk('local')
                        ->directory('soportes-eventos')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->required(),
                ]),
        ]);
    }

    private static function isMatricula(Get $get): bool
    {
        return $get('tipo') === TipoEventoCalificado::MatriculaColegio->value;
    }

    /**
     * @return array<string, string>
     */
    private static function tipoOptions(): array
    {
        $user = Auth::user();
        $tipos = $user instanceof User && $user->esAdministradorDelGrupo()
            ? TipoEventoCalificado::cases()
            : [TipoEventoCalificado::MatriculaColegio];

        $options = [];

        foreach ($tipos as $tipo) {
            $options[$tipo->value] = $tipo->getLabel();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function comercioOptions(): array
    {
        $user = Auth::user();
        $query = Comercio::query();

        if (! $user instanceof User || ! $user->esAdministradorDelGrupo()) {
            $query->getQuery()->where('id', $user instanceof User ? ($user->comercio_id ?? 0) : 0);
        }

        return $query->get()
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }
}
