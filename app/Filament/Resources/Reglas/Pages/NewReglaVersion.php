<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Pages;

use App\Actions\Reglas\VersionRegla;
use App\Filament\Resources\Reglas\Pages\Concerns\SavesThroughReglaActions;
use App\Filament\Resources\Reglas\ReglaResource;
use App\Filament\Resources\Reglas\Schemas\DefinicionFormMapper;
use App\Models\Regla;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Saving here never edits the open version: VersionRegla creates
 * `version + 1` and closes the previous one (hard rule 3.2).
 */
class NewReglaVersion extends EditRecord
{
    use SavesThroughReglaActions;

    protected static string $resource = ReglaResource::class;

    protected static ?string $title = 'Nueva versión';

    protected static ?string $breadcrumb = 'Nueva versión';

    private ?Regla $newVersion = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $regla = $this->getRecord();

        return $regla instanceof Regla ? DefinicionFormMapper::toForm($regla) : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Regla, 404);

        return $this->newVersion = $this->saveThroughActions(
            function (User $user) use ($record, $data): Regla {
                [$definicion, $source] = DefinicionFormMapper::fromForm($record->tipo, $data);

                return app(VersionRegla::class)(
                    $user,
                    $record,
                    $definicion,
                    ($data['activa'] ?? true) === true,
                    $source,
                    $this->parseVigenteDesde($data['vigente_desde'] ?? null),
                );
            },
        );
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Se guardó la versión ' . ($this->newVersion->version ?? '') . '.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('view', ['record' => $this->newVersion ?? $this->getRecord()]);
    }
}
