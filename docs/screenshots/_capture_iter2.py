"""Iter-2: re-capture screenshots that need improvement.
- 07/07b: wider viewport (1920px) so all 6 kanban kolom visible
- 10b/20: Pekerjaan detail full + scroll to show 7 relation tabs
- 03b: chat user bubble after dispatch settles
"""
import sys, time
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8010"
OUT = Path(__file__).parent / "_iter2"
OUT.mkdir(exist_ok=True)
sys.stdout.reconfigure(encoding="utf-8", errors="replace")


def shot(page, name, full=False):
    p = OUT / f"{name}.png"
    page.screenshot(path=str(p), full_page=full)
    print(f"  -> {name}.png  {page.url}")


def login(page):
    page.goto(f"{BASE}/admin/login", timeout=20000)
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle", timeout=20000)
    time.sleep(2)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # WIDE viewport for kanban (1920 x 1080)
        ctx_wide = browser.new_context(viewport={"width": 1920, "height": 1080})
        page = ctx_wide.new_page()
        login(page)

        # Kanban full (1920px should fit all 6 kolom)
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        # scroll to kanban
        page.evaluate("document.querySelector('h3, h2, .fi-section-header')?.scrollIntoView()")
        # try scrolling to "Papan Pekerjaan" heading
        try:
            page.locator(":text('Papan Pekerjaan')").first.scroll_into_view_if_needed()
        except Exception:
            page.mouse.wheel(0, 600)
        time.sleep(1)
        shot(page, "07-kanban-full", full=False)

        # Filter Jalan
        try:
            page.locator("button:has-text('Jalan'), a:has-text('Jalan')").first.click(timeout=3000)
            time.sleep(2)
            page.locator(":text('Papan Pekerjaan')").first.scroll_into_view_if_needed()
            time.sleep(0.5)
            shot(page, "07b-kanban-filter-jalan", full=False)
        except Exception as e:
            print(f"  filter jalan: {str(e)[:120]}")

        ctx_wide.close()

        # Normal viewport for Pekerjaan detail with 7 tabs
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        page = ctx.new_page()
        login(page)

        page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
        time.sleep(2)
        try:
            link = page.locator("table tbody tr a[href*='/pekerjaans/']:not([href*=edit])").first
            href = link.get_attribute("href")
            page.goto(href, wait_until="networkidle")
            time.sleep(3)
            # detail top
            shot(page, "10-pekerjaan-detail", full=False)
            # full page (form + relation tabs)
            shot(page, "10b-pekerjaan-detail-fullpage", full=True)
            # scroll to relation tabs
            try:
                page.locator("[role=tablist]").first.scroll_into_view_if_needed(timeout=3000)
            except Exception:
                page.evaluate("window.scrollTo(0, document.body.scrollHeight - 700)")
            time.sleep(1)
            shot(page, "20-pekerjaan-tabs-overview", full=False)
        except Exception as e:
            print(f"  detail: {str(e)[:200]}")

        # Chat user bubble — wait longer
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        try:
            inp = page.locator("textarea, input[placeholder*='Ketik' i]").first
            inp.scroll_into_view_if_needed()
            inp.fill("berapa proyek aktif?")
            shot(page, "03a-chat-typing-question", full=False)
            inp.press("Enter")
            # wait for user bubble to render (orange bubble on the right)
            page.wait_for_function(
                "() => [...document.querySelectorAll('.bg-amber-500, [class*=bg-amber], .bg-orange-500, [class*=bubble-user]')].length > 0 || document.body.innerText.includes('berapa proyek aktif?')",
                timeout=8000,
            )
            time.sleep(0.5)
            page.evaluate("document.querySelector('#chat-scroll, [wire\\\\:id]')?.scrollIntoView({block: 'end'})")
            time.sleep(0.3)
            shot(page, "03b-chat-user-bubble-instant", full=False)
            time.sleep(10)
            shot(page, "03c-chat-ai-response", full=False)
        except Exception as e:
            print(f"  chat: {str(e)[:200]}")

        browser.close()


if __name__ == "__main__":
    main()
