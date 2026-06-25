<?php

namespace App\Jobs;

use App\Models\ChatUpload;
use App\Services\KickoffParserService;
use App\Services\KontrakParserService;
use App\Services\RabParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Parse dokumen (extractFields via LLM) di background.
 *
 * extractFields lewat model LOKAL (Qwen) bisa makan menit-an — melebihi 300s wall
 * (php/fpm/nginx) kalau jalan sinkron di request chat → 504. Job ini melemparnya ke
 * queue (mirip OcrChatUpload), hasilnya di-cache. parse_* tool baca cache itu.
 *
 * Hanya dipakai saat backend batch lambat (driver=ollama); kalau OpenAI, AiChatService
 * tetap parse sinkron (cepat, < 300s) dan job ini tak pernah di-dispatch.
 */
class ParseChatDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Qwen extractFields (+ vision jadwal fallback) bisa lama; beri kelonggaran. */
    public int $timeout = 1100;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $uploadId,
        public string $type,
        public string $path,
    ) {}

    public function handle(): void
    {
        $service = match ($this->type) {
            'kak'                  => app(KickoffParserService::class),
            'kontrak', 'penawaran' => app(KontrakParserService::class),
            'rab'                  => app(RabParserService::class),
            default                => null,
        };

        try {
            if (!$service) {
                $this->store(['__error' => "type '{$this->type}' tidak dikenal (kak/kontrak/rab/penawaran)"]);
                return;
            }
            $this->store($service->parse($this->path));
        } catch (\Throwable $e) {
            $this->store(['__error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->store(['__error' => $e->getMessage()]);
    }

    private function store(array $payload): void
    {
        Cache::put(self::resultKey($this->uploadId, $this->type), $payload, 86400);
        Cache::forget(self::pendingKey($this->uploadId, $this->type));
    }

    public static function resultKey(int $id, string $type): string
    {
        return "parse_result:{$id}:{$type}";
    }

    public static function pendingKey(int $id, string $type): string
    {
        return "parse_pending:{$id}:{$type}";
    }
}
