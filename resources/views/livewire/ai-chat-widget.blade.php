<div
    x-data="{
        open: @entangle('isOpen'),
        msgs: @entangle('messages'),
        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.msglist;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-effect="msgs.length && scrollBottom()"
    @ai-fetch.window="setTimeout(() => $wire.fetchAi(), 50)"
    class="ai-fc-root"
>
    <style>
        .ai-fc-root {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9998;
        }
        .ai-fc-toggle {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: #0d9488;
            color: #fff;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .ai-fc-toggle:hover { background: #0f766e; }
        .ai-fc-toggle:active { transform: scale(0.95); }
        .ai-fc-panel {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 360px;
            height: 500px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .dark .ai-fc-panel {
            background: #1f2937;
            border-color: #374151;
        }
        .ai-fc-header {
            padding: 14px 18px;
            background: #0d9488;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .ai-fc-header-icon {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-fc-header h3 {
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            margin: 0;
            line-height: 1.2;
        }
        .ai-fc-header p {
            color: #ccfbf1;
            font-size: 11px;
            margin: 2px 0 0 0;
            line-height: 1.2;
        }
        .ai-fc-msglist {
            flex: 1;
            overflow-y: auto;
            padding: 14px 16px;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .dark .ai-fc-msglist { background: #111827; }
        .ai-fc-row { display: flex; }
        .ai-fc-row-user { justify-content: flex-end; }
        .ai-fc-row-assistant { justify-content: flex-start; }
        .ai-fc-bubble {
            max-width: 85%;
            padding: 9px 14px;
            font-size: 13px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .ai-fc-bubble-user {
            background: #0d9488;
            color: #ffffff !important;
            border-radius: 18px 18px 6px 18px;
        }
        .ai-fc-bubble-assistant {
            background: #ffffff;
            color: #1f2937;
            border-radius: 18px 18px 18px 6px;
            border: 1px solid #e5e7eb;
        }
        .dark .ai-fc-bubble-assistant {
            background: #374151;
            color: #f3f4f6;
            border-color: #4b5563;
        }
        .ai-fc-typing {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px 18px 18px 6px;
            padding: 10px 14px;
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .dark .ai-fc-typing {
            background: #374151;
            border-color: #4b5563;
        }
        .ai-fc-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #14b8a6;
            animation: ai-fc-bounce 1s infinite;
        }
        @keyframes ai-fc-bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-4px); opacity: 1; }
        }
        .ai-fc-input-bar {
            flex-shrink: 0;
            padding: 10px 12px;
            display: flex;
            gap: 8px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .dark .ai-fc-input-bar {
            background: #1f2937;
            border-top-color: #374151;
        }
        .ai-fc-input {
            flex: 1;
            font-size: 13px;
            padding: 9px 14px;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #1f2937;
            outline: none;
        }
        .ai-fc-input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13,148,136,0.2);
        }
        .ai-fc-input:disabled { opacity: 0.5; }
        .dark .ai-fc-input {
            background: #374151;
            color: #f3f4f6;
            border-color: #4b5563;
        }
        .ai-fc-send {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #0d9488;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ai-fc-send:hover { background: #0f766e; }
        .ai-fc-send:disabled { opacity: 0.5; }
    </style>

    {{-- Floating button --}}
    <button wire:click="toggle" title="Asisten AI DPUTR" class="ai-fc-toggle">
        <template x-if="!open">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
        </template>
        <template x-if="open">
            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </template>
    </button>

    {{-- Chat panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         class="ai-fc-panel">

        <div class="ai-fc-header">
            <div class="ai-fc-header-icon">
                <svg style="width:18px;height:18px;color:#fff;" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H7l5-8v4h4l-5 8z"/>
                </svg>
            </div>
            <div style="flex:1;min-width:0;">
                <h3>Asisten AI DPUTR</h3>
                <p>Data real-time dari sistem</p>
            </div>
        </div>

        <div x-ref="msglist" class="ai-fc-msglist">
            <template x-for="(msg, idx) in msgs" :key="idx">
                <div :class="msg.role === 'user' ? 'ai-fc-row ai-fc-row-user' : 'ai-fc-row ai-fc-row-assistant'">
                    <div :class="msg.role === 'user' ? 'ai-fc-bubble ai-fc-bubble-user' : 'ai-fc-bubble ai-fc-bubble-assistant'"
                         x-text="msg.content"></div>
                </div>
            </template>

            <div wire:loading wire:target="send,fetchAi" class="ai-fc-row ai-fc-row-assistant">
                <div class="ai-fc-typing">
                    <div class="ai-fc-dot" style="animation-delay:0ms"></div>
                    <div class="ai-fc-dot" style="animation-delay:150ms"></div>
                    <div class="ai-fc-dot" style="animation-delay:300ms"></div>
                </div>
            </div>
        </div>

        <div class="ai-fc-input-bar">
            <input type="text"
                wire:model="input"
                wire:keydown.enter.prevent="send"
                wire:loading.attr="disabled"
                wire:target="send,fetchAi"
                placeholder="Ketik pertanyaan..."
                autocomplete="off"
                class="ai-fc-input">
            <button wire:click="send"
                wire:loading.attr="disabled"
                wire:target="send,fetchAi"
                class="ai-fc-send">
                <svg wire:loading.remove wire:target="send,fetchAi" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <svg wire:loading wire:target="send,fetchAi" style="width:14px;height:14px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </button>
        </div>
    </div>
</div>
