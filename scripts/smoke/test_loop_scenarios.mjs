#!/usr/bin/env node
/**
 * Test loop scenario L2 (duplicate_spk):
 *   1. Upload 3 docs (Purna Wahana topografi yg sama no_spk dgn pekerjaan #2 existing)
 *   2. AI parse + try create → tool returns duplicate_spk error
 *   3. AI shows error to user, asks "update existing atau ganti SPK?"
 *   4. User replies "iya pakai existing aja, lanjut"
 *   5. ✓ EXPECTED: AI skip create, lanjut ke next step (assign vendor / give laporan link)
 *   6. ✗ BUG (loop): AI asks same question again
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT_DIR = resolve(ROOT, 'tmp', 'smoke', 'loop_test');
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

const PROMPT_INITIAL = 'JALANIN SEKARANG (file sudah TERATTACH semua, JANGAN minta upload lagi): parse_multiple_docs dengan 3 file terlampir (type kak=KAK PDF, kontrak=SPK/SPMK PDF, rab=Lampiran Negosiasi PDF), lalu create_pekerjaan dengan SEMUA field hasil parse termasuk lokasi, termin, milestones. Jangan tanya konfirmasi apapun, kerjain sekarang. Kalau ada warning duplikat, BERHENTI dan tanya gua.';

const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);
const shot = (page, name) => page.screenshot({ path: `${OUT_DIR}/${name}.png`, fullPage: false });

async function waitForReply(page, baselineCount, timeoutMs = 300_000) {
  const t0 = Date.now();
  while (Date.now() - t0 < timeoutMs) {
    const msgs = await page.locator('.ai-fc-bubble-assistant').all();
    if (msgs.length > baselineCount) {
      const last = msgs[msgs.length - 1];
      const txt = (await last.textContent()) || '';
      if (txt.length > 60) {
        await page.waitForTimeout(4000);
        const txt2 = (await last.textContent()) || '';
        return { text: txt2.trim(), count: msgs.length, elapsed: Date.now() - t0 };
      }
    }
    const elapsed = Math.floor((Date.now() - t0) / 1000);
    if (elapsed % 30 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
    await page.waitForTimeout(2000);
  }
  return null;
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60_000);
  page.on('pageerror', (e) => log(`  [PAGE ERROR] ${e.message}`));

  let exitCode = 0;
  const resultJson = { turns: [] };

  try {
    log('1. Login...');
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    const emailIn = page.locator('input[type="email"]').first();
    const pwIn    = page.locator('input[type="password"]').first();
    await emailIn.click(); await emailIn.fill(EMAIL); await emailIn.blur(); await page.waitForTimeout(400);
    await pwIn.click();    await pwIn.fill(PASSWORD); await pwIn.blur();    await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    log('  ✓ logged in');

    log('2. Open chat widget...');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(700);

    log('3. Upload 3 docs (will trigger duplicate_spk vs pekerjaan #2)...');
    const fileInput = page.locator('input[x-ref="filein"]');
    for (let i = 0; i < DOCS.length; i++) {
      log(`   ${i + 1}. ${DOCS[i].label}`);
      await fileInput.setInputFiles(DOCS[i].file);
      // Wait for chip to appear (Livewire confirms upload)
      await page.waitForFunction(
        (n) => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length >= n,
        i + 1, { timeout: 90_000 }
      ).catch(() => {});
      await page.waitForTimeout(2500);
    }
    log('   ✓ All 3 files uploaded + chips visible');

    log('=== TURN 1: send initial create prompt ===');
    const textInput = page.locator('input.ai-fc-input');
    await textInput.fill(PROMPT_INITIAL);
    const baseline1 = await page.locator('.ai-fc-bubble-assistant').count();
    await page.locator('button.ai-fc-send').click();
    log('Waiting AI turn 1 reply (~3 min for parse 3 docs)...');
    const turn1 = await waitForReply(page, baseline1, 360_000);
    if (!turn1) { log('✗ TURN 1 TIMEOUT'); exitCode = 1; throw new Error('turn1 timeout'); }
    log(`✓ Turn 1 reply (${Math.round(turn1.elapsed/1000)}s, len=${turn1.text.length}):`);
    log(`  ${turn1.text.slice(0, 400)}`);
    resultJson.turns.push({ n: 1, text: turn1.text, elapsed: turn1.elapsed });
    await shot(page, '01_turn1');

    const t1lower = turn1.text.toLowerCase();
    const isDuplicateQuestion = t1lower.includes('sudah ada') || t1lower.includes('mirip') || t1lower.includes('existing') || t1lower.includes('update');
    if (!isDuplicateQuestion) {
      log(`! Turn 1 doesn't look like duplicate question. Maybe created directly.`);
      resultJson.note = 'turn1 not duplicate question';
    }

    log('\n=== TURN 2: user replies "iya pakai existing aja, lanjut" ===');
    const baseline2 = await page.locator('.ai-fc-bubble-assistant').count();
    await textInput.fill('iya pakai existing aja, lanjut');
    await page.locator('button.ai-fc-send').click();
    log('Waiting AI turn 2 reply...');
    const turn2 = await waitForReply(page, baseline2, 240_000);
    if (!turn2) { log('✗ TURN 2 TIMEOUT'); exitCode = 1; throw new Error('turn2 timeout'); }
    log(`✓ Turn 2 reply (${Math.round(turn2.elapsed/1000)}s, len=${turn2.text.length}):`);
    log(`  ${turn2.text.slice(0, 400)}`);
    resultJson.turns.push({ n: 2, text: turn2.text, elapsed: turn2.elapsed });
    await shot(page, '02_turn2');

    const t2lower = turn2.text.toLowerCase();
    const repeatedQuestion = (t1lower.includes('mau update') && t2lower.includes('mau update'))
      || (t1lower.includes('mau pakai existing') && t2lower.includes('mau pakai existing'))
      || (t1lower.includes('apakah anda ingin') && t2lower.includes('apakah anda ingin'))
      || (t1lower.includes('mengganti') && t2lower.includes('mengganti'))
      || (t1lower.includes('atau bikin baru') && t2lower.includes('atau bikin baru'));
    const indicatesProgress = t2lower.includes('berhasil') || t2lower.includes('vendor') || t2lower.includes('personil') || t2lower.includes('laporan') || t2lower.includes('assign');

    log('\n=== INTERMEDIATE ASSERTIONS (after Turn 2) ===');
    if (repeatedQuestion) {
      log(`  ✗ FAIL: AI repeated same question (BUG: looping)`);
      exitCode = 1;
    } else {
      log(`  ✓ PASS: AI did NOT repeat same question (Turn 2)`);
    }

    // If turn 2 still asks something rather than progressing, send turn 3
    const turn2AsksDuplicate = t2lower.includes('sudah ada') || t2lower.includes('mau update');
    if (turn2AsksDuplicate && !indicatesProgress) {
      log('\n=== TURN 3: AI still asking, user replies "update pekerjaan yang existing, lanjut aja" ===');
      const baseline3 = await page.locator('.ai-fc-bubble-assistant').count();
      await textInput.fill('update pekerjaan yang existing, lanjut aja');
      await page.locator('button.ai-fc-send').click();
      const turn3 = await waitForReply(page, baseline3, 240_000);
      if (!turn3) { log('✗ TURN 3 TIMEOUT'); exitCode = 1; }
      else {
        log(`✓ Turn 3 reply (${Math.round(turn3.elapsed/1000)}s, len=${turn3.text.length}):`);
        log(`  ${turn3.text.slice(0, 400)}`);
        resultJson.turns.push({ n: 3, text: turn3.text, elapsed: turn3.elapsed });
        const t3lower = turn3.text.toLowerCase();
        const t3RepeatedDuplicate = t3lower.includes('mau update') || t3lower.includes('ganti nomor spk');
        const t3IndicatesProgress = t3lower.includes('berhasil') || t3lower.includes('vendor') || t3lower.includes('laporan') || t3lower.includes('assign') || t3lower.includes('rab') || t3lower.includes('rencana');
        if (t3RepeatedDuplicate) {
          log(`  ✗ FAIL: AI STILL asks duplicate question (LOOP CONFIRMED)`);
          exitCode = 1;
        } else {
          log(`  ✓ PASS: AI no longer asks duplicate question (Turn 3)`);
        }
        if (t3IndicatesProgress) {
          log(`  ✓ PASS: AI advanced to next step (Turn 3)`);
        } else {
          log(`  ! WARN: Turn 3 still no progress`);
        }
        resultJson.turn3_repeated = t3RepeatedDuplicate;
        resultJson.turn3_progress = t3IndicatesProgress;
      }
      await shot(page, '03_turn3');
    } else if (indicatesProgress) {
      log(`  ✓ PASS: AI advanced to next step immediately at Turn 2 — loop avoided`);
    }
    resultJson.repeated_question = repeatedQuestion;
    resultJson.indicates_progress = indicatesProgress;

  } catch (e) {
    log(`✗ EXCEPTION: ${e.message}`);
    await shot(page, '99_error');
    resultJson.exception = e.message;
    exitCode = 1;
  } finally {
    writeFileSync(`${OUT_DIR}/result.json`, JSON.stringify(resultJson, null, 2));
    log(`\nResult saved: ${OUT_DIR}/result.json`);
    await page.waitForTimeout(3000);
    await browser.close();
    process.exit(exitCode);
  }
})();
