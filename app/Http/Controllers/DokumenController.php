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

    public function downloadGenerated(string $encodedPath)
    {
        abort_unless(auth()->check(), 403);

        $path = base64_decode($encodedPath, true);
        if (!$path || !str_starts_with($path, 'dokumen/generated/')) {
            abort(400, 'Path tidak valid.');
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('local')->download($path, basename($path));
    }
}
