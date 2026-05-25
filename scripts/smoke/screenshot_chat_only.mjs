import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
const OUT = resolve(import.meta.dirname, '..', '..', 'tmp', 'smoke', 'ui_check');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
const log = (m) => console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`);
(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.setDefaultTimeout(60_000);
  try {
    log('login...');
    await page.goto('http://localhost:8010/admin/login', { waitUntil: 'networkidle' });
    const em = page.locator('input[type="email"]').first();
    const pw = page.locator('input[type="password"]').first();
    await em.click(); await em.fill('admin@dputr.go.id'); await em.blur(); await page.waitForTimeout(400);
    await pw.click(); await pw.fill('password'); await pw.blur(); await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'));
    await page.waitForLoadState('networkidle');
    log('open chat...');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(700);
    await page.locator('input.ai-fc-input').fill('halo bos');
    await page.locator('button.ai-fc-send').click();
    await page.waitForTimeout(8000);
    // Screenshot only the chat panel
    const panel = page.locator('.ai-fc-panel');
    await panel.screenshot({ path: `${OUT}/panel_only.png` });
    log(`done`);
  } finally {
    await page.waitForTimeout(500);
    await browser.close();
  }
})();
