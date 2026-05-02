<?php

namespace App\Filament\Resources\Master\TenagaAhliResource\Pages;

use App\Filament\Resources\Master\TenagaAhliResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenagaAhli extends ListRecords
{
    protected static string $resource = TenagaAhliResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
