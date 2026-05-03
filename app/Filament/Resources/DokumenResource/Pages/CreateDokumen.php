<?php

namespace App\Filament\Resources\DokumenResource\Pages;

use App\Filament\Resources\DokumenResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDokumen extends CreateRecord
{
    protected static string $resource = DokumenResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        if (!empty($data['file_path'])) {
            $fullPath = Storage::disk('local')->path($data['file_path']);
            if (file_exists($fullPath)) {
                $data['file_size'] = filesize($fullPath);
                $data['file_original_name'] = basename($data['file_path']);
            }
        }
        return $data;
    }
}
