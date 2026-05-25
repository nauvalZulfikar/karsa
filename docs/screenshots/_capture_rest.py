"""Capture remaining screenshots after iter1 partial failure."""
import os, sys, time
from pathlib import Path
from playwright.sync_api import sync_playwright, Page

BASE = "http://localhost:8010"
EMAIL = "admin@dputr.go.id"
PASSWORD = "password"
ITER = sys.argv[1] if len(sys.argv) > 1 else "iter1"
OUT = Path(__file__).parent / f"_{ITER}"
OUT.mkdir(exist_ok=True)

# force utf-8 stdout
sys.stdout.reconfigure(encoding="utf-8", errors="replace")


def shot(page: Page, name: str, full=False):
    path = OUT / f"{name}.png"
    page.screenshot(path=str(path), full_page=full)
    print(f"  -> {name}.png  url={page.url}")


def login(page: Page):
    page.goto(f"{BASE}/admin/login", wait_until="domcontentloaded", timeout=20000)
    page.wait_for_selector("input[type=email]", timeout=10000)
    page.fill("input[type=email]", EMAIL)
    page.fill("input[type=password]", PASSWORD)
    page.click("button[type=submit]")
    page.wait_for_url(f"{BASE}/admin", timeout=15000)
    page.wait_for_load_state("networkidle", timeout=15000)
    time.sleep(2)


def safe(name, fn):
    try:
        fn()
        print(f"OK  {name}")
    except Exception as e:
        msg = str(e).encode("ascii", "replace").decode()
        print(f"ERR {name}: {msg[:200]}")


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={"width": 1440, "height": 900}, ignore_https_errors=True)
        page = ctx.new_page()
        login(page)

        def pekerjaan_detail():
            page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
            time.sleep(2)
            # try Filament "View" action icon link in row
            link = page.locator("table tbody tr a[href*='/pekerjaans/']").first
            href = link.get_attribute("href")
            page.goto(href, wait_until="networkidle")
            time.sleep(3)
            shot(page, "10-pekerjaan-detail", full=False)
            shot(page, "10b-pekerjaan-detail-fullpage", full=True)
            shot(page, "20-pekerjaan-tabs-overview", full=True)

        def pekerjaan_create():
            page.goto(f"{BASE}/admin/pekerjaans/create", wait_until="networkidle", timeout=20000)
            time.sleep(3)
            shot(page, "21-form-tambah-pekerjaan", full=True)

        def laporan_harian():
            page.goto(f"{BASE}/admin/laporan-harians", wait_until="networkidle")
            time.sleep(2)
            shot(page, "11-laporan-harian-list", full=False)

        def audit_trail():
            page.goto(f"{BASE}/admin/activities", wait_until="networkidle")
            time.sleep(2)
            shot(page, "12-audit-trail", full=False)

        def masters():
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

        def floating_chat_open():
            page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
            time.sleep(2)
            # find fixed positioned bottom-right button (chat trigger)
            page.evaluate("""
                const btns = [...document.querySelectorAll('button, [role=button]')]
                  .filter(b => {
                    const r = b.getBoundingClientRect();
                    return r.bottom > window.innerHeight - 100 && r.right > window.innerWidth - 150 && r.width > 30;
                  });
                if (btns.length) btns[0].click();
            """)
            time.sleep(2)
            shot(page, "17b-floating-chat-open", full=False)

        safe("pekerjaan_detail", pekerjaan_detail)
        safe("pekerjaan_create", pekerjaan_create)
        safe("laporan_harian", laporan_harian)
        safe("audit_trail", audit_trail)
        safe("masters", masters)
        safe("floating_chat_open", floating_chat_open)

        # Vendor login — separate ctx
        ctx2 = browser.new_context(viewport={"width": 414, "height": 896})
        p2 = ctx2.new_page()
        try:
            p2.goto(f"{BASE}/vendor/login", wait_until="networkidle", timeout=15000)
            time.sleep(2)
            p2.screenshot(path=str(OUT / "18-vendor-login.png"), full_page=False)
            print("OK  vendor_login")
        except Exception as e:
            print(f"ERR vendor_login: {e}")

        browser.close()


if __name__ == "__main__":
    main()
