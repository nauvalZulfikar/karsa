"""Re-capture failed/incomplete screenshots from iter1."""
import sys, time
from pathlib import Path
from playwright.sync_api import sync_playwright, Page

BASE = "http://localhost:8010"
EMAIL = "admin@dputr.go.id"
PASSWORD = "password"
OUT = Path(__file__).parent / "_iter1"
sys.stdout.reconfigure(encoding="utf-8", errors="replace")


def shot(page: Page, name: str, full=False):
    p = OUT / f"{name}.png"
    page.screenshot(path=str(p), full_page=full)
    print(f"  -> {name}.png  {page.url}")


def login(page: Page):
    page.goto(f"{BASE}/admin/login", wait_until="domcontentloaded", timeout=20000)
    page.wait_for_selector("input[type=email]")
    page.fill("input[type=email]", EMAIL)
    page.fill("input[type=password]", PASSWORD)
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle", timeout=20000)
    time.sleep(2)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        page = ctx.new_page()
        login(page)

        def wait_modal_open(timeout_ms=10000):
            page.wait_for_function(
                "() => Array.from(document.querySelectorAll('.fi-modal-window')).some(e => e.offsetWidth > 0 && e.offsetHeight > 0)",
                timeout=timeout_ms,
            )

        # --- Import Kontrak modal ---
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        try:
            page.locator("button:has-text('Import Kontrak')").first.click(timeout=5000)
            wait_modal_open()
            time.sleep(2)
            shot(page, "04-import-kontrak-modal", full=False)
            page.keyboard.press("Escape")
            time.sleep(1)
        except Exception as e:
            print(f"import err: {str(e)[:200]}")

        # --- Export Data modal ---
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        try:
            page.locator("button:has-text('Export Data')").first.click(timeout=5000)
            wait_modal_open()
            time.sleep(2)
            shot(page, "05-export-data-modal", full=False)
            page.keyboard.press("Escape")
            time.sleep(1)
        except Exception as e:
            print(f"export err: {str(e)[:200]}")

        # --- Floating chat open ---
        page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
        time.sleep(2)
        try:
            page.locator(".ai-fc-toggle").first.click(timeout=5000)
            time.sleep(1.5)
            shot(page, "17b-floating-chat-open", full=False)
        except Exception as e:
            print(f"floating err: {str(e)[:200]}")

        # --- Chat user-bubble: send & wait for the user msg to appear ---
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(2)
        try:
            inp = page.locator("textarea, input[placeholder*='Ketik' i]").first
            inp.scroll_into_view_if_needed()
            inp.fill("berapa proyek aktif?")
            shot(page, "03a-chat-typing-question", full=False)
            inp.press("Enter")
            # Wait briefly for user bubble; not the AI yet
            time.sleep(1.2)
            # ensure scrolled to chat area
            page.evaluate("document.querySelector('.ai-chat-hero, [wire\\\\:id*=ai-chat-hero], #chat-scroll')?.scrollIntoView()")
            time.sleep(0.3)
            shot(page, "03b-chat-user-bubble-instant", full=False)
            # then wait for AI response
            time.sleep(10)
            shot(page, "03c-chat-ai-response", full=False)
        except Exception as e:
            print(f"chat err: {str(e)[:200]}")

        # --- Pekerjaan detail full + view-mode: open detail page, scroll through tabs ---
        page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
        time.sleep(2)
        # Pick a project that is "Sedang Berjalan" (not finished, has data)
        try:
            link = page.locator("table tbody tr a[href*='/pekerjaans/']:not([href*=edit])").first
            href = link.get_attribute("href")
            page.goto(href, wait_until="networkidle")
            time.sleep(2)
            # detail in viewport
            shot(page, "10-pekerjaan-detail", full=False)
            shot(page, "10b-pekerjaan-detail-fullpage", full=True)
            shot(page, "20-pekerjaan-tabs-overview", full=True)
        except Exception as e:
            print(f"detail err: {str(e)[:200]}")

        browser.close()


if __name__ == "__main__":
    main()
