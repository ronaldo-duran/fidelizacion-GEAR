<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\Users\Pages\Concerns\AssignsReglaJsonPermission;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    use AssignsReglaJsonPermission;
    use VuelveALaLista;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof User, 404);

        return $this->saveUser($record, $data);
    }
}
