<?php

namespace App\Filament\Resources\Master\BidangResource\Pages;

use App\Filament\Resources\Master\BidangResource;
use Filament\Resources\Pages\EditRecord;

class EditBidang extends EditRecord
{
    protected static string $resource = BidangResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
