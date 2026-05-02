<?php

namespace App\Filament\Resources\Master\HariLiburResource\Pages;

use App\Filament\Resources\Master\HariLiburResource;
use Filament\Resources\Pages\EditRecord;

class EditHariLibur extends EditRecord
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
