<?php

namespace App\Filament\Resources\Master\StatusPekerjaanResource\Pages;

use App\Filament\Resources\Master\StatusPekerjaanResource;
use Filament\Resources\Pages\EditRecord;

class EditStatusPekerjaan extends EditRecord
{
    protected static string $resource = StatusPekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
