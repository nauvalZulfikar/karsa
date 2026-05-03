<div
    x-data="{
        msgs: @entangle('messages'),
        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msglist;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-effect="msgs.length && scrollBottom()"
    x-init="scrollBottom()"
    @ai-fetch.window="setTimeout(() => $wire.fetchAi(), 50)"
    class="ai-chat-hero-root flex flex-col overflow-hidden"
>
    <style>
        .ai-chat-hero-root {
            height: 480px;
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
            overflow-y: auto;
            padding: 16px 20px;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scroll-behavior: smooth;
        }
        .dark .ai-chat-msglist {
            background: #111827;
        }
        .ai-chat-row {
            display: flex;
            width: 100%;
        }
        .ai-chat-row-user { justify-content: flex-end; }
        .ai-chat-row-assistant { justify-content: flex-start; }
        .ai-chat-bubble {
            max-width: 75%;
            padding: 10px 16px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
        }
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
        .ai-chat-send-btn {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #f59e0b;
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .ai-chat-send-btn:hover { background: #d97706; }
        .ai-chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>

    {{-- Header --}}
    <div class="ai-chat-header">
        <div class="ai-chat-header-icon">
            <svg style="width:20px;height:20px;color:#fff;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/>
            </svg>
        </div>
        <div style="flex:1;min-width:0;">
            <h3>Apa yang bisa saya bantu hari ini?</h3>
            <p>Tanya, atau minta saya update data — saya akan konfirmasi dulu</p>
        </div>
    </div>

    {{-- Message list --}}
    <div x-ref="msglist" class="ai-chat-msglist">
        <template x-for="(msg, idx) in msgs" :key="idx">
            <div :class="msg.role === 'user' ? 'ai-chat-row ai-chat-row-user' : 'ai-chat-row ai-chat-row-assistant'">
                <div :class="msg.role === 'user' ? 'ai-chat-bubble ai-chat-bubble-user' : 'ai-chat-bubble ai-chat-bubble-assistant'"
                     x-text="msg.content"></div>
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

    {{-- Input area --}}
    <div class="ai-chat-input-bar">
        <input type="text"
            wire:model="input"
            wire:keydown.enter.prevent="send"
            wire:loading.attr="disabled"
            wire:target="send,fetchAi"
            placeholder="Ketik pertanyaan atau perintah..."
            autocomplete="off"
            class="ai-chat-input">
        <button wire:click="send"
            wire:loading.attr="disabled"
            wire:target="send,fetchAi"
            class="ai-chat-send-btn">
            <svg wire:loading.remove wire:target="send,fetchAi" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
            <svg wire:loading wire:target="send,fetchAi" style="width:16px;height:16px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </button>
    </div>
</div>
