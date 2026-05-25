"""
Karta screenshot capture — iter-N.
Usage: python _capture.py [iter1|iter2|...]
"""
import os
import sys
import time
from pathlib import Path
from playwright.sync_api import sync_playwright, Page

BASE = "http://localhost:8010"
EMAIL = "admin@dputr.go.id"
PASSWORD = "password"

ITER = sys.argv[1] if len(sys.argv) > 1 else "iter1"
OUT = Path(__file__).parent / f"_{ITER}"
OUT.mkdir(exist_ok=True)


def shot(page: Page, name: str, full=False, clip=None):
    path = OUT / f"{name}.png"
    if clip:
        page.screenshot(path=str(path), clip=clip)
    else:
        page.screenshot(path=str(path), full_page=full)
    print(f"  -> {path.name} ({'full' if full else 'viewport'}) {page.url}")


def login(page: Page):
    page.goto(f"{BASE}/admin/login", wait_until="domcontentloaded", timeout=20000)
    page.wait_for_selector("input[type=email]", timeout=10000)
    shot(page, "01-login-page", full=True)
    page.fill("input[type=email]", EMAIL)
    page.fill("input[type=password]", PASSWORD)
    page.click("button[type=submit]")
    page.wait_for_url(f"{BASE}/admin", timeout=15000)
    page.wait_for_load_state("networkidle", timeout=15000)
    time.sleep(2)


def dashboard(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle", timeout=20000)
    time.sleep(2)
    # Top viewport
    shot(page, "02b-dashboard-top", full=False)
    # Full page
    shot(page, "02-dashboard-fullpage", full=True)


def avatar_dropdown(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(1)
    # Try filament avatar / user menu trigger
    try:
        page.locator("[x-data*=userMenu], [data-flux-tooltip], button[aria-label*='user' i], button[aria-label*='profil' i]").first.click(timeout=3000)
    except Exception:
        try:
            page.locator(".fi-user-menu, .fi-topbar-user-menu-trigger, [aria-haspopup='menu']").first.click(timeout=3000)
        except Exception as e:
            print(f"  ! avatar click failed: {e}")
    time.sleep(1)
    shot(page, "19-logout-menu", full=False)


def chat_states(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    # Find chat input
    try:
        chat_input = page.locator("textarea, input[placeholder*='Ketik' i], input[placeholder*='pertanyaan' i]").first
        chat_input.scroll_into_view_if_needed()
        chat_input.fill("berapa proyek aktif?")
        shot(page, "03a-chat-typing-question", full=False)
        chat_input.press("Enter")
        time.sleep(0.5)
        shot(page, "03b-chat-user-bubble-instant", full=False)
        time.sleep(8)
        shot(page, "03c-chat-ai-response", full=False)
    except Exception as e:
        print(f"  ! chat capture failed: {e}")


def floating_chat(page: Page):
    page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
    time.sleep(2)
    shot(page, "17-floating-chat-button", full=False)
    try:
        page.locator("[x-data*='floatingChat'], [aria-label*='chat' i], .floating-chat-button, button.fixed.bottom-4, button.fixed.bottom-6").first.click(timeout=3000)
        time.sleep(1)
        shot(page, "17b-floating-chat-open", full=False)
    except Exception as e:
        print(f"  ! floating chat: {e}")


def calendar_modal(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    try:
        page.locator("button:has-text('Kalender'), a:has-text('Kalender')").first.click(timeout=3000)
        time.sleep(2)
        shot(page, "06-calendar-modal", full=False)
        try:
            page.locator("button:has-text('›'), button[aria-label*='next' i]").first.click(timeout=2000)
            time.sleep(1)
            shot(page, "06b-calendar-next-month", full=False)
        except Exception:
            pass
        page.keyboard.press("Escape")
    except Exception as e:
        print(f"  ! calendar: {e}")


def kanban_full(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    # Scroll to kanban
    try:
        page.evaluate("document.querySelector('[wire\\\\:id*=\"kanban\" i], #kanban, [class*=kanban]')?.scrollIntoView()")
    except Exception:
        page.mouse.wheel(0, 1500)
    time.sleep(1)
    shot(page, "07-kanban-full", full=False)
    # Filter Jalan
    try:
        page.locator("button:has-text('Jalan'), a:has-text('Jalan')").first.click(timeout=3000)
        time.sleep(2)
        shot(page, "07b-kanban-filter-jalan", full=False)
    except Exception as e:
        print(f"  ! kanban filter: {e}")
    # Card modal — click first card
    try:
        page.locator("[wire\\:click*=openCard], [x-on\\:click*='card'], .kanban-card, [data-card-id]").first.click(timeout=3000)
        time.sleep(2)
        shot(page, "07c-kanban-card-modal", full=False)
        page.keyboard.press("Escape")
    except Exception as e:
        print(f"  ! kanban card: {e}")


def import_export_calendar_modals(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    # Import Kontrak modal
    try:
        page.locator("button:has-text('Import Kontrak'), a:has-text('Import Kontrak')").first.click(timeout=3000)
        time.sleep(2)
        shot(page, "04-import-kontrak-modal", full=False)
        page.keyboard.press("Escape")
        time.sleep(1)
    except Exception as e:
        print(f"  ! import: {e}")
    # Export Data
    try:
        page.locator("button:has-text('Export Data'), a:has-text('Export Data')").first.click(timeout=3000)
        time.sleep(2)
        shot(page, "05-export-data-modal", full=False)
        page.keyboard.press("Escape")
        time.sleep(1)
    except Exception as e:
        print(f"  ! export: {e}")


def sidebar(page: Page):
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    shot(page, "08-sidebar-collapsed", full=False)
    # Expand Master Data + Pengaturan
    try:
        for label in ["Master Data", "Pengaturan"]:
            try:
                page.locator(f"aside button:has-text('{label}'), aside a:has-text('{label}')").first.click(timeout=2000)
                time.sleep(0.5)
            except Exception:
                pass
        time.sleep(1)
        shot(page, "08b-sidebar-expanded", full=False)
    except Exception as e:
        print(f"  ! sidebar expand: {e}")


def pekerjaan_pages(page: Page):
    # List
    page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
    time.sleep(2)
    shot(page, "09-pekerjaan-list", full=False)
    # Filter tabs
    for label, name in [
        ("Tahun 2026", "22a-filter-tahun-2026"),
        ("Sedang Berjalan", "22b-filter-sedang-berjalan"),
        ("Terlambat", "22c-filter-terlambat"),
        ("Selesai", "22d-filter-selesai"),
    ]:
        try:
            page.locator(f"button:has-text('{label}'), a:has-text('{label}'), .fi-tabs-tab:has-text('{label}')").first.click(timeout=2000)
            time.sleep(2)
            shot(page, name, full=False)
        except Exception as e:
            print(f"  ! filter {label}: {e}")
    # Detail — first row
    page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
    time.sleep(2)
    try:
        page.locator("table tbody tr").first.click(timeout=3000)
        page.wait_for_load_state("networkidle", timeout=10000)
        time.sleep(2)
        shot(page, "10-pekerjaan-detail", full=False)
        shot(page, "10b-pekerjaan-detail-fullpage", full=True)
        shot(page, "20-pekerjaan-tabs-overview", full=True)
    except Exception as e:
        print(f"  ! detail: {e}")
    # Form Create
    page.goto(f"{BASE}/admin/pekerjaans/create", wait_until="networkidle")
    time.sleep(2)
    shot(page, "21-form-tambah-pekerjaan", full=True)


def laporan_audit(page: Page):
    page.goto(f"{BASE}/admin/laporan-harians", wait_until="networkidle")
    time.sleep(2)
    shot(page, "11-laporan-harian-list", full=False)
    page.goto(f"{BASE}/admin/activities", wait_until="networkidle")
    time.sleep(2)
    shot(page, "12-audit-trail", full=False)


def master_pages(page: Page):
    for route, name in [
        ("/admin/master/bidangs", "23-master-bidang"),
        ("/admin/master/perusahaans", "15-master-perusahaan"),
        ("/admin/master/tenaga-ahlis", "16-master-tenaga-ahli"),
        ("/admin/users", "14-pengguna-list"),
        ("/admin/system-settings", "13-system-settings"),
    ]:
        page.goto(f"{BASE}{route}", wait_until="networkidle")
        time.sleep(2)
        shot(page, name, full=False)
    page.goto(f"{BASE}/admin/system-settings", wait_until="networkidle")
    time.sleep(2)
    shot(page, "24-system-settings-full", full=True)


def vendor_login(page: Page):
    page.goto(f"{BASE}/vendor/login", wait_until="networkidle", timeout=15000)
    time.sleep(2)
    shot(page, "18-vendor-login", full=False)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, args=["--disable-gpu"])
        ctx = browser.new_context(viewport={"width": 1440, "height": 900}, ignore_https_errors=True)
        page = ctx.new_page()
        print(f"[iter={ITER}] login")
        login(page)
        print("[1/9] dashboard")
        dashboard(page)
        print("[2/9] avatar")
        avatar_dropdown(page)
        print("[3/9] chat")
        chat_states(page)
        print("[4/9] floating")
        floating_chat(page)
        print("[5/9] calendar/import/export modals")
        calendar_modal(page)
        import_export_calendar_modals(page)
        print("[6/9] kanban")
        kanban_full(page)
        print("[7/9] sidebar")
        sidebar(page)
        print("[8/9] pekerjaan")
        pekerjaan_pages(page)
        print("[9/9] master / laporan / vendor")
        laporan_audit(page)
        master_pages(page)
        # Vendor — separate context (no auth)
        ctx2 = browser.new_context(viewport={"width": 414, "height": 896}, ignore_https_errors=True)
        page2 = ctx2.new_page()
        page2.goto(f"{BASE}/vendor/login", wait_until="networkidle")
        time.sleep(2)
        page2.screenshot(path=str(OUT / "18-vendor-login.png"), full_page=False)
        print(f"  -> 18-vendor-login.png (mobile vp) {page2.url}")
        browser.close()


if __name__ == "__main__":
    main()
