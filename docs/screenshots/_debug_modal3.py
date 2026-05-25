"""Force-open modal via Livewire dispatch."""
import sys, time
from playwright.sync_api import sync_playwright
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
BASE = "http://localhost:8010"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_context(viewport={"width": 1440, "height": 900}).new_page()

    # capture browser console
    page.on("console", lambda msg: print(f"[BROWSER {msg.type}] {msg.text[:200]}"))
    page.on("pageerror", lambda err: print(f"[PAGEERR] {err}"))

    page.goto(f"{BASE}/admin/login")
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(3)

    # Inspect wire components
    info = page.evaluate("""
        () => {
            const out = { livewire: !!window.Livewire, version: window.Livewire?.version || null };
            // find component wire:id
            const widget = document.querySelector('[wire\\\\:id]');
            out.wireId = widget?.getAttribute('wire:id');
            // find the import button's wire-id parent
            const btn = document.querySelector("button[wire\\\\:click*='importKontrak']");
            return out;
        }
    """)
    print("info:", info)

    # Try clicking with proper wait for livewire request
    print("clicking via Livewire find/call...")
    try:
        page.evaluate("""
            async () => {
                const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Import Kontrak'));
                if (!btn) return 'no btn';
                // Get component
                let el = btn;
                while (el && !el.hasAttribute('wire:id')) el = el.parentElement;
                if (!el) return 'no component parent';
                const wireId = el.getAttribute('wire:id');
                const comp = window.Livewire.find(wireId);
                if (!comp) return 'no comp';
                await comp.call('mountAction', 'importKontrak');
                return 'called';
            }
        """)
    except Exception as e:
        print(f"err: {e}")

    time.sleep(5)
    info = page.evaluate("""
        () => [...document.querySelectorAll('.fi-modal-window')].map(w => ({w: w.offsetWidth, h: w.offsetHeight}))
    """)
    print("after Livewire call:", info)
    page.screenshot(path="_iter1/04-import-kontrak-modal.png")
    browser.close()
