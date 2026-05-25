#!/usr/bin/env node
/**
 * Test hero chat widget:
 * 1. Login
 * 2. Check welcome message + project status renders
 * 3. Upload 3 files via drag-drop (batch)
 * 4. Verify all 3 chips appear
 * 5. Send message "bikin proyek baru"
 * 6. Verify file labels in user bubble (not paths)
 * 7. Wait for AI reply — check markdown renders (no raw ### or **)
 * 8. Check chat window stays fixed height (scroll, not grow)
 * 9. Test edit button visibility on hover
 * 10. Screenshot everything
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT = resolve(ROOT, 'tmp', 'smoke', 'hero_test');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const BASE = 'http://localhost:8010';
const log = (m) => console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`);

const results = [];
function check(name, pass, detail = '') {
  const icon = pass ? '✓' : '✗';
  console.log(`  ${icon} ${name}${detail ? ' — ' + detail : ''}`);
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
    log('  Logged in');

    // Wait for hero widget
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${OUT}/01_dashboard.png` });

    // 2. Check welcome message
    log('2. Check welcome + status...');
    const heroRoot = page.locator('.ai-chat-hero-root');
    const heroExists = await heroRoot.count() > 0;
    check('Hero widget exists', heroExists);

    if (heroExists) {
      const heroText = await heroRoot.textContent();
      check('Welcome header visible', heroText.includes('Apa yang bisa saya bantu'));
      check('Has suggestion buttons', heroText.includes('Berapa proyek kritis'));

      // 3. Check fixed height (not growing)
      log('3. Check fixed height...');
      const heroBox = await heroRoot.boundingBox();
      check('Hero has bounded height', heroBox.height < 900, `height=${Math.round(heroBox.height)}px`);

      // 4. Check msglist scrollable
      const msglist = page.locator('.ai-chat-msglist');
      const scrollInfo = await msglist.evaluate(el => ({
        overflowY: getComputedStyle(el).overflowY,
        scrollHeight: el.scrollHeight,
        clientHeight: el.clientHeight,
      }));
      check('Msglist overflow-y is auto', scrollInfo.overflowY === 'auto', scrollInfo.overflowY);

      // 5. Check stop button CSS
      log('4. Check stop button...');
      // Send a quick message to trigger AI
      const input = page.locator('.ai-chat-input').first();
      await input.fill('halo');
      await page.locator('.ai-chat-send-btn').first().click();
      await page.waitForTimeout(500);

      // Check stop button appears during loading
      const stopBtn = page.locator('.ai-chat-stop-btn');
      // It might be briefly visible during fetchAi
      await page.screenshot({ path: `${OUT}/02_sending.png` });

      // Wait for reply
      log('5. Wait for AI reply...');
      const t0 = Date.now();
      let gotReply = false;
      while (Date.now() - t0 < 60000) {
        const bubbles = await page.locator('.ai-chat-bubble-assistant').all();
        for (const b of bubbles) {
          const t = (await b.textContent()) || '';
          if (t.length > 30 && !t.includes('Apa yang bisa saya bantu')) {
            gotReply = true;
            break;
          }
        }
        if (gotReply) break;
        await page.waitForTimeout(2000);
      }
      check('AI reply received', gotReply, `${Math.round((Date.now()-t0)/1000)}s`);
      await page.screenshot({ path: `${OUT}/03_reply.png` });

      // 6. Check hero height didn't grow after reply
      const heroBox2 = await heroRoot.boundingBox();
      check('Hero height unchanged after reply', Math.abs(heroBox2.height - heroBox.height) < 5,
        `before=${Math.round(heroBox.height)} after=${Math.round(heroBox2.height)}`);

      // 7. Check markdown rendering — no raw ### or **
      const allBubbleHtml = await page.locator('.ai-chat-bubble-assistant').last().innerHTML();
      const hasRawHash = allBubbleHtml.includes('###');
      const hasRawStars = allBubbleHtml.includes('**');
      check('No raw ### in rendered HTML', !hasRawHash, hasRawHash ? 'FOUND raw ###' : 'clean');
      check('No raw ** in rendered HTML', !hasRawStars, hasRawStars ? 'FOUND raw **' : 'clean');
      // Check it uses x-html (has HTML tags)
      const hasHtmlTags = allBubbleHtml.includes('<strong>') || allBubbleHtml.includes('<br>') || allBubbleHtml.includes('&bull;');
      check('Bubble has rendered HTML tags', hasHtmlTags);

      // 8. Test edit button on user bubble
      log('6. Check edit button...');
      const userBubble = page.locator('.ai-chat-bubble-user').first();
      await userBubble.hover();
      await page.waitForTimeout(500);
      await page.screenshot({ path: `${OUT}/04_edit_hover.png` });
      const editBtn = page.locator('.ai-chat-edit-btn').first();
      const editVisible = await editBtn.evaluate(el => {
        return getComputedStyle(el).opacity !== '0';
      }).catch(() => false);
      check('Edit button visible on hover', editVisible);

      // 9. Now test file upload
      log('7. Test file upload...');
      const files = [
        'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/files-for-upload/1. KAK Kajian Geoteknik Stabilitas Tanah.pdf',
        'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/files-for-upload/Lampiran Negosiasi Konsultan Konstruksi.pdf',
        'D:/Downloads/coding project/project_management/doc-geoteknik-stabilitas-tanah/files-for-upload/Spk,Spmk,Ba PT Itergo - Kajian Geoteknik Stabilitas Tanah .pdf',
      ];

      // Upload via file input (not drag-drop, more reliable in test)
      for (let fi = 0; fi < files.length; fi++) {
        const fileInput = page.locator('input[x-ref="heroFile"]');
        await fileInput.setInputFiles(files[fi]);
        // Wait for Livewire to process upload + PDF text extraction
        const expectedChips = fi + 1;
        const chipWaitStart = Date.now();
        while (Date.now() - chipWaitStart < 15000) {
          const chips = await page.locator('[style*="border-radius:14px"], .ai-fc-chip').count();
          if (chips >= expectedChips) break;
          await page.waitForTimeout(500);
        }
        log(`  File ${fi+1}/${files.length} uploaded`);
      }

      await page.screenshot({ path: `${OUT}/05_files_uploaded.png` });

      // Count chips
      const chipCount = await page.locator('.ai-fc-chip, [style*="border-radius:14px"]').count();
      check('File chips visible', chipCount >= 3, `${chipCount} chips`);

      // 10. Send with files
      log('8. Send with files...');
      const input2 = page.locator('.ai-chat-input').first();
      await input2.fill('bikin proyek baru dari file ini');
      await page.locator('.ai-chat-send-btn').first().click();
      await page.waitForTimeout(2000);

      // Check user bubble has file labels not paths
      const lastUserBubble = page.locator('.ai-chat-bubble-user').last();
      const userHtml = await lastUserBubble.innerHTML();
      const hasPath = userHtml.includes('storage') || userHtml.includes('D:\\') || userHtml.includes('D:/');
      const hasChip = userHtml.includes('📎');
      check('User bubble shows file labels (📎)', hasChip);
      check('User bubble does NOT show file paths', !hasPath, hasPath ? 'LEAKED path!' : 'clean');

      await page.screenshot({ path: `${OUT}/06_sent_with_files.png` });

      // Wait for AI reply (longer — parsing 3 PDFs)
      log('9. Wait for AI reply with files (max 3min)...');
      const t1 = Date.now();
      let gotReply2 = false;
      while (Date.now() - t1 < 180000) {
        const bubbles = await page.locator('.ai-chat-bubble-assistant').all();
        // Find a new reply (after the first one)
        if (bubbles.length >= 3) {
          const lastText = (await bubbles[bubbles.length - 1].textContent()) || '';
          if (lastText.length > 50) {
            gotReply2 = true;
            break;
          }
        }
        const elapsed = Math.floor((Date.now() - t1) / 1000);
        if (elapsed % 30 === 0 && elapsed > 0) log(`  ...waiting ${elapsed}s`);
        await page.waitForTimeout(3000);
      }
      check('AI reply with file parsing received', gotReply2, `${Math.round((Date.now()-t1)/1000)}s`);
      await page.screenshot({ path: `${OUT}/07_file_reply.png` });

      // Check no "tidak dapat ditemukan" error
      if (gotReply2) {
        const lastReply = await page.locator('.ai-chat-bubble-assistant').last().textContent();
        const hasError = lastReply.includes('tidak dapat ditemukan') || lastReply.includes('tidak ditemukan');
        check('No "file not found" error in reply', !hasError, hasError ? 'ERROR: file not found!' : 'clean');

        // Check markdown rendered
        const lastHtml = await page.locator('.ai-chat-bubble-assistant').last().innerHTML();
        const hasRaw2 = lastHtml.includes('###') || lastHtml.includes('**');
        check('File reply markdown rendered', !hasRaw2, hasRaw2 ? 'raw markdown found' : 'clean');
      }

      // Final height check
      const heroBox3 = await heroRoot.boundingBox();
      check('Hero height still bounded after all', heroBox3.height < 900,
        `height=${Math.round(heroBox3.height)}px`);

      await page.screenshot({ path: `${OUT}/08_final.png` });
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
    console.log(`\nScreenshots: ${OUT}/`);
    await page.waitForTimeout(1000);
    await browser.close();
  }
})();
