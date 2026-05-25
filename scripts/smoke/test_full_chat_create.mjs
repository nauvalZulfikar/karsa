#!/usr/bin/env node
/**
 * FULL E2E via browser chat widget:
 *   1. Login
 *   2. Open chat widget
 *   3. Upload 4 docs (KAK + SPK + Negosiasi + Penawaran xlsx)
 *   4. Type "bikin proyek baru pakai 4 dokumen ini"
 *   5. Wait AI parse → create_pekerjaan → auto-generate laporan (~3-5 min)
 *   6. Extract dokumen download URL from reply
 *   7. Save reply transcript + screenshots
 *
 * Output: tmp/smoke/full_chat/{result.json, screenshots}
 * Result JSON includes: pekerjaan_id, dokumen_download_url, reply_text
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT_DIR = resolve(ROOT, 'tmp', 'smoke', 'full_chat');
if (!existsSync(OUT_DIR)) mkdirSync(OUT_DIR, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const DOC_ROOT = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah';
const DOCS = [
  { label: 'KAK',       file: `${DOC_ROOT}/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf` },
  { label: 'SPK/SPMK',  file: `${DOC_ROOT}/Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf` },
  { label: 'Negosiasi', file: `${DOC_ROOT}/Lampiran Negosiasi Konsultan Konstruksi.pdf` },
  { label: 'Penawaran', file: `${DOC_ROOT}/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK/penawaran-PT. ITERGO BUANA UTAMA.xlsx` },
];

const PROMPT = 'Tolong bikin proyek baru pakai 4 dokumen yang baru gua upload (KAK, SPK/SPMK, Negosiasi/RAB, Penawaran xlsx). Pakai parse_multiple_docs untuk parse semua. Lalu create_pekerjaan dengan semua data hasil parse (termin & milestones juga). Setelah pekerjaan dibuat, kasih ID-nya dan link download laporan pendahuluan.';

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
    const emailIn = page.locator('input[type="email"]').first();
    const pwIn    = page.locator('input[type="password"]').first();
    await emailIn.click(); await emailIn.fill(EMAIL); await emailIn.blur(); await page.waitForTimeout(300);
    await pwIn.click();    await pwIn.fill(PASSWORD); await pwIn.blur();    await page.waitForTimeout(300);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    log('  ✓ logged in');

    log('2. Opening chat widget...');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(700);
    await shot(page, '01_chat_open');

    log(`3. Uploading ${DOCS.length} docs sequentially...`);
    const fileInput = page.locator('input[x-ref="filein"]');
    for (let i = 0; i < DOCS.length; i++) {
      const d = DOCS[i];
      log(`   ${i + 1}. ${d.label} (${d.file.split('/').pop()})`);
      await fileInput.setInputFiles(d.file);
      await page.waitForFunction(
        (n) => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length >= n,
        i + 1,
        { timeout: 90_000 }
      ).catch(() => {});
      await page.waitForTimeout(2500);
    }
    await shot(page, '02_uploaded');

    log('4. Sending prompt: bikin proyek baru...');
    const textInput = page.locator('input.ai-fc-input');
    await textInput.fill(PROMPT);
    await page.locator('button.ai-fc-send').click();
    await shot(page, '03_prompt_sent');

    log('5. Waiting AI to complete full flow (parse → create → laporan) — up to 6 min...');
    const baseline = await page.locator('.ai-fc-bubble-assistant').count();
    const t0 = Date.now();
    let finalReply = null;

    while (Date.now() - t0 < 360_000) {
      const msgs = await page.locator('.ai-fc-bubble-assistant').all();
      if (msgs.length > baseline) {
        const last = msgs[msgs.length - 1];
        const txt = (await last.textContent()) || '';
        // Wait until reply mentions dokumen download (link or proyek created)
        if (txt.includes('Unduh Laporan') || txt.includes('proyek') && (txt.includes('berhasil') || txt.includes('ID') || txt.includes('id'))) {
          // Wait extra 5s to ensure reply is complete
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
      log('  ✗ TIMEOUT — no AI reply detected with proyek+laporan markers');
      exitCode = 1;
    } else {
      const elapsed = Math.round((Date.now() - t0) / 1000);
      log(`  ✓ Got reply in ${elapsed}s (length=${finalReply.length})`);
      log('  --- REPLY (first 800 chars) ---');
      console.log(finalReply.slice(0, 800));
      log('  --- /REPLY ---');

      // Extract dokumen ID + pekerjaan info from reply
      const dokumenUrl = (finalReply.match(/https?:\/\/[^\s)]+\/dokumen\/(\d+)\/download/) || [])[0];
      const dokumenId  = (finalReply.match(/dokumen\/(\d+)\/download/) || [])[1];
      const pekerjaanId = (finalReply.match(/ID[:\s]*(\d+)|id[:\s]*(\d+)|proyek[\w\s]+(\d+)/i) || [])[1];

      resultJson = {
        success: !!dokumenUrl,
        elapsed_seconds: elapsed,
        pekerjaan_id_from_reply: pekerjaanId || null,
        dokumen_id: dokumenId || null,
        dokumen_download_url: dokumenUrl || null,
        reply_preview: finalReply.slice(0, 500),
        full_reply_chars: finalReply.length,
      };
      log(`  → dokumen URL: ${dokumenUrl}`);
      log(`  → dokumen ID: ${dokumenId}`);
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
