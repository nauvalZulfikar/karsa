<?php

namespace App\Filament\Resources\Master\JenisPekerjaanResource\Pages;

use App\Filament\Resources\Master\JenisPekerjaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisPekerjaan extends ListRecords
{
    protected static string $resource = JenisPekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
