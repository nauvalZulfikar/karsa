<?php

namespace App\Filament\Resources\Master\TenagaAhliResource\Pages;

use App\Filament\Resources\Master\TenagaAhliResource;
use Filament\Resources\Pages\EditRecord;

class EditTenagaAhli extends EditRecord
{
    protected static string $resource = TenagaAhliResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
