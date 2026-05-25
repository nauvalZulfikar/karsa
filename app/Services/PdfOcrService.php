<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * OCR PDF hasil scan via OpenAI Vision.
 *
 * Strategy:
 * 1. Convert PDF pages → PNG via Python helper (uses pymupdf — pure Python, no external bins)
 * 2. Send each PNG (base64) to gpt-4o-mini vision API
 * 3. Concatenate extracted text
 *
 * Prereq: Python 3.x in PATH + `pip install pymupdf`
 */
class PdfOcrService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function ocr(string $pdfPath, int $maxPages = 5): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF tidak ditemukan: {$pdfPath}");
        }

        $pages = $this->renderPagesToPng($pdfPath, $maxPages);
        if (empty($pages)) {
            throw new \RuntimeException('Gagal render halaman PDF jadi gambar.');
        }

        $allText = [];
        foreach ($pages as $idx => $pngBase64) {
            $pageNum = $idx + 1;
            $text = $this->extractTextFromImage($pngBase64);
            $allText[] = "=== HALAMAN {$pageNum} ===\n" . $text;
        }

        return implode("\n\n", $allText);
    }

    /**
     * Render PDF pages to PNG via Python helper, return list of base64-encoded PNG strings.
     */
    private function renderPagesToPng(string $pdfPath, int $maxPages): array
    {
        $script = base_path('scripts/pdf_ocr_render.py');
        if (!file_exists($script)) {
            throw new \RuntimeException("Python helper missing: {$script}");
        }

        $cmd = sprintf('python "%s" "%s" %d 2>&1', $script, $pdfPath, $maxPages);
        $output = shell_exec($cmd);
        if (!$output) {
            throw new \RuntimeException("Python OCR render returned empty. Cek `python` di PATH + `pip install pymupdf`.");
        }

        $lines = array_filter(array_map('trim', explode("\n", $output)), fn ($l) => $l !== '');
        $pages = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, 'ERROR:')) {
                throw new \RuntimeException(substr($line, 7));
            }
            if (str_starts_with($line, 'PAGE:')) {
                $pages[] = substr($line, 5);
            }
        }
        return $pages;
    }

    private function extractTextFromImage(string $pngBase64): string
    {
        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->retry(2, 2000)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'       => 'gpt-4o-mini',
                'max_tokens'  => 2000,
                'messages'    => [
                    ['role' => 'system', 'content' => 'Kamu OCR engine. Extract SEMUA teks dari gambar persis seperti tampilannya. Pertahankan struktur tabel pakai pipe |. Jangan tambah komentar atau ringkasan.'],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'Extract semua teks dari gambar ini:'],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:image/png;base64,' . $pngBase64,
                        ]],
                    ]],
                ],
            ]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            throw new \RuntimeException("OpenAI Vision API error: {$err}");
        }

        return $response->json('choices.0.message.content', '');
    }
}
