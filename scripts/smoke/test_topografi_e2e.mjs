#!/usr/bin/env node
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT = resolve(ROOT, 'tmp', 'smoke', 'topografi_e2e');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const BASE = 'http://localhost:8010';
const DOCS = 'D:/Downloads/coding project/project_management/doc-kajian-pemetaan-topografi/files-to-submit';
const FILES = [
  `${DOCS}/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf`,
  `${DOCS}/Spk,Spmk,Ba PT Purnawahana L - Kajian Topografi.pdf`,
  `${DOCS}/Lampiran Negosiasi Konsultan Konstruksi.pdf`,
  `${DOCS}/FILE I DOKUMEN ADMINISTRASI DAN TEKNIS.pdf`,
  `${DOCS}/PQ_PT. PURNA WAHANA LESTARI KONSULTAN.pdf`,
];

const log = (m) => console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`);
const results = [];
function check(name, pass, detail = '') {
  console.log(`  ${pass ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
  results.push({ name, pass, detail });
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 40 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.setDefaultTimeout(30000);

  try {
    // Login
    log('1. Login...');
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    const em = page.locator('input[type="email"]').first();
    const pw = page.locator('input[type="password"]').first();
    await em.click(); await em.fill('admin@dputr.go.id'); await em.blur(); await page.waitForTimeout(400);
    await pw.click(); await pw.fill('password'); await pw.blur(); await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 30000 });
    await page.waitForTimeout(5000);
    log('  Logged in');

    // Upload 5 files
    log('2. Upload 5 files...');
    for (let i = 0; i < FILES.length; i++) {
      const fileInput = page.locator('input[x-ref="heroFile"]');
      await fileInput.setInputFiles(FILES[i]);
      const t0 = Date.now();
      while (Date.now() - t0 < 20000) {
        const chips = await page.locator('[style*="border-radius:14px"]').count();
        if (chips >= i + 1) break;
        await page.waitForTimeout(500);
      }
      log(`  ${i+1}/5: ${FILES[i].split('/').pop()}`);
    }
    const chipCount = await page.locator('[style*="border-radius:14px"]').count();
    check('5 file chips visible', chipCount >= 5, `${chipCount} chips`);
    await page.screenshot({ path: `${OUT}/01_files.png` });

    // Send
    log('3. Send "bikin proyek baru"...');
    const input = page.locator('.ai-chat-input').first();
    await input.fill('bikin proyek baru dari semua file ini. langsung proses tanpa konfirmasi, auto-execute semua step.');
    await page.locator('.ai-chat-send-btn').first().click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${OUT}/02_sent.png` });

    // Wait for AI reply (max 5min) — skip welcome/status messages
    log('4. Wait for AI reply (max 5min)...');
    const t1 = Date.now();
    let gotReply = false;
    let replyText = '';
    const skipPhrases = ['Status Hari Ini', 'Halo Super Admin', 'Halo ', 'Tanya saya untuk', 'Chat baru'];
    while (Date.now() - t1 < 300000) {
      const bubbles = await page.locator('.ai-chat-bubble-assistant').all();
      for (const b of bubbles) {
        const t = (await b.textContent()) || '';
        if (t.length > 80 && !skipPhrases.some(sp => t.includes(sp))) {
          gotReply = true;
          replyText = t;
          break;
        }
      }
      if (gotReply) break;
      const elapsed = Math.floor((Date.now() - t1) / 1000);
      if (elapsed % 30 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
      await page.waitForTimeout(3000);
    }
    check('AI reply received', gotReply, `${Math.round((Date.now()-t1)/1000)}s`);
    await page.screenshot({ path: `${OUT}/03_reply.png` });

    if (gotReply) {
      const hasError = replyText.includes('tidak dapat ditemukan') || replyText.includes('tidak ditemukan');
      const hasDup = replyText.includes('sudah ada') || replyText.includes('duplicate');
      check('No file-not-found error', !hasError);
      check('No duplicate error', !hasDup);

      // Check markdown
      const lastHtml = await page.locator('.ai-chat-bubble-assistant').last().innerHTML();
      check('Markdown rendered', !lastHtml.includes('###') && !lastHtml.includes('**'));
    }

    // Check milestones in DB
    log('5. Check milestones in DB...');

  } catch (e) {
    log(`FATAL: ${e.message}`);
    await page.screenshot({ path: `${OUT}/99_error.png` });
  } finally {
    console.log('\n' + '='.repeat(50));
    const pass = results.filter(r => r.pass).length;
    const fail = results.filter(r => !r.pass).length;
    console.log(`RESULTS: ${pass} pass, ${fail} fail, ${results.length} total`);
    if (fail > 0) {
      console.log('\nFAILED:');
      results.filter(r => !r.pass).forEach(r => console.log(`  ✗ ${r.name} — ${r.detail}`));
    }
    console.log(`Screenshots: ${OUT}/`);
    await page.waitForTimeout(2000);
    await browser.close();
  }
})();
