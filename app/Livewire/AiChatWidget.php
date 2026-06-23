<?php

namespace App\Livewire;

use App\Models\ChatSession;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class AiChatWidget extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;
    public string $input = '';
    public array $messages = [];
    public array $attachments = [];
    public bool $stopped = false;
    public string $pendingAiBody = '';
    public ?int $sessionId = null;
    public bool $showHistory = false;

    #[Validate(['file', 'max:512000', 'mimes:pdf,xlsx,xls,docx,jpg,jpeg,png'])]
    public $uploadedFile;

    public $uploadedFiles = [];
    public bool $isBatchUploading = false;

    private array $aiMessages = [];

    public function mount(): void
    {
        $this->loadSession();
    }

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;

        if ($this->isOpen && empty($this->messages)) {
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => 'Halo! Saya asisten AI DPUTR. Tanya apa saja soal proyek, laporan harian, pengadaan, atau personil.',
            ];
            $this->saveSession();
        }
    }

    protected function loadSession(?int $id = null): void
    {
        if (!$id) {
            $id = session()->pull('chat_active_session');
        }
        $session = $id
            ? ChatSession::where('id', $id)->where('user_id', auth()->id())->first()
            : ChatSession::where('user_id', auth()->id())->latest()->first();

        if ($session && !empty($session->messages)) {
            $this->messages = $session->messages;
            $this->aiMessages = $session->ai_messages ?? $session->messages;
            $this->sessionId = $session->id;
        }
    }

    protected function saveSession(): void
    {
        $title = $this->generateTitle();

        $data = [
            'user_id' => auth()->id(),
            'title' => $title,
            'messages' => $this->messages,
            'ai_messages' => $this->aiMessages ?: $this->messages,
        ];

        if ($this->sessionId) {
            ChatSession::where('id', $this->sessionId)->update($data);
        } else {
            $session = ChatSession::create($data);
            $this->sessionId = $session->id;
        }
    }

    private function generateTitle(): ?string
    {
        foreach ($this->messages as $msg) {
            if ($msg['role'] === 'user') {
                $text = preg_replace('/📎[^\n]+/', '', $msg['content']);
                $text = trim(preg_replace('/\s+/', ' ', $text));
                if (mb_strlen($text) > 0) {
                    return mb_substr($text, 0, 50);
                }
            }
        }
        return null;
    }

    public function newChat(): void
    {
        $this->sessionId = null;
        $this->messages = [];
        $this->aiMessages = [];
        $this->input = '';
        $this->pendingAiBody = '';
        $this->attachments = [];
        $this->showHistory = false;

        $this->messages = [
            ['role' => 'assistant', 'content' => 'Chat baru dimulai. Apa yang bisa saya bantu?'],
        ];
        $this->saveSession();
        $this->redirect(request()->header('Referer', '/admin'));
    }

    public function switchSession(int $id): void
    {
        session()->put('chat_active_session', $id);
        $this->redirect(request()->header('Referer', '/admin'));
    }

    public function deleteSession(int $id): void
    {
        ChatSession::where('id', $id)->where('user_id', auth()->id())->delete();

        if ($this->sessionId === $id) {
            $this->messages = [];
            $this->aiMessages = [];
            $this->sessionId = null;
            $this->loadSession();
        }
    }

    public function toggleHistory(): void
    {
        $this->showHistory = !$this->showHistory;
    }

    public function getSessions(): array
    {
        return ChatSession::where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'created_at', 'updated_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title ?: 'Chat ' . $s->created_at->format('d M H:i'),
                'date' => $s->updated_at->diffForHumans(),
                'active' => $s->id === $this->sessionId,
            ])
            ->toArray();
    }

    public function updatedUploadedFile(): void
    {
        $this->validate();
        if (!$this->uploadedFile) return;

        $this->attachments[] = $this->ingestUpload($this->uploadedFile);
        $this->uploadedFile = null;
    }

    public function updatedUploadedFiles(): void
    {
        if (empty($this->uploadedFiles)) return;
        $this->isBatchUploading = true;

        foreach ($this->uploadedFiles as $file) {
            $this->attachments[] = $this->ingestUpload($file);
        }

        $this->uploadedFiles = [];
        $this->isBatchUploading = false;
    }

    /**
     * Simpan file, catat ChatUpload, extract teks-layer cepat (Smalot).
     * Kalau PDF hasil scan → OCR berat dilempar ke queue (OcrChatUpload) supaya
     * request web nggak nahan worker. Return ['name','path'] buat attachments.
     */
    private function ingestUpload($file): array
    {
        $name   = $file->getClientOriginalName();
        $stored = $file->store('ai_chat_uploads', 'local');
        $abs    = Storage::disk('local')->path($stored);
        $mime   = $file->getMimeType();
        $size   = $file->getSize();

        $upload = \App\Models\ChatUpload::create([
            'user_id'       => auth()->id(),
            'original_name' => $name,
            'abs_path'      => $abs,
            'mime'          => $mime,
            'size_bytes'    => $size,
        ]);

        if (str_contains((string) $mime, 'pdf') || str_ends_with(strtolower($name), '.pdf')) {
            $text = '';
            try {
                $text = trim((new \Smalot\PdfParser\Parser())->parseFile($abs)->getText());
            } catch (\Throwable) {
            }

            $isScanned = mb_strlen($text) < 100;
            $upload->update([
                'ocr_text'       => mb_substr($text, 0, 15000),
                'is_scanned_pdf' => $isScanned,
            ]);

            if ($isScanned) {
                \App\Jobs\OcrChatUpload::dispatch($upload->id);
            }
        }

        return ['name' => $name, 'path' => $abs];
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            array_splice($this->attachments, $index, 1);
        }
    }

    public function send(): void
    {
        $text = trim($this->input);
        if (empty($text) && empty($this->attachments)) {
            return;
        }

        $aiBody = $text;
        $displayBody = $text;
        if (!empty($this->attachments)) {
            $aiBody .= "\n\n[File terlampir]\n";
            $fileLabels = [];
            foreach ($this->attachments as $att) {
                $aiBody .= "- {$att['name']} → {$att['path']}\n";
                $fileLabels[] = "📎 {$att['name']}";
            }
            $displayBody .= "\n\n" . implode("\n", $fileLabels);
        }

        $this->pendingAiBody = $aiBody;
        $this->stopped = false;
        $this->messages[] = ['role' => 'user', 'content' => $displayBody];
        $this->aiMessages[] = ['role' => 'user', 'content' => $aiBody];
        $this->input = '';
        $this->attachments = [];

        $this->saveSession();
        $this->dispatch('ai-fetch');
    }

    public function stopAi(): void
    {
        $this->stopped = true;
    }

    public function editMessage(int $index): void
    {
        if (!isset($this->messages[$index]) || $this->messages[$index]['role'] !== 'user') {
            return;
        }

        $this->input = $this->messages[$index]['content'];
        $this->messages = array_values(array_slice($this->messages, 0, $index));
        $this->aiMessages = array_values(array_slice($this->aiMessages, 0, $index));
        $this->saveSession();
    }

    public function fetchAi(): void
    {
        if (empty($this->messages)) {
            return;
        }
        $last = end($this->messages);
        if (($last['role'] ?? '') !== 'user') {
            return;
        }

        if (empty($this->aiMessages) && $this->sessionId) {
            $session = ChatSession::find($this->sessionId);
            if ($session) {
                $this->aiMessages = $session->ai_messages ?? $session->messages;
            }
        }

        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        try {
            $reply = app(AiChatService::class)->chat($this->aiMessages ?: $this->messages);
        } catch (\Throwable $e) {
            $reply = 'Maaf, terjadi kesalahan: ' . $e->getMessage();
        }

        if ($this->stopped) {
            $this->stopped = false;
            $this->messages[] = ['role' => 'assistant', 'content' => '⏹ Dihentikan oleh user.'];
            $this->aiMessages[] = ['role' => 'assistant', 'content' => '⏹ Dihentikan oleh user.'];
            $this->saveSession();
            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
        $this->aiMessages[] = ['role' => 'assistant', 'content' => $reply];
        $this->pendingAiBody = '';
        $this->saveSession();
    }

    public function render()
    {
        return view('livewire.ai-chat-widget');
    }
}
