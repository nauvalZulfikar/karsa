import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
const OUT = resolve(import.meta.dirname, '..', '..', 'tmp', 'smoke', 'modal_ui');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 40 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.setDefaultTimeout(30000);

  // Login
  await page.goto('http://localhost:8010/admin/login', { waitUntil: 'networkidle' });
  const em = page.locator('input[type="email"]').first();
  const pw = page.locator('input[type="password"]').first();
  await em.click(); await em.fill('admin@dputr.go.id'); await em.blur(); await page.waitForTimeout(400);
  await pw.click(); await pw.fill('password'); await pw.blur(); await page.waitForTimeout(400);
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 30000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  // Find and click kanban card
  const card = page.locator('.kanban-card').first();
  if (await card.count() > 0) {
    await card.click();
    await page.waitForTimeout(1000);
    await page.screenshot({ path: `${OUT}/01_info_tab.png` });

    // Click Milestone tab
    const tabs = page.locator('.kanban-modal-body button');
    const allTabs = await tabs.all();
    for (const t of allTabs) {
      const txt = await t.textContent();
      if (txt.includes('Milestone')) {
        await t.click();
        break;
      }
    }
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${OUT}/02_milestone_tab.png` });

    // Scroll down in modal to see more
    const modal = page.locator('.kanban-modal');
    await modal.evaluate(el => el.scrollTop = 300);
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${OUT}/03_milestone_scrolled.png` });
  }

  await page.waitForTimeout(1000);
  await browser.close();
})();
