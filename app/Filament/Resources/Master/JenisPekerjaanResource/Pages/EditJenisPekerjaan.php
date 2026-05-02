<?php

namespace App\Filament\Resources\Master\JenisPekerjaanResource\Pages;

use App\Filament\Resources\Master\JenisPekerjaanResource;
use Filament\Resources\Pages\EditRecord;

class EditJenisPekerjaan extends EditRecord
{
    protected static string $resource = JenisPekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
