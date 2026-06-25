<?php

namespace Tests\Feature;

use App\Jobs\ParseChatDocument;
use App\Models\ChatUpload;
use App\Models\User;
use App\Services\AiChatService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Parsing async (backend lokal lambat): parse_* tidak boleh blok request web > 300s.
 * driver=ollama -> dispatch ParseChatDocument + balikin "processing"; sekali hasil
 * di-cache, tool balikin datanya. (OpenAI tetap sinkron, tak disentuh test ini.)
 */
class AiAsyncParseTest extends TestCase
{
    use RefreshDatabase;

    private AiChatService $svc;
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleAndPermissionSeeder::class, AdminSeeder::class]);
        $this->actingAs(User::where('email', 'admin@dputr.go.id')->first());
        config(['services.llm_batch.driver' => 'ollama']); // backend lambat -> async
        $this->svc = app(AiChatService::class);
        $this->tmp = tempnam(sys_get_temp_dir(), 'spk') . '.pdf';
        file_put_contents($this->tmp, '%PDF-1.4 dummy');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        parent::tearDown();
    }

    private function tool(string $name, array $input): array
    {
        $m = new ReflectionMethod($this->svc, 'executeTool');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $name, $input);
    }

    public function test_parse_qwen_dilempar_ke_queue_dan_balikin_processing(): void
    {
        Bus::fake();
        $u = ChatUpload::create(['original_name' => 'spk.pdf', 'abs_path' => $this->tmp, 'mime' => 'application/pdf']);

        $res = $this->tool('parse_kontrak_pdf', ['file_path' => $this->tmp]);

        $this->assertSame('processing', $res['status'] ?? null);
        Bus::assertDispatched(ParseChatDocument::class, fn ($j) => $j->uploadId === $u->id && $j->type === 'kontrak');
    }

    public function test_parse_kedua_kali_tidak_dispatch_ulang_saat_pending(): void
    {
        Bus::fake();
        $u = ChatUpload::create(['original_name' => 'spk.pdf', 'abs_path' => $this->tmp, 'mime' => 'application/pdf']);

        $this->tool('parse_kontrak_pdf', ['file_path' => $this->tmp]); // dispatch #1 + set pending
        $this->tool('parse_kontrak_pdf', ['file_path' => $this->tmp]); // pending -> JANGAN dispatch lagi

        Bus::assertDispatchedTimes(ParseChatDocument::class, 1);
    }

    public function test_hasil_cache_dikembalikan_sebagai_data(): void
    {
        $u = ChatUpload::create(['original_name' => 'spk.pdf', 'abs_path' => $this->tmp, 'mime' => 'application/pdf']);
        Cache::put(ParseChatDocument::resultKey($u->id, 'kontrak'), [
            'pekerjaan' => ['no_spk' => '602.1/09/SPK/X', 'nama_pekerjaan' => 'Kajian Geoteknik'],
        ], 600);

        $res = $this->tool('parse_kontrak_pdf', ['file_path' => $this->tmp]);

        $this->assertTrue($res['ok'] ?? false);
        $this->assertSame('602.1/09/SPK/X', $res['data']['pekerjaan']['no_spk']);
    }
}
