#!/usr/bin/env node
/**
 * Verify pekerjaan #18 detail page → Dokumen tab shows the auto-generated laporan,
 * AND the download chip works.
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

  const PID = process.env.PEKERJAAN_ID || '18';
  log(`Open /admin/pekerjaans/${PID}...`);
  await page.goto(`http://localhost:8010/admin/pekerjaans/${PID}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  // Click Dokumen Proyek tab
  const dokumenTab = page.getByRole('tab', { name: /dokumen proyek/i }).first();
  await dokumenTab.scrollIntoViewIfNeeded();
  await dokumenTab.click();
  log('  Tab clicked, waiting 6s for Livewire lazy hydrate...');
  await page.waitForTimeout(6000);

  // Scroll the tab content into view by scrolling page to bottom
  await page.evaluate(() => window.scrollBy(0, 800));
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${SHOTS}/dok18_tab_view.png`, fullPage: true });

  // Look for laporan row via text content
  const found = await page.evaluate(() => {
    const all = document.body.innerText;
    return {
      hasLaporan: /Laporan Pendahuluan/i.test(all),
      hasGeoteknik: /Kajian Geoteknik/i.test(all),
      tableRows: Array.from(document.querySelectorAll('table tbody tr')).map(r => r.innerText.replace(/\s+/g, ' ').slice(0, 200)),
    };
  });
  log(`  hasLaporan in body text: ${found.hasLaporan}`);
  log(`  hasGeoteknik in body text: ${found.hasGeoteknik}`);
  log(`  table rows count: ${found.tableRows.length}`);
  found.tableRows.forEach(r => log('    | ' + r));

  // Try clicking the "Unduh" action to download
  const unduh = page.getByRole('link', { name: /unduh/i }).first();
  if (await unduh.count() > 0) {
    const [dl] = await Promise.all([
      page.waitForEvent('download', { timeout: 15000 }),
      unduh.click(),
    ]).catch(() => [null]);
    if (dl) {
      const path = await dl.path();
      log(`  ✓ Download triggered: ${dl.suggestedFilename()} | path=${path}`);
    } else {
      log('  ! Unduh button clicked but no download captured');
    }
  } else {
    log('  ! No Unduh link found');
  }

  log('Done.');
  await page.waitForTimeout(3000);
  await browser.close();
})();
