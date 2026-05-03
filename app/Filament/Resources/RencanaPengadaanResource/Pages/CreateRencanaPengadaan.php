<?php

namespace App\Filament\Resources\RencanaPengadaanResource\Pages;

use App\Filament\Resources\RencanaPengadaanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRencanaPengadaan extends CreateRecord
{
    protected static string $resource = RencanaPengadaanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
