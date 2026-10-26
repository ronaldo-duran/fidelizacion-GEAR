<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\Users\Pages\Concerns\AssignsReglaJsonPermission;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    use AssignsReglaJsonPermission;
    use VuelveALaLista;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveUser(User::query()->newModelInstance(), $data);
    }
}
