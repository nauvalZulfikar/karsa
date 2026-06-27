<?php

namespace App\Jobs;

use App\Models\ChatUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Smalot\PdfParser\Parser;

/**
 * Ekstraksi teks-layer PDF (Smalot) + deteksi scan, di background.
 *
 * Dulu jalan SINKRON di dalam request upload (`AiChatWidget::ingestUpload`):
 * Smalot pure-PHP butuh 4–13 dtk untuk PDF teks besar → user nahan napas tiap upload,
 * batch upload jadi penjumlahan. Padahal parser (Kickoff/Kontrak/Rab) toh selalu
 * re-extract teks fresh sendiri; probe upload cuma dipakai untuk (a) set is_scanned_pdf
 * dan (b) cache fallback ocr_text. Jadi aman dilempar ke queue (worker shaka-ai-queue).
 *
 * Selama job ini belum kelar, gate `AiChatService::ocrPending()` mengembalikan
 * "diproses" (lihat probe pending key) → parse tool menunggu, TIDAK OCR sinkron.
 */
class ProbeChatUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public int $backoff = 15;

    public function __construct(public int $chatUploadId) {}

    public function handle(): void
    {
        $upload = ChatUpload::find($this->chatUploadId);
        if (! $upload || ! is_file((string) $upload->abs_path)) {
            $this->finish();

            return;
        }

        // Idempotent: sudah punya teks layak (probe sebelumnya / OCR sudah kelar) →
        // JANGAN timpa dengan hasil Smalot kosong (mis. saat re-probe scan setelah cache expired).
        if (mb_strlen((string) $upload->ocr_text) >= 200) {
            $this->finish();

            return;
        }

        $text = '';
        try {
            $text = trim((new Parser)->parseFile($upload->abs_path)->getText());
        } catch (\Throwable) {
            // PDF rusak / terenkripsi / hasil scan → teks kosong, ditangani sebagai scan di bawah.
        }

        $isScanned = mb_strlen($text) < 100;
        $upload->update([
            'ocr_text' => mb_substr($text, 0, 15000),
            'is_scanned_pdf' => $isScanned,
        ]);

        // Scan → OCR berat (render halaman + Vision) tetap job terpisah biar idempotent.
        if ($isScanned) {
            OcrChatUpload::dispatch($upload->id);
        }

        $this->finish();
    }

    public function failed(\Throwable $e): void
    {
        // Jangan biarkan user nyangkut di "diproses" kalau probe gagal.
        $this->finish();
        logger()->warning("ProbeChatUpload gagal (id={$this->chatUploadId}): {$e->getMessage()}");
    }

    /** Probe selesai (sukses/gagal): hapus penanda "lagi jalan", set penanda "sudah diprobe". */
    private function finish(): void
    {
        Cache::forget(self::pendingKey($this->chatUploadId));
        Cache::put(self::doneKey($this->chatUploadId), 1, 86400);
    }

    public static function doneKey(int $id): string
    {
        return "probe_done:{$id}";
    }

    public static function pendingKey(int $id): string
    {
        return "probe_pending:{$id}";
    }
}
