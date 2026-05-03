<?php

namespace App\Filament\Resources\RealisasiPengadaanResource\Pages;

use App\Filament\Resources\RealisasiPengadaanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRealisasiPengadaans extends ListRecords
{
    protected static string $resource = RealisasiPengadaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
