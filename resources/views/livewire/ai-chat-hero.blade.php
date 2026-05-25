<div
    x-data="{
        msgs: @entangle('messages'),
        drag: false,
        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msglist;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        dropFiles(e) {
            this.drag = false;
            const files = e.dataTransfer.files;
            if (!files.length) return;
            this.$refs.heroBatchFile.files = files;
            this.$refs.heroBatchFile.dispatchEvent(new Event('change'));
        },
        renderMd(text) { return window._heroRenderMd(text); },
        renderUserMsg(text) { return window._heroRenderUser(text); }
    }"
    x-effect="msgs.length && scrollBottom()"
    x-init="scrollBottom()"
    @ai-fetch.window="setTimeout(() => $wire.fetchAi(), 50)"
    @dragover.prevent="drag = true"
    @dragleave.prevent="drag = false"
    @drop.prevent="dropFiles($event)"
    :style="drag ? 'outline:2px dashed #f59e0b; outline-offset:-4px;' : ''"
    class="ai-chat-hero-root flex flex-col overflow-hidden"
>
    <style>
        .ai-chat-hero-root {
            height: 78vh;
            max-height: 78vh;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .dark .ai-chat-hero-root {
            background: #1f2937;
            border-color: #374151;
        }
        .ai-chat-header {
            padding: 14px 20px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .ai-chat-header-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ai-chat-header h3 {
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            margin: 0;
            line-height: 1.2;
        }
        .ai-chat-header p {
            color: #fef3c7;
            font-size: 11px;
            margin: 2px 0 0 0;
            line-height: 1.2;
        }
        .ai-chat-msglist {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 16px 20px;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scroll-behavior: smooth;
        }
        .dark .ai-chat-msglist { background: #111827; }
        .ai-chat-row {
            display: flex;
            flex-shrink: 0;
            width: 100%;
        }
        .ai-chat-row-user { justify-content: flex-end; }
        .ai-chat-row-assistant { justify-content: flex-start; }
        .ai-chat-bubble {
            max-width: 80%;
            padding: 10px 16px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: normal;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            position: relative;
            overflow: visible;
        }
        .ai-chat-bubble strong { font-weight: 700; }
        .ai-chat-bubble-user {
            background: #f59e0b;
            color: #ffffff !important;
            border-radius: 20px 20px 6px 20px;
        }
        .ai-chat-bubble-assistant {
            background: #ffffff;
            color: #1f2937;
            border-radius: 20px 20px 20px 6px;
            border: 1px solid #e5e7eb;
        }
        .dark .ai-chat-bubble-assistant {
            background: #374151;
            color: #f3f4f6;
            border-color: #4b5563;
        }
        .ai-chat-bubble-assistant a { color: #f59e0b; text-decoration: underline; }
        .ai-chat-row-user:hover .ai-chat-edit-btn { opacity: 1; }
        .ai-chat-edit-btn {
            width: 24px; height: 24px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none; cursor: pointer;
            color: #fff; font-size: 12px;
            display: inline-flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.15s;
            margin-right: 4px; vertical-align: middle;
        }
        .ai-chat-edit-btn:hover { background: rgba(255,255,255,0.4); }
        .ai-chat-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.15);
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            margin: 2px 0;
        }
        .ai-chat-typing {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px 20px 20px 6px;
            padding: 12px 18px;
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .dark .ai-chat-typing {
            background: #374151;
            border-color: #4b5563;
        }
        .ai-chat-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #f59e0b;
            animation: ai-bounce 1s infinite;
        }
        @keyframes ai-bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-5px); opacity: 1; }
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .ai-chat-suggest {
            flex-shrink: 0;
            padding: 10px 20px 4px;
            background: #ffffff;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .dark .ai-chat-suggest { background: #1f2937; }
        .ai-chat-suggest-btn {
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #374151;
            border: none;
            cursor: pointer;
            transition: background 0.15s;
        }
        .ai-chat-suggest-btn:hover { background: #fef3c7; }
        .ai-chat-suggest-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .dark .ai-chat-suggest-btn { background: #374151; color: #e5e7eb; }
        .dark .ai-chat-suggest-btn:hover { background: #4b5563; }
        .ai-chat-input-bar {
            flex-shrink: 0;
            padding: 12px 16px;
            display: flex;
            gap: 8px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            align-items: center;
        }
        .dark .ai-chat-input-bar {
            background: #1f2937;
            border-top-color: #374151;
        }
        .ai-chat-input {
            flex: 1;
            font-size: 14px;
            padding: 10px 16px;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #1f2937;
            outline: none;
        }
        .ai-chat-input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245,158,11,0.2);
        }
        .ai-chat-input:disabled { opacity: 0.5; }
        .dark .ai-chat-input {
            background: #374151;
            color: #f3f4f6;
            border-color: #4b5563;
        }
        .ai-chat-send-btn, .ai-chat-stop-btn {
            width: 40px; height: 40px;
            border-radius: 50%;
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .ai-chat-send-btn { background: #f59e0b; }
        .ai-chat-send-btn:hover { background: #d97706; }
        .ai-chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .ai-chat-stop-btn { background: #374151; box-shadow: inset 0 0 0 2px #6b7280; }
        .ai-chat-stop-btn:hover { background: #4b5563; }
        .dark .ai-chat-stop-btn { background: #1f2937; box-shadow: inset 0 0 0 2px #9ca3af; }
    </style>

    <script>
        window._heroRenderMd = function(text) {
            if (!text) return '';
            var s = String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, function(m, label, url) {
                if (url.indexOf('/dokumen/') !== -1) {
                    return '<a href="' + url + '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:6px 12px;background:#f59e0b;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📄 ' + label + '</a>';
                }
                return '<a href="' + url + '" target="_blank" style="color:#f59e0b;text-decoration:underline;">' + label + '</a>';
            });
            s = s.replace(/^#{1,4}\s+(.+)$/gm, '<strong style="display:block;margin:6px 0 2px;">$1</strong>');
            s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
            s = s.replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.15);padding:1px 4px;border-radius:3px;font-size:12px;">$1</code>');
            s = s.replace(/^---+$/gm, '<hr style="border:none;border-top:1px solid rgba(128,128,128,0.3);margin:6px 0;">');
            s = s.replace(/^[-•]\s+(.+)$/gm, '&bull; $1');
            s = s.replace(/\n/g, '<br>');
            return s;
        };
        window._heroRenderUser = function(text) {
            if (!text) return '';
            var s = String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            s = s.replace(/📎\s*(\S[^\n]*)/g, '<span style="display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,0.15);padding:3px 10px;border-radius:10px;font-size:12px;margin:2px 0;">📎 $1</span>');
            s = s.replace(/\n/g, '<br>');
            return s;
        };
    </script>

    <input type="file" wire:model="uploadedFiles" x-ref="heroBatchFile" multiple accept=".pdf,.xlsx,.xls,.docx,.jpg,.jpeg,.png" style="display:none">

    {{-- Header --}}
    <div class="ai-chat-header" style="position:relative;" x-data="{ historyOpen: false }">
        <div class="ai-chat-header-icon">
            <svg style="width:20px;height:20px;color:#fff;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/>
            </svg>
        </div>
        <div style="flex:1;min-width:0;">
            <h3>Apa yang bisa saya bantu hari ini?</h3>
            <p>Tanya, atau minta saya update data — saya akan konfirmasi dulu</p>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="button" wire:click="newChat" title="Chat Baru"
                    style="background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;">
                ＋
            </button>
            <button type="button" @click="historyOpen = !historyOpen" title="Riwayat Chat"
                    style="background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;">
                ☰
            </button>
        </div>

        {{-- History dropdown --}}
        <div x-show="historyOpen" x-cloak @click.outside="historyOpen = false"
             style="position:absolute;top:100%;left:0;right:0;z-index:10;background:var(--km-card-bg, #f9fafb);border:1px solid var(--km-border, #e5e7eb);border-radius:0 0 12px 12px;max-height:240px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.15);">
            @php $sessions = $this->getSessions(); @endphp
            @forelse($sessions as $s)
                <div style="display:flex;align-items:center;padding:10px 20px;gap:8px;cursor:pointer;{{ $s['active'] ? 'background:rgba(245,158,11,0.15);' : '' }}"
                     wire:click="switchSession({{ $s['id'] }})" @click="historyOpen = false">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;font-weight:{{ $s['active'] ? '700' : '500' }};color:var(--km-text, #1f2937);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $s['title'] }}
                        </div>
                        <div style="font-size:10px;color:var(--km-muted, #6b7280);">{{ $s['date'] }}</div>
                    </div>
                    <button type="button" wire:click.stop="deleteSession({{ $s['id'] }})" title="Hapus"
                            style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;padding:2px;opacity:0.5;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.5'">
                        🗑
                    </button>
                </div>
            @empty
                <div style="padding:16px 20px;font-size:12px;color:var(--km-muted, #6b7280);text-align:center;">Belum ada riwayat chat</div>
            @endforelse
        </div>
    </div>

    {{-- Message list --}}
    <div x-ref="msglist" class="ai-chat-msglist">
        <template x-for="(msg, idx) in msgs" :key="idx">
            <div :class="msg.role === 'user' ? 'ai-chat-row ai-chat-row-user' : 'ai-chat-row ai-chat-row-assistant'">
                <div :class="msg.role === 'user' ? 'ai-chat-bubble ai-chat-bubble-user' : 'ai-chat-bubble ai-chat-bubble-assistant'">
                    <template x-if="msg.role === 'user'">
                        <span style="display:flex;align-items:start;gap:6px;">
                            <span x-html="renderUserMsg(msg.content)" style="flex:1;"></span>
                            <button class="ai-chat-edit-btn" title="Edit & kirim ulang"
                                    @click.stop="$wire.editMessage(idx)" style="flex-shrink:0;margin-top:2px;">✏</button>
                        </span>
                    </template>
                    <template x-if="msg.role !== 'user'">
                        <span x-html="renderMd(msg.content)"></span>
                    </template>
                </div>
            </div>
        </template>

        {{-- Typing indicator --}}
        <div wire:loading wire:target="send,fetchAi" class="ai-chat-row ai-chat-row-assistant">
            <div class="ai-chat-typing">
                <div class="ai-chat-dot" style="animation-delay:0ms"></div>
                <div class="ai-chat-dot" style="animation-delay:150ms"></div>
                <div class="ai-chat-dot" style="animation-delay:300ms"></div>
            </div>
        </div>
    </div>

    {{-- Quick suggestions --}}
    <div class="ai-chat-suggest">
        @php
            $suggestions = [
                'Berapa proyek kritis hari ini?',
                'Tampilkan laporan harian hari ini',
                'Ringkasan dashboard',
                'Termin yang menunggu approval',
            ];
        @endphp
        @foreach ($suggestions as $s)
            <button type="button"
                wire:click="$set('input', @js($s))"
                wire:loading.attr="disabled"
                wire:target="send,fetchAi"
                class="ai-chat-suggest-btn">{{ $s }}</button>
        @endforeach
    </div>

    {{-- Attachments --}}
    <div wire:loading wire:target="uploadedFiles" style="padding:8px 20px; font-size:12px; color:#f59e0b;">
        ⏳ Mengupload file...
    </div>
    @if(!empty($attachments))
        <div style="display:flex; flex-wrap:wrap; gap:6px; padding:8px 20px 4px;">
            @foreach($attachments as $i => $att)
                <div style="display:inline-flex; align-items:center; gap:6px; background:#f0fdfa; color:#0f766e; border:1px solid #99f6e4; padding:4px 10px; border-radius:14px; font-size:12px;">
                    <span title="{{ $att['name'] }}" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:240px;">📎 {{ $att['name'] }}</span>
                    <button type="button" wire:click="removeAttachment({{ $i }})" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:16px; line-height:1; padding:0;">×</button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Input area --}}
    <div class="ai-chat-input-bar">
        <label style="display:flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:50%; background:#f3f4f6; cursor:pointer; flex-shrink:0; transition:background .15s;"
               onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"
               title="Upload PDF / Excel / Word">
            <input type="file" wire:model="uploadedFile" x-ref="heroFile" accept=".pdf,.xlsx,.xls,.docx,.jpg,.jpeg,.png" style="display:none">
            <svg wire:loading.remove wire:target="uploadedFile" style="width:18px;height:18px;color:#6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            <svg wire:loading wire:target="uploadedFile" style="width:18px;height:18px;color:#f59e0b;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </label>

        <input type="text"
            wire:model="input"
            wire:keydown.enter.prevent="send"
            wire:loading.attr="disabled"
            wire:target="send,fetchAi"
            placeholder="Ketik pertanyaan, atau drag PDF/Excel ke sini..."
            autocomplete="off"
            class="ai-chat-input">

        {{-- Send (normal) / Stop (while AI processing) --}}
        <button wire:loading.remove wire:target="fetchAi"
            wire:click="send"
            wire:loading.attr="disabled" wire:target="send"
            class="ai-chat-send-btn">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
        </button>
        <button wire:loading wire:target="fetchAi"
            @click="if(confirm('Hentikan AI? Halaman akan di-refresh.')) location.reload()"
            class="ai-chat-stop-btn"
            title="Hentikan AI"
            style="position:relative;">
            <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:12px;height:12px;background:#fff;border-radius:2px;"></span>
        </button>
    </div>
</div>
