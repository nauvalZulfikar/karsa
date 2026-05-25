#!/usr/bin/env node
/**
 * Edge-case E2E: test dokumen yang BUKAN KAK utama.
 * Goal: pastikan parser ga crash, AI handle dengan benar (summarize atau bilang
 * "ini bukan KAK/Kontrak/RAB").
 *
 * Run: node scripts/smoke/test_extra_docs_e2e.mjs
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const SHOTS = resolve(ROOT, 'tmp', 'smoke', 'extra_docs_e2e');
if (!existsSync(SHOTS)) mkdirSync(SHOTS, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const DOC_ROOT = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah';
const TEST_CASES = [
  {
    label: 'PQ (kualifikasi)',
    file: `${DOC_ROOT}/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK/PQ - ITERGO BUANA UTAMA.pdf`,
    prompt: 'Tolong baca dokumen yang gua upload. Kasih ringkasan apa isinya, dokumen tipe apa, untuk pekerjaan apa.',
    expectations: ['itergo', 'kualifikasi', 'geoteknik'],
    forbidDefaults: false,
  },
  {
    label: 'TA Team Leader',
    file: `${DOC_ROOT}/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK/TA/1. Rian Hendriawan, ST_Team Leader.pdf`,
    prompt: 'Dokumen ini CV/sertifikat tenaga ahli. Tolong ekstrak nama, posisi, dan kualifikasi-nya.',
    expectations: ['rian', 'team leader'],
    forbidDefaults: false,
  },
  {
    label: 'Penawaran XLSX',
    file: `${DOC_ROOT}/PRINT PT. ITERGO BUANA UTAMA GEOTEKNIK/penawaran-PT. ITERGO BUANA UTAMA.xlsx`,
    prompt: 'Tolong baca file penawaran ini. Pakai parse_penawaran_pdf. Kasih ringkasan: nama vendor, nilai penawaran, item-item utama kalau ada.',
    expectations: ['itergo', 'penawaran'],
    forbidDefaults: false,
  },
];

const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);
const shot = (page, name) => page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false });

async function loginAndOpenChat(page) {
  log('  Login...');
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
  log('  ✓ logged in');

  log('  Opening chat...');
  await page.locator('button.ai-fc-toggle').click();
  await page.waitForTimeout(500);
}

async function runCase(page, tc, idx) {
  log(`\n[Case ${idx + 1}] ${tc.label}`);
  log(`  File: ${tc.file.split('/').pop()}`);

  // Click chat clear button if exists; else just upload new file
  const fileInput = page.locator('input[x-ref="filein"]');
  const beforeChips = await page.locator('.ai-fc-chip, [class*="chip"], [class*="attachment"]').count();

  await fileInput.setInputFiles(tc.file);
  await page.waitForFunction(
    (n) => document.querySelectorAll('.ai-fc-chip, [class*="chip"], [class*="attachment"]').length > n,
    beforeChips,
    { timeout: 60_000 }
  ).catch(() => {});
  await page.waitForTimeout(2500);
  await shot(page, `${idx + 1}_uploaded`);
  log(`  ✓ uploaded`);

  const textInput = page.locator('input.ai-fc-input');
  await textInput.fill(tc.prompt);
  const baselineMsgs = await page.locator('.ai-fc-bubble-assistant').count();
  await page.locator('button.ai-fc-send').click();
  await shot(page, `${idx + 1}_sent`);

  log(`  Waiting AI reply (max 3 min)...`);
  const t0 = Date.now();
  let reply = null;
  while (Date.now() - t0 < 180_000) {
    const msgs = await page.locator('.ai-fc-bubble-assistant').all();
    if (msgs.length > baselineMsgs) {
      const last = msgs[msgs.length - 1];
      const txt = (await last.textContent()) || '';
      if (txt.trim().length > 80 && !txt.includes('Halo! Saya asisten AI')) {
        reply = txt.trim();
        break;
      }
    }
    const elapsed = Math.floor((Date.now() - t0) / 1000);
    if (elapsed % 30 === 0 && elapsed > 0) log(`    ...waiting ${elapsed}s`);
    await page.waitForTimeout(2000);
  }
  await shot(page, `${idx + 1}_replied`);

  if (!reply) {
    log(`  ✗ TIMEOUT — no reply`);
    return { pass: false, reason: 'timeout' };
  }
  log(`  ✓ Got reply in ${((Date.now() - t0) / 1000).toFixed(1)}s`);
  log(`  --- REPLY ---`);
  console.log(reply.slice(0, 800));
  log(`  --- /REPLY ---`);

  const l = reply.toLowerCase();

  // Pastikan ga ada hallucinated default (e.g., "1 Januari 2026" untuk doc yang bukan KAK)
  const hallucinationFlags = [
    { sig: 'kepala bidang bangunan gedung dan pengembangan permukiman', desc: 'leaked KAK PPK jabatan ke dokumen non-KAK' },
    // Add more flags if patterns emerge
  ];
  const leaks = hallucinationFlags.filter(f => l.includes(f.sig));

  const matched = tc.expectations.filter(e => l.includes(e.toLowerCase()));
  log(`  expectations met: ${matched.length}/${tc.expectations.length} (${matched.join(', ')})`);
  if (leaks.length) {
    leaks.forEach(le => log(`  ⚠ potential leak: ${le.desc}`));
  }

  const ok = matched.length >= Math.ceil(tc.expectations.length / 2);
  log(`  ${ok ? '✓' : '✗'} Case ${tc.label}: ${ok ? 'PASS' : 'FAIL'}`);
  return { pass: ok, matched: matched.length, total: tc.expectations.length, leaks };
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60_000);
  page.on('pageerror', (e) => log(`  [PAGE ERROR] ${e.message}`));

  let exitCode = 0;
  let results = [];
  try {
    await loginAndOpenChat(page);

    for (let i = 0; i < TEST_CASES.length; i++) {
      const result = await runCase(page, TEST_CASES[i], i);
      results.push({ ...TEST_CASES[i], ...result });
      // Allow chat state to settle between cases
      await page.waitForTimeout(1500);
    }

    log('\n=== SUMMARY ===');
    let passed = 0;
    for (const r of results) {
      log(`  ${r.pass ? '✓' : '✗'} ${r.label} — ${r.matched ?? 0}/${r.total ?? '?'} expectations`);
      if (r.pass) passed++;
      if (r.leaks?.length) log(`     ⚠ ${r.leaks.length} potential leak(s)`);
    }
    log(`=== ${passed}/${results.length} cases passed ===`);
    if (passed < results.length) exitCode = 1;
  } catch (e) {
    log(`✗ EXCEPTION: ${e.message}`);
    await shot(page, '99_error');
    exitCode = 1;
  } finally {
    await browser.close();
    process.exit(exitCode);
  }
})();
