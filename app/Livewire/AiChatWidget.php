<?php

namespace App\Livewire;

use App\Services\AiChatService;
use Livewire\Component;

class AiChatWidget extends Component
{
    public bool $isOpen = false;
    public string $input = '';
    public array $messages = [];

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;

        if ($this->isOpen && empty($this->messages)) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => 'Halo! Saya asisten AI DPUTR. Tanya apa saja soal proyek, laporan harian, pengadaan, atau personil. Contoh: "berapa proyek yang kritis?" atau "tampilkan laporan hari ini".',
            ];
        }
    }

    /**
     * Phase 1: append user message instantly + clear input,
     * then dispatch browser event to trigger AI fetch in a separate request.
     * This way the user bubble appears immediately while AI is "thinking".
     */
    public function send(): void
    {
        $text = trim($this->input);
        if (empty($text)) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->input = '';

        $this->dispatch('ai-fetch');
    }

    /**
     * Phase 2: actually call OpenAI. Triggered by browser event after Phase 1 paints.
     */
    public function fetchAi(): void
    {
        if (empty($this->messages)) {
            return;
        }
        $last = end($this->messages);
        if (($last['role'] ?? '') !== 'user') {
            return; // safety: only fetch when last msg is from user
        }

        try {
            $reply = app(AiChatService::class)->chat($this->messages);
        } catch (\Throwable $e) {
            $reply = 'Maaf, terjadi kesalahan: ' . $e->getMessage();
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
    }

    public function render()
    {
        return view('livewire.ai-chat-widget');
    }
}
