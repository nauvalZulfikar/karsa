<?php

namespace App\Jobs;

use App\Models\ChatUpload;
use App\Services\PdfOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * OCR PDF hasil scan di background.
 *
 * OCR berat (render tiap halaman jadi gambar + Vision API per halaman) dulu jalan
 * sinkron di dalam request chat → nahan worker FPM 240s+ → 504 / situs hang.
 * Sekarang dilempar ke queue (worker `karta-queue`), hasilnya di-cache ke
 * ChatUpload.ocr_text. Parser tinggal baca cache itu (instan, tanpa OCR ulang).
 */
class OcrChatUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** OCR multi-halaman + Vision bisa lama; jangan dipotong worker default 60s. */
    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $chatUploadId,
        public int $maxPages = 8,
    ) {}

    public function handle(PdfOcrService $ocr): void
    {
        $upload = ChatUpload::find($this->chatUploadId);
        if (!$upload || !is_file((string) $upload->abs_path)) {
            return;
        }

        // Idempotent: sudah pernah di-OCR dengan hasil layak → skip (hemat biaya Vision).
        if (mb_strlen((string) $upload->ocr_text) >= 200) {
            return;
        }

        $text = trim($ocr->ocr($upload->abs_path, $this->maxPages));

        $upload->update([
            'is_scanned_pdf' => true,
            'ocr_text'       => mb_substr($text, 0, 20000),
        ]);

        // Tandai selesai walau teks tipis/gagal-baca, supaya gate chat nggak loop
        // "masih diproses" selamanya — biar parser yang munculin error sebenarnya.
        Cache::put($this->doneKey($this->chatUploadId), 1, 86400);
    }

    public function failed(\Throwable $e): void
    {
        // Habis percobaan: tetap tandai selesai biar user nggak nyangkut di "diproses".
        Cache::put($this->doneKey($this->chatUploadId), 1, 86400);
        logger()->warning("OcrChatUpload gagal (id={$this->chatUploadId}): {$e->getMessage()}");
    }

    public static function doneKey(int $id): string
    {
        return "ocr_done:{$id}";
    }
}
