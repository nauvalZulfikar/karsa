#!/usr/bin/env node
/**
 * Focused E2E: upload KAK → ask AI to extract → assert field values.
 * Run: node scripts/smoke/test_kak_e2e.mjs
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const SHOTS = resolve(ROOT, 'tmp', 'smoke', 'kak_e2e');
if (!existsSync(SHOTS)) mkdirSync(SHOTS, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const KAK = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf';

// Expected values di KAK doc
const EXPECTED = {
  namaPekerjaan: 'KAJIAN GEOTEKNIK STABILITAS TANAH',
  lokasi:        'Lebakmuncang',         // substring check
  pagu:          ['100.000.000', '100000000', '100 juta'], // any of these
  durasi:        '30',
  tanggalKak:    '2025-12-19',
  ppkNama:       'Widya Astuti',          // substring
  ppkNip:        '19790405',              // substring
};

const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);
const shot = (page, name) => page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false });

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60_000);

  page.on('pageerror', (e) => log(`  [PAGE ERROR] ${e.message}`));

  let exitCode = 0;
  try {
    log('1. Login...');
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    const emailIn = page.locator('input[type="email"]').first();
    const passwordIn = page.locator('input[type="password"]').first();
    await emailIn.click(); await emailIn.fill(EMAIL); await emailIn.blur();
    await page.waitForTimeout(400);
    await passwordIn.click(); await passwordIn.fill(PASSWORD); await passwordIn.blur();
    await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    log(`  ✓ logged in: ${page.url()}`);
    await shot(page, '01_logged_in');

    log('2. Opening chat widget...');
    const toggle = page.locator('button.ai-fc-toggle');
    await toggle.waitFor({ state: 'visible', timeout: 10_000 });
    await toggle.click();
    await page.waitForTimeout(500);
    await shot(page, '02_chat_opened');

    log('3. Uploading KAK PDF...');
    const fileInput = page.locator('input[x-ref="filein"]');
    await fileInput.setInputFiles(KAK);
    await page.waitForFunction(
      () => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length >= 1,
      null,
      { timeout: 60_000 }
    ).catch(() => {});
    await page.waitForTimeout(2000);
    await shot(page, '03_kak_uploaded');

    log('4. Typing prompt: ekstrak semua data dari KAK...');
    const textInput = page.locator('input.ai-fc-input');
    await textInput.fill(
      'Tolong ekstrak SEMUA data penting dari KAK yang baru gua upload. Pakai parse_kak_pdf. Tampilkan: nama pekerjaan, lokasi, nilai pagu, durasi, tanggal KAK, nama PPK, NIP PPK, jabatan PPK, instansi.'
    );
    await page.locator('button.ai-fc-send').click();
    await shot(page, '04_prompt_sent');

    log('5. Waiting for AI reply (up to 3 min)...');
    const t0 = Date.now();
    let baseline = await page.locator('.ai-fc-bubble-assistant').count();
    let reply = null;

    while (Date.now() - t0 < 180_000) {
      const msgs = await page.locator('.ai-fc-bubble-assistant').all();
      if (msgs.length > baseline) {
        const last = msgs[msgs.length - 1];
        const txt = (await last.textContent()) || '';
        if (txt.trim().length > 100 && !txt.includes('Halo! Saya asisten AI')) {
          reply = txt.trim();
          break;
        }
      }
      const elapsed = Math.floor((Date.now() - t0) / 1000);
      if (elapsed % 20 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
      await page.waitForTimeout(2000);
    }

    await shot(page, '05_ai_replied');

    if (!reply) {
      log(`  ✗ TIMEOUT — no AI reply in 180s`);
      exitCode = 1;
    } else {
      log(`  ✓ Got reply in ${((Date.now() - t0) / 1000).toFixed(1)}s`);
      log(`  --- REPLY ---`);
      console.log(reply);
      log(`  --- /REPLY ---`);

      // Assertions
      const checks = [
        { name: 'nama_pekerjaan',     match: reply.toLowerCase().includes(EXPECTED.namaPekerjaan.toLowerCase()) },
        { name: 'lokasi (Lebakmuncang)', match: reply.toLowerCase().includes(EXPECTED.lokasi.toLowerCase()) },
        { name: 'pagu 100jt',          match: EXPECTED.pagu.some(p => reply.toLowerCase().includes(p.toLowerCase())) },
        { name: 'durasi 30 hari',      match: /\b30\b/.test(reply) && /hari/i.test(reply) },
        { name: 'tanggal_kak 2025-12-19', match: reply.includes(EXPECTED.tanggalKak) || (reply.includes('19') && /desember/i.test(reply) && reply.includes('2025')) },
        { name: 'ppk_nama (Widya Astuti)', match: reply.toLowerCase().includes(EXPECTED.ppkNama.toLowerCase()) },
        { name: 'ppk_nip (197904...)',   match: reply.includes(EXPECTED.ppkNip) },
      ];

      let pass = 0;
      log('\n=== ASSERTIONS ===');
      for (const c of checks) {
        const mark = c.match ? '✓' : '✗';
        log(`  ${mark} ${c.name}`);
        if (c.match) pass++;
      }
      log(`=== ${pass}/${checks.length} passed ===\n`);
      if (pass < checks.length) {
        exitCode = 1;
        log(`✗ FAIL — ${checks.length - pass} assertions failed`);
      } else {
        log('✅ ALL ASSERTIONS PASSED');
      }
    }

    await page.waitForTimeout(3000);
  } catch (e) {
    log(`✗ EXCEPTION: ${e.message}`);
    await shot(page, '99_error');
    exitCode = 1;
  } finally {
    await browser.close();
    process.exit(exitCode);
  }
})();
