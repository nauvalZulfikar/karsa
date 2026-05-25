"""Verify 7 relation tabs render on Pekerjaan detail page."""
import sys, time
from playwright.sync_api import sync_playwright
sys.stdout.reconfigure(encoding="utf-8", errors="replace")
BASE = "http://localhost:8010"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_context(viewport={"width": 1440, "height": 900}).new_page()
    page.goto(f"{BASE}/admin/login")
    page.fill("input[type=email]", "admin@dputr.go.id")
    page.fill("input[type=password]", "password")
    page.click("button[type=submit]")
    time.sleep(4)
    page.goto(f"{BASE}/admin/pekerjaans/2", wait_until="networkidle")
    time.sleep(3)
    tabs = page.evaluate("""
        () => {
            const tabs = [...document.querySelectorAll('[role=tab], button.fi-tabs-tab, .fi-tabs-tab')];
            const labels = tabs.map(t => t.textContent.trim().slice(0,40));
            return {count: tabs.length, labels: labels};
        }
    """)
    print(tabs)
    # also look for the relation manager headers
    rels = page.evaluate("""
        () => {
            const want = ['Personil', 'Vendor', 'Rencana Pengadaan', 'Realisasi Pengadaan', 'Dokumen', 'Termin Pembayaran', 'Milestone'];
            const out = {};
            for (const w of want) {
                out[w] = !!document.body.innerText.includes(w);
            }
            return out;
        }
    """)
    print("relation labels visible:", rels)
    # also check kanban kolom count
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(2)
    k = page.evaluate("""
        () => {
            const cols = [...document.querySelectorAll('[wire\\\\:id*=KanbanPekerjaan] h3, [wire\\\\:id*=KanbanPekerjaan] .kanban-col-header, [wire\\\\:id*=KanbanPekerjaan] [data-col]')];
            // generic: count flex children inside kanban widget
            const root = document.querySelector('[wire\\\\:id*=KanbanPekerjaan], #kanban, .kanban-board') || document.querySelector('[class*=Kanban]');
            return {colHeaderCount: cols.length, bodyText: document.body.innerText.split('Papan Pekerjaan')[1]?.slice(0,400)};
        }
    """)
    print("kanban:", k)
    browser.close()
