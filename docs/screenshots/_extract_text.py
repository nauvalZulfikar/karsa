"""Extract visible text + structure from each Karta page that has a screenshot in the guidebook.
Output: _extracted/<name>.txt — one file per screenshot reference, containing the actual visible
text on the page, so we can compare guidebook alt-text/description against reality.
"""
import sys, time, json
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8010"
OUT = Path(__file__).parent / "_extracted"
OUT.mkdir(exist_ok=True)
sys.stdout.reconfigure(encoding="utf-8", errors="replace")


def login(page):
    page.goto(f"{BASE}/admin/login", timeout=20000)
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin", wait_until="networkidle", timeout=20000)
    time.sleep(2)


def dump(page, name, max_lines=80):
    text = page.evaluate("""
        () => {
            // Strip script/style noise, get visible-ish text
            const lines = document.body.innerText.split('\\n').map(s => s.trim()).filter(s => s.length > 0);
            return lines.slice(0, 200).join('\\n');
        }
    """)
    out = OUT / f"{name}.txt"
    out.write_text(f"URL: {page.url}\nVIEWPORT: {page.viewport_size}\n---\n{text}\n", encoding="utf-8")
    snippet = "\n".join(text.split("\n")[:max_lines])
    print(f"[{name}] {len(text.split(chr(10)))} lines  url={page.url}")


def vendor_login_dump(p):
    ctx2 = p.new_context(viewport={"width": 414, "height": 896})
    p2 = ctx2.new_page()
    p2.goto(f"{BASE}/vendor/login", wait_until="networkidle", timeout=15000)
    time.sleep(2)
    dump(p2, "18-vendor-login")
    ctx2.close()


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={"width": 1440, "height": 900})
        page = ctx.new_page()

        # 01 login
        page.goto(f"{BASE}/admin/login", timeout=20000)
        time.sleep(2)
        dump(page, "01-login-page")

        login(page)

        # dashboard top + full
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        dump(page, "02-dashboard-full")
        # also save chat hero specifically
        chat_hero = page.evaluate("""
            () => {
                const el = document.querySelector('.ai-chat-hero, [wire\\\\:id*=AiChatHero]') || document.body;
                return el.innerText.split('\\n').map(s=>s.trim()).filter(Boolean).slice(0,40).join('\\n');
            }
        """)
        (OUT / "02-chat-hero-content.txt").write_text(chat_hero, encoding="utf-8")

        # avatar dropdown
        try:
            page.locator("[aria-haspopup='menu'], .fi-user-menu, button[x-on\\:click*='userMenu']").first.click(timeout=3000)
            time.sleep(1)
        except Exception:
            try:
                # click the avatar bulat hitam
                page.locator(".fi-avatar, [class*=avatar]").first.click(timeout=2000)
                time.sleep(1)
            except Exception:
                pass
        dump(page, "19-logout-menu")
        page.keyboard.press("Escape")
        time.sleep(0.5)

        # calendar
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(2)
        try:
            page.locator("button:has-text('Kalender')").first.click(timeout=3000)
            time.sleep(2)
        except Exception:
            pass
        dump(page, "06-calendar-modal")

        # kanban (wider viewport for kanban inspection)
        # do via JS — find kanban kolom labels regardless of viewport
        page.goto(f"{BASE}/admin", wait_until="networkidle")
        time.sleep(3)
        kanban = page.evaluate("""
            () => {
                const text = document.body.innerText;
                // Extract Kanban section
                const start = text.indexOf('Papan Pekerjaan');
                if (start < 0) return 'no kanban section';
                return text.slice(start, start + 1500);
            }
        """)
        (OUT / "07-kanban-content.txt").write_text(kanban, encoding="utf-8")
        print(f"[07-kanban] kanban section saved")

        # sidebar — extract sidebar text only
        sidebar = page.evaluate("""
            () => {
                const aside = document.querySelector('aside') || document.querySelector('.fi-sidebar');
                return aside ? aside.innerText : 'no sidebar';
            }
        """)
        (OUT / "08-sidebar-collapsed.txt").write_text(sidebar, encoding="utf-8")
        print(f"[08-sidebar-collapsed] saved")

        # expand sidebar groups
        for label in ["Master Data", "Pengaturan"]:
            try:
                page.locator(f"aside button:has-text('{label}'), aside a:has-text('{label}')").first.click(timeout=2000)
                time.sleep(0.5)
            except Exception:
                pass
        time.sleep(1)
        sidebar_exp = page.evaluate("""
            () => {
                const aside = document.querySelector('aside') || document.querySelector('.fi-sidebar');
                return aside ? aside.innerText : 'no sidebar';
            }
        """)
        (OUT / "08b-sidebar-expanded.txt").write_text(sidebar_exp, encoding="utf-8")
        print(f"[08b-sidebar-expanded] saved")

        # pekerjaan list
        page.goto(f"{BASE}/admin/pekerjaans", wait_until="networkidle")
        time.sleep(2)
        dump(page, "09-pekerjaan-list")
        # extract filter tabs
        tabs = page.evaluate("""
            () => {
                const tabs = [...document.querySelectorAll('.fi-tabs-tab, [role=tab]')];
                return tabs.map(t => t.textContent.trim().replace(/\\s+/g, ' '));
            }
        """)
        (OUT / "09-filter-tabs.txt").write_text(json.dumps(tabs, ensure_ascii=False), encoding="utf-8")

        # pekerjaan detail
        page.goto(f"{BASE}/admin/pekerjaans/2", wait_until="networkidle")
        time.sleep(3)
        dump(page, "10-pekerjaan-detail")
        rels = page.evaluate("""
            () => {
                const tabs = [...document.querySelectorAll('[role=tab], button.fi-tabs-tab, .fi-tabs-tab')];
                return tabs.map(t => t.textContent.trim()).filter(Boolean);
            }
        """)
        (OUT / "10-relation-tabs.txt").write_text(json.dumps(rels, ensure_ascii=False), encoding="utf-8")

        # form tambah pekerjaan
        page.goto(f"{BASE}/admin/pekerjaans/create", wait_until="networkidle")
        time.sleep(3)
        sections = page.evaluate("""
            () => {
                const heads = [...document.querySelectorAll('.fi-section-header h3, .fi-section-header-heading, h2, h3')];
                return heads.map(h => h.textContent.trim()).filter(Boolean);
            }
        """)
        (OUT / "21-form-sections.txt").write_text(json.dumps(sections, ensure_ascii=False), encoding="utf-8")

        # laporan harian
        page.goto(f"{BASE}/admin/laporan-harians", wait_until="networkidle")
        time.sleep(2)
        dump(page, "11-laporan-harian-list")

        # audit trail
        page.goto(f"{BASE}/admin/activities", wait_until="networkidle")
        time.sleep(2)
        dump(page, "12-audit-trail")

        # master pages
        for route, name in [
            ("/admin/master/bidangs", "23-master-bidang"),
            ("/admin/master/perusahaans", "15-master-perusahaan"),
            ("/admin/master/tenaga-ahlis", "16-master-tenaga-ahli"),
            ("/admin/users", "14-pengguna-list"),
            ("/admin/system-settings", "13-system-settings"),
        ]:
            page.goto(f"{BASE}{route}", wait_until="networkidle")
            time.sleep(2)
            dump(page, name)

        vendor_login_dump(browser)
        browser.close()


if __name__ == "__main__":
    main()
