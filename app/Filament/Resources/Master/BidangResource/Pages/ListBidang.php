<?php

namespace App\Filament\Resources\Master\BidangResource\Pages;

use App\Filament\Resources\Master\BidangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBidang extends ListRecords
{
    protected static string $resource = BidangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
