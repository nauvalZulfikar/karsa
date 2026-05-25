#!/usr/bin/env node
/**
 * Multi-doc E2E: upload KAK + SPK/SPMK + Negosiasi → AI summarizes all → assert
 * Tests: parse_multiple_docs path + new KAK parser behavior
 * Run: node scripts/smoke/test_multidoc_e2e.mjs
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const SHOTS = resolve(ROOT, 'tmp', 'smoke', 'multidoc_e2e');
if (!existsSync(SHOTS)) mkdirSync(SHOTS, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const DOC_ROOT = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah';
const DOCS = [
  { name: 'KAK',       file: `${DOC_ROOT}/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf` },
  { name: 'SPK/SPMK',  file: `${DOC_ROOT}/Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf` },
  { name: 'Negosiasi', file: `${DOC_ROOT}/Lampiran Negosiasi Konsultan Konstruksi.pdf` },
];

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
    log(`  ✓ logged in`);

    log('2. Opening chat...');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(500);

    log('3. Uploading 3 docs sequentially...');
    const fileInput = page.locator('input[x-ref="filein"]');
    for (let i = 0; i < DOCS.length; i++) {
      const d = DOCS[i];
      log(`   ${i + 1}. ${d.name}`);
      await fileInput.setInputFiles(d.file);
      await page.waitForFunction(
        (n) => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length >= n,
        i + 1,
        { timeout: 60_000 }
      ).catch(() => {});
      await page.waitForTimeout(2000);
    }
    await shot(page, '03_all_uploaded');

    log('4. Sending prompt: extract dari semua 3 docs...');
    const textInput = page.locator('input.ai-fc-input');
    await textInput.fill(
      'Tolong ekstrak data dari 3 dokumen yang baru gua upload. Pakai parse_multiple_docs dengan types: kak (KAK PDF), kontrak (SPK/SPMK PDF), rab (Lampiran Negosiasi PDF). Setelah itu kasih ringkasan: nama pekerjaan, lokasi, pagu, nilai kontrak, PPK, durasi, tanggal mulai, tanggal akhir, no_spk, no_spmk, vendor.'
    );
    await page.locator('button.ai-fc-send').click();
    await shot(page, '04_prompt_sent');

    log('5. Waiting for AI reply (up to 5 min — parse 3 docs)...');
    const t0 = Date.now();
    let baseline = await page.locator('.ai-fc-bubble-assistant').count();
    let reply = null;

    while (Date.now() - t0 < 300_000) {
      const msgs = await page.locator('.ai-fc-bubble-assistant').all();
      if (msgs.length > baseline) {
        const last = msgs[msgs.length - 1];
        const txt = (await last.textContent()) || '';
        if (txt.trim().length > 150 && !txt.includes('Halo! Saya asisten AI')) {
          reply = txt.trim();
          break;
        }
      }
      const elapsed = Math.floor((Date.now() - t0) / 1000);
      if (elapsed % 30 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
      await page.waitForTimeout(2000);
    }

    await shot(page, '05_ai_replied');

    if (!reply) {
      log(`  ✗ TIMEOUT — no AI reply in 300s`);
      exitCode = 1;
    } else {
      log(`  ✓ Got reply in ${((Date.now() - t0) / 1000).toFixed(1)}s`);
      log(`  --- REPLY ---`);
      console.log(reply);
      log(`  --- /REPLY ---`);

      // Assertions from MIXED data sources
      const l = reply.toLowerCase();
      const checks = [
        // From KAK
        { name: 'KAK: Lebakmuncang',     match: l.includes('lebakmuncang') },
        { name: 'KAK: pagu 100jt',       match: l.includes('100.000.000') || l.includes('100000000') },
        { name: 'KAK: 30 hari',          match: /\b30\b/.test(reply) && /hari/i.test(reply) },
        { name: 'KAK: Widya Astuti',     match: l.includes('widya astuti') },
        // From Kontrak/SPK
        { name: 'Kontrak: nilai 99jt',   match: l.includes('99.594.750') || l.includes('99594750') },
        { name: 'Kontrak: ITERGO',       match: l.includes('itergo') },
        // From SPK/SPMK dates
        { name: 'tanggal_mulai 2026-01-05', match: l.includes('2026-01-05') || (l.includes('5 januari') && l.includes('2026')) || (l.includes('05/01/2026')) },
        { name: 'tanggal_akhir 2026-02-03', match: l.includes('2026-02-03') || (l.includes('3 februari') && l.includes('2026')) || l.includes('03/02/2026') },
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
        log(`✗ Some assertions failed`);
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
