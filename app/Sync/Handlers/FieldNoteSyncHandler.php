<?php

namespace App\Sync\Handlers;

use App\Models\FieldNote;
use App\Sync\AbstractOfflineSyncHandler;

class FieldNoteSyncHandler extends AbstractOfflineSyncHandler
{
    protected function modelClass(): string
    {
        return FieldNote::class;
    }

    protected function userScoped(): bool
    {
        return true;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function attributesFromValidated(array $data): array
    {
        return [
            'name' => $data['name'],
        ];
    }
}
