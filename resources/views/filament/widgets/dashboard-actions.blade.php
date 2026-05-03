<x-filament-widgets::widget>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        {{ $this->importKontrakAction }}
        {{ $this->exportAction }}
        <button type="button"
                onclick="Livewire.dispatch('open-calendar')"
                style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:9999px;background:#fff;color:#1f2937;border:1px solid #d1d5db;cursor:pointer;font-weight:600;font-size:14px;transition:all 0.15s;"
                onmouseover="this.style.background='#f9fafb';this.style.borderColor='#f59e0b';"
                onmouseout="this.style.background='#fff';this.style.borderColor='#d1d5db';">
            🗓️ Kalender
        </button>
    </div>
</x-filament-widgets::widget>
