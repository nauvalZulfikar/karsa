#!/usr/bin/env node
/**
 * E2E via chat: upload 4 docs dari submit folder PT. Purna Wahana (topografi proyek),
 * create pekerjaan, verify laporan auto-generated with topografi template.
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT_DIR = resolve(ROOT, 'tmp', 'smoke', 'topo_e2e');
if (!existsSync(OUT_DIR)) mkdirSync(OUT_DIR, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const SUBMIT_DIR = 'D:/Downloads/coding project/project_management/doc-kajian-pemetaan-topografi/submit';
const DOCS = [
  { label: 'KAK',       file: `${SUBMIT_DIR}/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf` },
  { label: 'SPK/SPMK',  file: `${SUBMIT_DIR}/Spk,Spmk,Ba PT Purnawahana L - Kajian Topografi.pdf` },
  { label: 'Negosiasi', file: `${SUBMIT_DIR}/Lampiran Negosiasi Konsultan Konstruksi.pdf` },
];

const PROMPT = 'Bikin proyek baru dari 3 dokumen yg gua upload. Pakai parse_multiple_docs dengan types: kak (PDF KAK), kontrak (PDF SPK Purna Wahana — ini proyek TOPOGRAFI, vendor PT. PURNA WAHANA LESTARI), rab (Lampiran Negosiasi). Lalu create_pekerjaan dengan SEMUA field hasil parse: nama_pekerjaan dari SPK ("Kajian Topografi"), perusahaan_nama "PT. PURNA WAHANA LESTARI", lokasi dari KAK field lokasi_pekerjaan, termin & milestones. Setelah pekerjaan dibuat, kasih ID + link laporan.';

const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);
const shot = (page, name) => page.screenshot({ path: `${OUT_DIR}/${name}.png`, fullPage: false });

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60_000);
  page.on('pageerror', (e) => log(`  [PAGE ERROR] ${e.message}`));

  let exitCode = 0;
  let resultJson = { success: false };

  try {
    log('1. Login...');
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    await page.locator('input[type="email"]').first().fill(EMAIL);
    await page.locator('input[type="password"]').first().fill(PASSWORD);
    await page.locator('input[type="password"]').first().blur();
    await page.waitForTimeout(300);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    log('  ✓ logged in');

    log('2. Opening chat widget...');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(700);

    log('3. Uploading 4 docs sequentially...');
    const fileInput = page.locator('input[x-ref="filein"]');
    for (let i = 0; i < DOCS.length; i++) {
      const d = DOCS[i];
      log(`   ${i + 1}. ${d.label}`);
      await fileInput.setInputFiles(d.file);
      await page.waitForFunction(
        (n) => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length >= n,
        i + 1, { timeout: 90_000 }
      ).catch(() => {});
      await page.waitForTimeout(2500);
    }
    await shot(page, '02_uploaded');

    log('4. Sending prompt...');
    await page.locator('input.ai-fc-input').fill(PROMPT);
    await page.locator('button.ai-fc-send').click();

    log('5. Waiting AI reply (up to 8 min)...');
    const baseline = await page.locator('.ai-fc-bubble-assistant').count();
    const t0 = Date.now();
    let finalReply = null;

    while (Date.now() - t0 < 480_000) {
      const msgs = await page.locator('.ai-fc-bubble-assistant').all();
      if (msgs.length > baseline) {
        const last = msgs[msgs.length - 1];
        const txt = (await last.textContent()) || '';
        if (txt.includes('Unduh Laporan') || (txt.includes('proyek') && (txt.includes('berhasil') || txt.includes('ID')))) {
          await page.waitForTimeout(5000);
          const txt2 = (await last.textContent()) || '';
          if (txt2.length > 100) {
            finalReply = txt2.trim();
            break;
          }
        }
      }
      const elapsed = Math.floor((Date.now() - t0) / 1000);
      if (elapsed % 30 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
      await page.waitForTimeout(2000);
    }

    await shot(page, '04_replied');
    if (!finalReply) {
      log('  ✗ TIMEOUT');
      exitCode = 1;
    } else {
      const elapsed = Math.round((Date.now() - t0) / 1000);
      log(`  ✓ Got reply in ${elapsed}s (length=${finalReply.length})`);
      log('  --- REPLY ---');
      console.log(finalReply.slice(0, 1000));
      log('  --- /REPLY ---');
      const pekerjaanId = (finalReply.match(/ID[:\s]*(\d+)/i) || [])[1];
      resultJson = {
        success: true,
        elapsed_seconds: elapsed,
        pekerjaan_id: pekerjaanId,
        reply_preview: finalReply.slice(0, 700),
      };
    }
    writeFileSync(`${OUT_DIR}/result.json`, JSON.stringify(resultJson, null, 2));
    log(`6. Result saved: ${OUT_DIR}/result.json`);

  } catch (e) {
    log(`✗ EXCEPTION: ${e.message}`);
    await shot(page, '99_error');
    writeFileSync(`${OUT_DIR}/result.json`, JSON.stringify({ success: false, error: e.message }, null, 2));
    exitCode = 1;
  } finally {
    await page.waitForTimeout(3000);
    await browser.close();
    process.exit(exitCode);
  }
})();
