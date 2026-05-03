<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use Illuminate\Support\Facades\Storage;

class DokumenController extends Controller
{
    public function download(Dokumen $dokumen)
    {
        abort_unless(auth()->check(), 403);

        if (!Storage::disk('local')->exists($dokumen->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $filename = $dokumen->file_original_name ?? basename($dokumen->file_path);
        return Storage::disk('local')->download($dokumen->file_path, $filename);
    }
}
