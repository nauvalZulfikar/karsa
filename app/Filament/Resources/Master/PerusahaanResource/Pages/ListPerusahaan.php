<?php

namespace App\Filament\Resources\Master\PerusahaanResource\Pages;

use App\Filament\Resources\Master\PerusahaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerusahaan extends ListRecords
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
