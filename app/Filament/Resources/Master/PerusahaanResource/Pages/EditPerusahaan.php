<?php

namespace App\Filament\Resources\Master\PerusahaanResource\Pages;

use App\Filament\Resources\Master\PerusahaanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPerusahaan extends EditRecord
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
