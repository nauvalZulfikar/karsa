<?php

namespace Tests\Feature;

use App\Services\AiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FASE C — failover: kalau backend LLM utama (mis. Ollama lokal) tak terjangkau,
 * chatbot otomatis jatuh ke OpenAI, bukan crash.
 */
class AiFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_utama_mati_jatuh_ke_openai(): void
    {
        config([
            'services.llm.driver'        => 'ollama',
            'services.llm.base_url'      => 'http://127.0.0.1:9/v1',   // port mati
            'services.llm.model'         => 'qwen3:8b-32k',
            'services.llm.api_key'       => 'ollama',
            'services.llm.timeout'       => 5,
            'services.llm.fallback'      => true,
            'services.llm.fallback_url'  => 'https://api.openai.com/v1/chat/completions',
            'services.llm.fallback_model' => 'gpt-4o-mini',
            'services.llm.fallback_key'  => 'sk-test-key',
        ]);

        Http::fake([
            '127.0.0.1:9/*'    => fn () => throw new ConnectionException('Connection refused'),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message'       => ['role' => 'assistant', 'content' => 'Jawaban dari fallback OpenAI.'],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $reply = (new AiChatService())->chat([['role' => 'user', 'content' => 'halo']]);

        $this->assertStringContainsString('fallback OpenAI', $reply);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com'));
    }
}
