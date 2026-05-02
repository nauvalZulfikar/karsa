<?php

namespace App\Filament\Resources\Master\HariLiburResource\Pages;

use App\Filament\Resources\Master\HariLiburResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHariLibur extends ListRecords
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
