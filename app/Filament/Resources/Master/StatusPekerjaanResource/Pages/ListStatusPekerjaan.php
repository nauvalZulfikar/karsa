<?php

namespace App\Filament\Resources\Master\StatusPekerjaanResource\Pages;

use App\Filament\Resources\Master\StatusPekerjaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStatusPekerjaan extends ListRecords
{
    protected static string $resource = StatusPekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
