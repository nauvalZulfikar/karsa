<?php

namespace App\Services;

use App\Models\ChatUpload;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\Response;

/**
 * Render isi file upload langsung di browser (tanpa download).
 * - PDF & gambar  : stream inline (native browser).
 * - XLS/XLSX/CSV  : PhpSpreadsheet -> tabel HTML.
 * - DOCX          : PhpWord -> HTML.
 * - DOC (binary)  : LibreOffice headless -> PDF (di-cache), lalu stream inline.
 */
class DocumentPreviewService
{
    public function render(ChatUpload $upload): Response
    {
        $path = $this->safePath((string) $upload->abs_path);
        if ($path === null) {
            abort(404, 'File tidak ditemukan atau path tidak valid.');
        }

        $ext = strtolower(pathinfo((string) $upload->original_name, PATHINFO_EXTENSION));

        return match ($upload->kind()) {
            'pdf'   => $this->inlineFile($path, 'application/pdf', $upload->original_name),
            'image' => $this->inlineFile($path, $upload->mime ?: 'image/jpeg', $upload->original_name),
            'sheet' => $this->html($this->spreadsheetHtml($path)),
            'word'  => $ext === 'docx'
                ? $this->html($this->wordHtml($path))
                : $this->docViaLibreOffice($path, $upload),
            default => $this->unsupported($upload),
        };
    }

    /** Cegah path-traversal: file harus berada di dalam storage/app. */
    private function safePath(string $abs): ?string
    {
        $real = realpath($abs);
        $root = realpath(storage_path('app'));
        if ($real === false || $root === false) {
            return null;
        }

        return str_starts_with($real, $root) && is_file($real) ? $real : null;
    }

    private function inlineFile(string $path, string $contentType, string $name): Response
    {
        return response()->file($path, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function html(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function spreadsheetHtml(string $path): string
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'xls') . '.html';
        $writer->save($tmp);
        $out = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $out;
    }

    private function wordHtml(string $path): string
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $tmp = tempnam(sys_get_temp_dir(), 'doc') . '.html';
        $writer->save($tmp);
        $out = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $out;
    }

    private function docViaLibreOffice(string $path, ChatUpload $upload): Response
    {
        $bin = $this->sofficeBinary();
        if ($bin === null) {
            return $this->unsupported($upload, 'Preview .doc butuh LibreOffice (belum terpasang di server). Silakan unduh file.');
        }

        $cacheDir = storage_path('app/private/dokumen/preview-cache');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cached = $cacheDir . '/' . md5($path) . '.pdf';

        if (! is_file($cached)) {
            // LibreOffice butuh HOME yang bisa ditulis untuk profil.
            $result = Process::timeout(90)
                ->env(['HOME' => $cacheDir])
                ->run([$bin, '--headless', '--convert-to', 'pdf', '--outdir', $cacheDir, $path]);

            $generated = $cacheDir . '/' . pathinfo($path, PATHINFO_FILENAME) . '.pdf';
            if ($result->successful() && is_file($generated)) {
                @rename($generated, $cached);
            }
        }

        if (is_file($cached)) {
            return $this->inlineFile($cached, 'application/pdf', $upload->original_name . '.pdf');
        }

        return $this->unsupported($upload, 'Konversi .doc gagal.');
    }

    private function sofficeBinary(): ?string
    {
        $candidates = array_filter([
            config('services.libreoffice.bin'),
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/opt/libreoffice/program/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ]);

        foreach ($candidates as $bin) {
            if (is_string($bin) && is_executable($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function unsupported(ChatUpload $upload, ?string $msg = null): Response
    {
        $msg ??= 'Format ini belum bisa ditampilkan di browser.';
        $name = e($upload->original_name);
        $body = <<<HTML
        <!doctype html><html lang="id"><head><meta charset="utf-8">
        <style>body{font-family:system-ui,sans-serif;color:#374151;display:flex;height:100vh;margin:0;
        align-items:center;justify-content:center;text-align:center;background:#f9fafb}
        .box{max-width:420px;padding:2rem}.f{font-weight:600;margin-bottom:.5rem;word-break:break-all}</style></head>
        <body><div class="box"><div class="f">{$name}</div><p>{$msg}</p></div></body></html>
        HTML;

        return $this->html($body);
    }
}
