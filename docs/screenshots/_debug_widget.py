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
    page.goto(f"{BASE}/admin", wait_until="networkidle")
    time.sleep(3)
    # find the wire:id of the widget that owns Import Kontrak button
    info = page.evaluate("""
        () => {
            const btn = [...document.querySelectorAll('button')].find(b => b.textContent.includes('Import Kontrak'));
            if (!btn) return 'no btn';
            let el = btn;
            while (el && !el.hasAttribute('wire:id')) el = el.parentElement;
            const wireId = el?.getAttribute('wire:id');
            // dump HTML around the widget
            const cl = btn.closest('.fi-wi') || btn.closest('[wire\\\\:id]');
            const modalsInside = cl ? cl.querySelectorAll('.fi-modal').length : 0;
            return {wireId, widgetCls: cl?.className.slice(0,80), modalsInside};
        }
    """)
    print(info)
    # Now click button and inspect what happens
    page.locator("button:has-text('Import Kontrak')").first.click()
    time.sleep(3)
    # find all currently visible modal-windows by classes / data-open
    out = page.evaluate("""
        () => {
            // find any element with style display block in modal hierarchy
            const wins = [...document.querySelectorAll('.fi-modal')];
            return wins.map(m => ({
                id: m.id || m.getAttribute('wire:id'),
                hasOpen: !!m.querySelector('[x-data*=isOpen]'),
                visible: m.offsetWidth + m.offsetHeight > 0,
                style: m.getAttribute('style') || '',
                modalWin: m.querySelector('.fi-modal-window')?.offsetWidth || 0,
            }));
        }
    """)
    for o in out:
        print(o)

    # try clicking via JS click
    print('via JS click:')
    page.evaluate("[...document.querySelectorAll('button')].find(b => b.textContent.includes('Import Kontrak')).click()")
    time.sleep(3)
    out2 = page.evaluate("""
        () => [...document.querySelectorAll('.fi-modal-window')].filter(m => m.offsetWidth > 0).length
    """)
    print(f'visible modal-windows: {out2}')
    page.screenshot(path='_iter1/_dbg-after-click.png')
    browser.close()
