<div
    x-data="{
        open: @entangle('isOpen'),
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
            this.$refs.batchFilein.files = files;
            this.$refs.batchFilein.dispatchEvent(new Event('change'));
        }
    }"
    x-effect="msgs.length && scrollBottom()"
    @ai-fetch.window="setTimeout(() => $wire.fetchAi(), 50)"
    @dragover.window.prevent="if (open) drag = true"
    @dragleave.window.prevent="drag = false"
    @drop.window.prevent="if (open) dropFiles($event)"
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
            width: 560px;
            height: 75vh;
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
            padding: 10px 14px;
            background: #0d9488;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .ai-fc-header-icon {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
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
            overflow-x: hidden;
            padding: 8px 12px;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .dark .ai-fc-msglist { background: #111827; }
        .ai-fc-row { display: flex; flex-shrink: 0; }
        .ai-fc-row:first-child { margin-top: auto; }
        .ai-fc-row-user { justify-content: flex-end; }
        .ai-fc-row-assistant { justify-content: flex-start; }
        .ai-fc-bubble {
            max-width: 85%;
            padding: 8px 12px;
            font-size: 13px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: normal;
            direction: ltr !important;
            text-align: left !important;
            unicode-bidi: isolate;
            text-indent: 0 !important;
            position: relative;
        }
        .ai-fc-bubble * {
            text-align: inherit !important;
            text-indent: 0 !important;
        }
        .ai-fc-bubble strong { font-weight: 700; }
        .ai-fc-bubble-user {
            background: #0d9488;
            color: #ffffff !important;
            border-radius: 16px 16px 4px 16px;
        }
        .ai-fc-bubble-assistant {
            background: #ffffff;
            color: #1f2937;
            border-radius: 16px 16px 16px 4px;
            border: 1px solid #e5e7eb;
        }
        .dark .ai-fc-bubble-assistant {
            background: #374151;
            color: #f3f4f6;
            border-color: #4b5563;
        }
        .ai-fc-bubble-user:hover .ai-fc-edit-btn { opacity: 1; }
        .ai-fc-edit-btn {
            position: absolute;
            top: 4px; left: -28px;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(0,0,0,0.2);
            border: none; cursor: pointer;
            color: #fff; font-size: 11px;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.15s;
        }
        .ai-fc-edit-btn:hover { background: rgba(0,0,0,0.4); }
        .ai-fc-typing {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px 16px 16px 4px;
            padding: 8px 12px;
            display: inline-flex;
            gap: 4px;
            align-items: center;
            width: fit-content;
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
        @keyframes spin { to { transform: rotate(360deg); } }
        .ai-fc-input-bar {
            flex-shrink: 0;
            padding: 8px 10px;
            display: flex;
            gap: 6px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            align-items: center;
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
        .ai-fc-send, .ai-fc-stop {
            width: 36px; height: 36px;
            border-radius: 50%;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ai-fc-send { background: #0d9488; }
        .ai-fc-send:hover { background: #0f766e; }
        .ai-fc-send:disabled { opacity: 0.5; cursor: default; }
        .ai-fc-stop { background: #ef4444; }
        .ai-fc-stop:hover { background: #dc2626; }

        /* Attachments */
        .ai-fc-attachments {
            display: flex; flex-wrap: wrap; gap: 6px;
            padding: 8px 12px 0;
            background: #ffffff;
        }
        .dark .ai-fc-attachments { background: #1f2937; }
        .ai-fc-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f0fdfa; color: #0f766e;
            border: 1px solid #99f6e4;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            max-width: 220px;
        }
        .dark .ai-fc-chip { background: #134e4a; color: #5eead4; border-color: #0d9488; }
        .ai-fc-chip-name {
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 180px;
        }
        .ai-fc-chip-x {
            background: none; border: none; cursor: pointer;
            color: #ef4444; font-size: 16px; line-height: 1; padding: 0;
        }
        .ai-fc-attach-btn {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0;
            transition: background 0.15s;
        }
        .ai-fc-attach-btn:hover { background: #e5e7eb; }
        .dark .ai-fc-attach-btn { background: #374151; }
        .dark .ai-fc-attach-btn:hover { background: #4b5563; }
        .ai-fc-drag-active {
            outline: 2px dashed #0d9488 !important;
            outline-offset: -4px;
        }
        .ai-fc-drag-active .ai-fc-msglist { background: rgba(13,148,136,0.05) !important; }

        /* Download chip in chat bubble */
        .ai-fc-dl-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 8px 14px;
            background: #0d9488;
            color: #fff !important;
            border-radius: 8px;
            text-decoration: none !important;
            font-weight: 600;
            font-size: 13px;
            transition: background .15s;
        }
        .ai-fc-dl-chip:hover { background: #0f766e; }
        .ai-fc-bubble-assistant a { color: #0d9488; text-decoration: underline; }
    </style>

    <script>
        window.kartaChatHelpers = function () {
            return {
                renderMd(text) {
                    if (!text) return '';
                    let s = String(text)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                    s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, function (m, label, url) {
                        if (url.indexOf('/dokumen/') !== -1) {
                            return '<a href="' + url + '" target="_blank" class="ai-fc-dl-chip">📄 ' + label + '</a>';
                        }
                        return '<a href="' + url + '" target="_blank">' + label + '</a>';
                    });
                    s = s.replace(/^#{1,4}\s+(.+)$/gm, '<strong>$1</strong>');
                    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                         .replace(/\*([^*\n]+)\*/g, '<em>$1</em>')
                         .replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.15);padding:1px 4px;border-radius:3px;font-size:12px;">$1</code>');
                    s = s.replace(/^---+$/gm, '<hr style="border:none;border-top:1px solid rgba(128,128,128,0.3);margin:6px 0;">');
                    s = s.replace(/^[-•]\s+(.+)$/gm, '&bull; $1');
                    s = s.replace(/\n/g, '<br>');
                    return s;
                }
            };
        };
    </script>

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

    {{-- Chat panel — drag-drop zone covers entire panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         class="ai-fc-panel"
         :class="{ 'ai-fc-drag-active': drag }">

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

        <div x-ref="msglist" class="ai-fc-msglist"
             x-data="window.kartaChatHelpers()">
            <template x-for="(msg, idx) in msgs" :key="idx">
                <div :class="msg.role === 'user' ? 'ai-fc-row ai-fc-row-user' : 'ai-fc-row ai-fc-row-assistant'">
                    <div :class="msg.role === 'user' ? 'ai-fc-bubble ai-fc-bubble-user' : 'ai-fc-bubble ai-fc-bubble-assistant'">
                        <template x-if="msg.role === 'user'">
                            <span>
                                <button class="ai-fc-edit-btn" title="Edit & kirim ulang"
                                        @click.stop="$wire.editMessage(idx)">✏</button>
                                <span x-text="msg.content"></span>
                            </span>
                        </template>
                        <template x-if="msg.role !== 'user'"><span x-html="renderMd(msg.content)"></span></template>
                    </div>
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

        <div wire:loading wire:target="uploadedFiles" style="padding:6px 12px; font-size:11px; color:#0d9488;">
            ⏳ Mengupload file...
        </div>
        @if(!empty($attachments))
            <div class="ai-fc-attachments">
                @foreach($attachments as $i => $att)
                    <div class="ai-fc-chip">
                        <span class="ai-fc-chip-name" title="{{ $att['name'] }}">📎 {{ $att['name'] }}</span>
                        <button type="button" wire:click="removeAttachment({{ $i }})" class="ai-fc-chip-x">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <input type="file" wire:model="uploadedFiles" x-ref="batchFilein" multiple accept=".pdf,.xlsx,.xls,.docx,.jpg,.jpeg,.png" style="display:none">

        <div class="ai-fc-input-bar">
            <label class="ai-fc-attach-btn" title="Upload PDF / Excel / Word">
                <input type="file" wire:model="uploadedFile" x-ref="filein" accept=".pdf,.xlsx,.xls,.docx,.jpg,.jpeg,.png" style="display:none">
                <svg wire:loading.remove wire:target="uploadedFile" style="width:18px;height:18px;color:#6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                <svg wire:loading wire:target="uploadedFile" style="width:18px;height:18px;color:#0d9488;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </label>

            <input type="text"
                wire:model="input"
                wire:keydown.enter.prevent="send"
                wire:loading.attr="disabled"
                wire:target="send,fetchAi"
                placeholder="Ketik pertanyaan, atau drag file ke sini..."
                autocomplete="off"
                class="ai-fc-input">

            {{-- Send button (normal) / Stop button (while processing) --}}
            <button wire:loading.remove wire:target="fetchAi"
                wire:click="send"
                wire:loading.attr="disabled"
                wire:target="send"
                class="ai-fc-send">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
            <button wire:loading wire:target="fetchAi"
                wire:click="stopAi"
                class="ai-fc-stop"
                title="Hentikan AI"
                style="position:relative;">
                <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:10px;height:10px;background:#fff;border-radius:2px;"></span>
            </button>
        </div>
    </div>
</div>
