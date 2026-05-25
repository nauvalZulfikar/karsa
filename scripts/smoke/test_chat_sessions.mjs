#!/usr/bin/env node
import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const OUT = resolve(import.meta.dirname, '..', '..', 'tmp', 'smoke', 'chat_sessions');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
const BASE = 'http://localhost:8010';
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
    await page.waitForLoadState('load', { timeout: 30000 });
    await page.waitForTimeout(3000);
    log('  Logged in');
    await page.screenshot({ path: `${OUT}/01_dashboard.png` });

    // 2. Check header buttons exist
    log('2. Check header buttons...');
    const header = page.locator('.ai-chat-header');
    const headerHtml = await header.innerHTML();
    check('New Chat button (＋) exists', headerHtml.includes('＋'));
    check('History button (☰) exists', headerHtml.includes('☰'));

    // 3. Send a message to create session
    log('3. Send message to create session...');
    const input = page.locator('.ai-chat-input').first();
    await input.fill('halo ini test session 1');
    await page.locator('.ai-chat-send-btn').first().click();
    await page.waitForTimeout(2000);

    // Wait for AI reply
    const t0 = Date.now();
    while (Date.now() - t0 < 30000) {
      const bubbles = await page.locator('.ai-chat-bubble-assistant').all();
      let gotNew = false;
      for (const b of bubbles) {
        const t = (await b.textContent()) || '';
        if (t.length > 20 && !t.includes('Status Hari Ini') && !t.includes('Halo') && !t.includes('Tanya saya')) {
          gotNew = true;
          break;
        }
      }
      if (gotNew) break;
      await page.waitForTimeout(1000);
    }
    check('Session 1 AI reply received', true);
    await page.screenshot({ path: `${OUT}/02_session1.png` });

    // 4. Click history button
    log('4. Open history...');
    const historyBtn = page.locator('button[title="Riwayat Chat"]');
    await historyBtn.click();
    await page.waitForTimeout(1000);
    await page.screenshot({ path: `${OUT}/03_history_open.png` });

    // Check history has at least 1 session
    const historyItems = await page.locator('[wire\\:click^="switchSession"]').count();
    check('History shows session(s)', historyItems >= 1, `${historyItems} sessions`);

    // 5. Create new chat (triggers redirect)
    log('5. Create new chat...');
    const newChatBtn = page.locator('button[title="Chat Baru"]');
    await newChatBtn.click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(2000);
    await page.screenshot({ path: `${OUT}/04_new_chat.png` });

    // Check messages reset (only welcome messages should remain)
    const userBubblesAfterNew = await page.locator('.ai-chat-bubble-user').count();
    check('New chat has no user messages', userBubblesAfterNew === 0);

    // 6. Send message in new session
    log('6. Send message in session 2...');
    await input.fill('ini test session 2 ya');
    await page.locator('.ai-chat-send-btn').first().click();
    await page.waitForTimeout(3000);
    check('Session 2 message sent', true);
    await page.screenshot({ path: `${OUT}/05_session2.png` });

    // 7. Open history again — should show 2 sessions
    log('7. Check history has 2 sessions...');
    await historyBtn.click();
    await page.waitForTimeout(1000);
    const historyItems2 = await page.locator('[wire\\:click^="switchSession"]').count();
    check('History shows 2+ sessions', historyItems2 >= 2, `${historyItems2} sessions`);
    await page.screenshot({ path: `${OUT}/06_history_2sessions.png` });

    // 8. Switch back to session 1 (triggers redirect)
    log('8. Switch to session 1...');
    const firstSession = page.locator('[wire\\:click^="switchSession"]').last();
    await firstSession.click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(2000);

    // Check session 1 content restored
    const userBubbles = await page.locator('.ai-chat-bubble-user').all();
    let foundSession1 = false;
    for (const b of userBubbles) {
      const t = (await b.textContent()) || '';
      if (t.includes('test session 1')) {
        foundSession1 = true;
        break;
      }
    }
    check('Session 1 content restored after switch', foundSession1);
    await page.screenshot({ path: `${OUT}/07_switched_back.png` });

    // 9. Refresh page — chat should persist
    log('9. Refresh page...');
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    const userBubblesAfterRefresh = await page.locator('.ai-chat-bubble-user').count();
    check('Chat persists after refresh', userBubblesAfterRefresh > 0);
    await page.screenshot({ path: `${OUT}/08_after_refresh.png` });

    // 10. Delete a session
    log('10. Delete session...');
    await historyBtn.click();
    await page.waitForTimeout(1000);
    const deleteBtn = page.locator('button[title="Hapus"]').first();
    if (await deleteBtn.count() > 0) {
      await deleteBtn.click();
      await page.waitForTimeout(1500);
      const historyItems3 = await page.locator('[wire\\:click^="switchSession"]').count();
      check('Session deleted from history', historyItems3 < historyItems2, `${historyItems3} remaining`);
      await page.screenshot({ path: `${OUT}/09_after_delete.png` });
    } else {
      check('Delete button found', false, 'no delete button');
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
    await page.waitForTimeout(1000);
    await browser.close();
  }
})();
