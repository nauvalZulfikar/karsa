<?php

namespace App\Filament\Resources\RencanaPengadaanResource\Pages;

use App\Filament\Resources\RencanaPengadaanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRencanaPengadaans extends ListRecords
{
    protected static string $resource = RencanaPengadaanResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
