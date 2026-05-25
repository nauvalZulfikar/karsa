"""Debug import modal selector."""
import sys, time
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8010"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_context(viewport={"width": 1440, "height": 900}).new_page()
    page.goto(f"{BASE}/admin/login")
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle", timeout=20000)
    time.sleep(2)

    # find buttons matching 'Import Kontrak'
    btns = page.locator("button:has-text('Import Kontrak')").all()
    print(f"matched buttons: {len(btns)}")
    for i, b in enumerate(btns):
        attrs = b.evaluate("e => ({wireClick: e.getAttribute('wire:click'), type: e.type, classes: e.className, txt: e.innerText.trim().slice(0,50)})")
        print(f"  [{i}] {attrs}")

    # try clicking and then dump dialog/modal elements
    if btns:
        btns[0].click()
        time.sleep(3)
        # dump any modal-like things
        dumped = page.evaluate("""
            () => {
                const sel = ['[role=dialog]', '.fi-modal', '.fi-modal-window', 'dialog', '.filament-modal', '[x-show=\"isOpen\"]'];
                const out = {};
                for (const s of sel) {
                    const els = [...document.querySelectorAll(s)];
                    out[s] = els.map(e => ({tag: e.tagName, visible: !!(e.offsetWidth || e.offsetHeight), cls: e.className.slice(0,80)}));
                }
                return out;
            }
        """)
        for k, v in dumped.items():
            print(f"{k}: {v}")
    browser.close()
