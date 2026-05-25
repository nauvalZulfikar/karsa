#!/usr/bin/env node
/**
 * Quick verify: pekerjaan detail page shows the auto-generated laporan dokumen.
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const SHOTS = resolve(ROOT, 'tmp', 'smoke', 'shots');
if (!existsSync(SHOTS)) mkdirSync(SHOTS, { recursive: true });

const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.setDefaultTimeout(60_000);

  log('Login...');
  await page.goto('http://localhost:8010/admin/login');
  await page.locator('input[type="email"]').first().fill('admin@dputr.go.id');
  await page.locator('input[type="password"]').first().fill('password');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL((u) => !u.toString().includes('/login'));
  log('  ✓ logged in');

  // Get latest pekerjaan ID from env var (passed via CLI)
  const targetId = process.env.PEKERJAAN_ID || '15';
  log(`Direct nav to /admin/pekerjaans/${targetId}...`);
  await page.goto(`http://localhost:8010/admin/pekerjaans/${targetId}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000); // let Livewire hydrate
  await page.screenshot({ path: `${SHOTS}/detail_02_view.png`, fullPage: true });
  log(`  ✓ on ${page.url()}`);

  // Scroll to find dokumen section
  log('Scrolling to Dokumen relation manager...');
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/detail_03_bottom.png`, fullPage: true });

  // Find & click the "Dokumen Proyek" tab specifically
  const dokumenTab = page.getByRole('tab', { name: /dokumen proyek/i }).first();
  await dokumenTab.scrollIntoViewIfNeeded();
  await dokumenTab.click();
  log('  Tab clicked, waiting 4s for Livewire lazy-load...');
  await page.waitForTimeout(4000);

  // Scroll to absolute bottom so tab content (below tabs) is in view
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight + 1000));
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SHOTS}/detail_04_dokumen_tab.png`, fullPage: true });

  // Print what's in the tab content via DOM inspection
  const tabContent = await page.evaluate(() => {
    const rows = document.querySelectorAll('table tbody tr');
    return Array.from(rows).map(r => r.innerText.trim().slice(0, 200));
  });
  log('  Rows in any table on page:');
  tabContent.forEach(r => log('    | ' + r));

  log('Done. Screenshots saved.');
  await page.waitForTimeout(4000);
  await browser.close();
})();
