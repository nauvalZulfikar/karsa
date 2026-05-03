<?php

namespace App\Filament\Resources\RealisasiPengadaanResource\Pages;

use App\Filament\Resources\RealisasiPengadaanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRealisasiPengadaan extends CreateRecord
{
    protected static string $resource = RealisasiPengadaanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['perusahaan_id'] = auth()->user()->perusahaan_id ?? $data['perusahaan_id'] ?? null;
        $data['status'] = 'submitted';
        return $data;
    }
}
