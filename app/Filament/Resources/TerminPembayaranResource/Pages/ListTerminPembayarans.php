<?php

namespace App\Filament\Resources\TerminPembayaranResource\Pages;

use App\Filament\Resources\TerminPembayaranResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTerminPembayarans extends ListRecords
{
    protected static string $resource = TerminPembayaranResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
