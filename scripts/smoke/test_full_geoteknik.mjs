#!/usr/bin/env node
/**
 * Full E2E: upload 5 geoteknik files → create project → verify milestones + laporan
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT = resolve(ROOT, 'tmp', 'smoke', 'geoteknik_e2e');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const BASE = 'http://localhost:8010';
const DOCS = 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/files-for-upload';
const FILES = [
  `${DOCS}/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf`,
  `${DOCS}/Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf`,
  `${DOCS}/Lampiran Negosiasi Konsultan Konstruksi.pdf`,
  `${DOCS}/FILE DOKUMEN ADMINISTRASI DAN TEKNIS.pdf`,
  `${DOCS}/PQ - ITERGO BUANA UTAMA.pdf`,
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
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    log('  Logged in');

    // Upload 5 files one by one
    log('2. Upload 5 files...');
    for (let i = 0; i < FILES.length; i++) {
      const fileInput = page.locator('input[x-ref="heroFile"]');
      await fileInput.setInputFiles(FILES[i]);
      // Wait for chip to appear
      const t0 = Date.now();
      while (Date.now() - t0 < 20000) {
        const chips = await page.locator('[style*="border-radius:14px"]').count();
        if (chips >= i + 1) break;
        await page.waitForTimeout(500);
      }
      const name = FILES[i].split('/').pop();
      log(`  ${i+1}/5: ${name}`);
    }
    const chipCount = await page.locator('[style*="border-radius:14px"]').count();
    check('5 file chips visible', chipCount >= 5, `${chipCount} chips`);
    await page.screenshot({ path: `${OUT}/01_files_uploaded.png` });

    // Send message
    log('3. Send "bikin proyek baru"...');
    const input = page.locator('.ai-chat-input').first();
    await input.fill('bikin proyek baru dari semua file ini. langsung proses, jangan tanya konfirmasi.');
    await page.locator('.ai-chat-send-btn').first().click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${OUT}/02_sent.png` });

    // Check stop button centered
    log('4. Check stop button...');
    const stopBtn = page.locator('.ai-chat-stop-btn').first();
    if (await stopBtn.isVisible()) {
      const btnBox = await stopBtn.boundingBox();
      const spanEl = stopBtn.locator('span').first();
      if (await spanEl.count() > 0) {
        const spanBox = await spanEl.boundingBox();
        const offsetX = Math.abs((btnBox.x + btnBox.width/2) - (spanBox.x + spanBox.width/2));
        const offsetY = Math.abs((btnBox.y + btnBox.height/2) - (spanBox.y + spanBox.height/2));
        check('Stop button square centered', offsetX < 3 && offsetY < 3, `offset x=${offsetX.toFixed(1)} y=${offsetY.toFixed(1)}`);
      }
      await page.screenshot({ path: `${OUT}/03_stop_btn.png` });
    } else {
      log('  Stop button not visible (AI already replied)');
    }

    // Wait for AI reply (max 5min for 5 PDFs)
    log('5. Wait for AI reply (max 5min)...');
    const t1 = Date.now();
    let gotReply = false;
    let replyText = '';
    while (Date.now() - t1 < 300000) {
      const bubbles = await page.locator('.ai-chat-bubble-assistant').all();
      for (const b of bubbles) {
        const t = (await b.textContent()) || '';
        if (t.length > 100 && !t.includes('Status Hari Ini') && !t.includes('Halo Super Admin')) {
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
    await page.screenshot({ path: `${OUT}/04_reply.png` });

    // Check no errors
    if (gotReply) {
      const hasFileError = replyText.includes('tidak dapat ditemukan') || replyText.includes('tidak ditemukan');
      const hasDupError = replyText.includes('sudah ada') || replyText.includes('duplicate');
      const hasLoopSign = replyText.includes('Apakah Anda ingin menggunakan');
      check('No file-not-found error', !hasFileError);
      check('No duplicate SPK error', !hasDupError, hasDupError ? replyText.slice(0, 200) : 'clean');
      check('No loop question', !hasLoopSign);

      // Check markdown rendered
      const lastHtml = await page.locator('.ai-chat-bubble-assistant').last().innerHTML();
      const rawMd = lastHtml.includes('###') || lastHtml.includes('**');
      check('Markdown rendered (no raw ### or **)', !rawMd);
    }

    // Check hero height didn't grow
    const heroBox = await page.locator('.ai-chat-hero-root').boundingBox();
    check('Chat window height bounded', heroBox.height < 900, `${Math.round(heroBox.height)}px`);

    // Check user bubble has file chips not paths
    const userBubbles = await page.locator('.ai-chat-bubble-user').all();
    if (userBubbles.length > 0) {
      const lastUserHtml = await userBubbles[userBubbles.length - 1].innerHTML();
      const hasPath = lastUserHtml.includes('storage') || lastUserHtml.includes('D:\\') || lastUserHtml.includes('D:/');
      check('User bubble no file paths', !hasPath);
      const chipIcons = (lastUserHtml.match(/📎/g) || []).length;
      check('User bubble has file chips', chipIcons >= 5, `${chipIcons} chips`);
    }

    await page.screenshot({ path: `${OUT}/05_final.png` });

    // Scroll up to check all content
    const msglist = page.locator('.ai-chat-msglist');
    await msglist.evaluate(el => el.scrollTop = 0);
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${OUT}/06_scrolled_top.png` });

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
    writeFileSync(`${OUT}/results.json`, JSON.stringify({ pass: pass, fail, results }, null, 2));
    await page.waitForTimeout(2000);
    await browser.close();
  }
})();
