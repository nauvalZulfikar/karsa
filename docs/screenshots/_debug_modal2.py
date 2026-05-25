"""Click and screenshot all the dialog states."""
import sys, time
from playwright.sync_api import sync_playwright
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
BASE = "http://localhost:8010"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=False, slow_mo=300)
    page = browser.new_context(viewport={"width": 1440, "height": 900}).new_page()
    page.goto(f"{BASE}/admin/login")
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(3)

    print("clicking Import Kontrak...")
    page.locator("button:has-text('Import Kontrak')").first.click()
    # poll modal-window display every 500ms for 12s
    for i in range(24):
        time.sleep(0.5)
        info = page.evaluate("""
            () => {
                const wins = [...document.querySelectorAll('.fi-modal-window')];
                return wins.map(w => ({w: w.offsetWidth, h: w.offsetHeight, computed: window.getComputedStyle(w).display}));
            }
        """)
        print(f"  t={i*0.5:>4.1f}s: {info}")
        if any(x['w'] > 0 and x['h'] > 0 for x in info):
            print("  MODAL OPEN!")
            page.screenshot(path="_iter1/04-import-kontrak-modal.png")
            break
    browser.close()
