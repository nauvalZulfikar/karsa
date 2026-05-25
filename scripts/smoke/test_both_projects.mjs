#!/usr/bin/env node
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT = resolve(ROOT, 'tmp', 'smoke', 'both_projects');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const BASE = 'http://localhost:8010';
const log = (m) => console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`);
const results = [];
function check(name, pass, detail = '') {
  console.log(`  ${pass ? '✓' : '✗'} ${name}${detail ? ' — ' + detail : ''}`);
  results.push({ name, pass, detail });
}

const PROJECTS = [
  {
    name: 'Topografi (Purna Wahana)',
    folder: 'D:/Downloads/coding project/project_management/doc-kajian-pemetaan-topografi/files-to-submit',
    files: [
      '1. KAK Kajian Geoteknik Stabilitas Tanah.pdf',
      'Spk,Spmk,Ba PT Purnawahana L - Kajian Topografi.pdf',
      'Lampiran Negosiasi Konsultan Konstruksi.pdf',
      'FILE I DOKUMEN ADMINISTRASI DAN TEKNIS.pdf',
      'PQ_PT. PURNA WAHANA LESTARI KONSULTAN.pdf',
    ],
  },
  {
    name: 'Geoteknik (Itergo)',
    folder: 'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/files-for-upload',
    files: [
      '1. KAK Kajian Geoteknik Stabilitas Tanah.pdf',
      'Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf',
      'Lampiran Negosiasi Konsultan Konstruksi.pdf',
      'FILE DOKUMEN ADMINISTRASI DAN TEKNIS.pdf',
      'PQ - ITERGO BUANA UTAMA.pdf',
    ],
  },
];

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 40 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.setDefaultTimeout(30000);

  try {
    // Login
    log('Login...');
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    const em = page.locator('input[type="email"]').first();
    const pw = page.locator('input[type="password"]').first();
    await em.click(); await em.fill('admin@dputr.go.id'); await em.blur(); await page.waitForTimeout(400);
    await pw.click(); await pw.fill('password'); await pw.blur(); await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 30000 });
    await page.waitForTimeout(5000);
    log('Logged in');

    const skipPhrases = ['Status Hari Ini', 'Halo Super Admin', 'Halo ', 'Tanya saya untuk', 'Chat baru'];

    for (let pi = 0; pi < PROJECTS.length; pi++) {
      const proj = PROJECTS[pi];
      log(`\n${'='.repeat(50)}`);
      log(`PROJECT ${pi+1}: ${proj.name}`);
      log('='.repeat(50));

      // New chat if not first
      if (pi > 0) {
        log('Creating new chat...');
        await page.locator('button[title="Chat Baru"]').click();
        await page.waitForLoadState('load', { timeout: 30000 });
        await page.waitForTimeout(3000);
      }

      // Upload files
      log('Uploading files...');
      for (let i = 0; i < proj.files.length; i++) {
        const filePath = `${proj.folder}/${proj.files[i]}`;
        const fileInput = page.locator('input[x-ref="heroFile"]');
        await fileInput.setInputFiles(filePath);
        const t0 = Date.now();
        while (Date.now() - t0 < 20000) {
          const chips = await page.locator('[style*="border-radius:14px"]').count();
          if (chips >= i + 1) break;
          await page.waitForTimeout(500);
        }
        log(`  ${i+1}/${proj.files.length}: ${proj.files[i]}`);
      }
      await page.screenshot({ path: `${OUT}/p${pi+1}_01_files.png` });

      // Send
      log('Sending...');
      const input = page.locator('.ai-chat-input').first();
      await input.fill('bikin proyek baru dari semua file ini. langsung proses tanpa konfirmasi, auto-execute semua step.');
      await page.locator('.ai-chat-send-btn').first().click();
      await page.waitForTimeout(2000);

      // Wait for reply
      log('Waiting for AI reply (max 5min)...');
      const t1 = Date.now();
      let gotReply = false;
      let replyText = '';
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

      check(`[${proj.name}] AI reply received`, gotReply, `${Math.round((Date.now()-t1)/1000)}s`);
      await page.screenshot({ path: `${OUT}/p${pi+1}_02_reply.png` });

      if (gotReply) {
        const hasFileErr = replyText.includes('tidak dapat ditemukan') || replyText.includes('tidak ditemukan');
        const hasDupErr = replyText.includes('sudah ada') || replyText.includes('duplicate');
        const hasUploadAgain = replyText.includes('upload kembali') || replyText.includes('drag-drop');
        check(`[${proj.name}] No file-not-found error`, !hasFileErr);
        check(`[${proj.name}] No duplicate error`, !hasDupErr, hasDupErr ? replyText.slice(0, 150) : 'clean');
        check(`[${proj.name}] No re-upload request`, !hasUploadAgain);
        check(`[${proj.name}] Project created`, replyText.includes('berhasil') || replyText.includes('dibuat') || replyText.includes('ID:'));
      }
    }

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
