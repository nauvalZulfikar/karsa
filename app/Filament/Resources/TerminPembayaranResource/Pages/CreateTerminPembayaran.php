<?php

namespace App\Filament\Resources\TerminPembayaranResource\Pages;

use App\Filament\Resources\TerminPembayaranResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTerminPembayaran extends CreateRecord
{
    protected static string $resource = TerminPembayaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
