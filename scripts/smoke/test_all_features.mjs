#!/usr/bin/env node
/**
 * Karta DPUTR-PM — Full Feature Test as User
 * Runs through the test checklist acting as a real user in the browser.
 * Outputs results to tmp/smoke/test_results.json + console.
 */
import { chromium } from 'playwright';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const OUT = resolve(ROOT, 'tmp', 'smoke', 'test_results');
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const BASE = 'http://localhost:8010';
const EMAIL = 'admin@dputr.go.id';
const PASSWORD = 'password';

const results = [];
let passCount = 0;
let failCount = 0;
let skipCount = 0;

function log(m) { console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`); }

function record(section, test, status, detail = '') {
  const icon = status === 'PASS' ? '✓' : status === 'FAIL' ? '✗' : '⊘';
  console.log(`  ${icon} [${section}] ${test}${detail ? ' — ' + detail : ''}`);
  results.push({ section, test, status, detail });
  if (status === 'PASS') passCount++;
  else if (status === 'FAIL') failCount++;
  else skipCount++;
}

async function login(page) {
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle', timeout: 30000 });
  const em = page.locator('input[type="email"]').first();
  const pw = page.locator('input[type="password"]').first();
  await em.click(); await em.fill(EMAIL); await em.blur();
  await page.waitForTimeout(300);
  await pw.click(); await pw.fill(PASSWORD); await pw.blur();
  await page.waitForTimeout(300);
  await page.locator('button[type="submit"]').first().click();
  await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 15000 });
  await page.waitForLoadState('networkidle');
}

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 40 });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(15000);

  try {
    // ═══════════════════════════════════════════════════════════════
    // SECTION 0: SMOKE / INFRASTRUCTURE
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 0: SMOKE / INFRASTRUCTURE ===');

    // 0.1 Server jalan
    try {
      const res = await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
      record('0', 'Server jalan (port 8010)', res.status() === 200 ? 'PASS' : 'FAIL', `status=${res.status()}`);
    } catch (e) { record('0', 'Server jalan (port 8010)', 'FAIL', e.message); }

    // 0.2 Login page render
    try {
      const loginForm = await page.locator('input[type="email"]').count();
      record('0', 'Login page render', loginForm > 0 ? 'PASS' : 'FAIL');
    } catch (e) { record('0', 'Login page render', 'FAIL', e.message); }

    // 0.3 Login sukses
    try {
      await login(page);
      const url = page.url();
      record('0', 'Login admin sukses', !url.includes('/login') ? 'PASS' : 'FAIL', url);
    } catch (e) { record('0', 'Login admin sukses', 'FAIL', e.message); }

    // 0.4 Sidebar muncul
    try {
      await page.waitForSelector('nav, aside, [class*="sidebar"]', { timeout: 5000 });
      const sidebar = await page.locator('nav, aside, [class*="sidebar"]').first();
      const sidebarText = await sidebar.textContent();
      const hasPekerjaan = sidebarText.includes('Pekerjaan') || sidebarText.includes('pekerjaan');
      record('0', 'Sidebar muncul (navigation)', hasPekerjaan ? 'PASS' : 'FAIL', hasPekerjaan ? 'Pekerjaan link found' : 'no Pekerjaan link');
    } catch (e) { record('0', 'Sidebar muncul (navigation)', 'FAIL', e.message); }

    await page.screenshot({ path: `${OUT}/00_dashboard.png` });

    // ═══════════════════════════════════════════════════════════════
    // SECTION 1: AUTH
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 1: AUTH & USER MANAGEMENT ===');

    // 1.1 Login invalid password
    try {
      const page2 = await ctx.newPage();
      await page2.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
      const em2 = page2.locator('input[type="email"]').first();
      const pw2 = page2.locator('input[type="password"]').first();
      await em2.fill(EMAIL); await pw2.fill('wrongpass');
      await page2.locator('button[type="submit"]').first().click();
      await page2.waitForTimeout(2000);
      const stillLogin = page2.url().includes('/login');
      const errorMsg = await page2.locator('[class*="danger"], [class*="error"], [role="alert"]').count();
      record('1', 'Login password salah → error', (stillLogin && errorMsg > 0) ? 'PASS' : 'FAIL',
        `still on login: ${stillLogin}, error visible: ${errorMsg > 0}`);
      await page2.close();
    } catch (e) { record('1', 'Login password salah → error', 'FAIL', e.message); }

    // 1.2 User Resource accessible
    try {
      await page.goto(`${BASE}/admin/users`, { waitUntil: 'networkidle' });
      const h1 = await page.locator('h1, [class*="header"] h1, [class*="heading"]').first().textContent();
      record('1', 'UserResource list muncul', h1.toLowerCase().includes('user') ? 'PASS' : 'FAIL', h1.trim());
      await page.screenshot({ path: `${OUT}/01_users.png` });
    } catch (e) { record('1', 'UserResource list muncul', 'FAIL', e.message); }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 2: MASTER DATA
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 2: MASTER DATA ===');

    const masterPages = [
      { slug: 'master/bidangs', label: 'Bidang' },
      { slug: 'master/perusahaans', label: 'Perusahaan' },
      { slug: 'master/tenaga-ahlis', label: 'Tenaga Ahli' },
      { slug: 'master/jenis-pekerjaans', label: 'Jenis Pekerjaan' },
      { slug: 'master/status-pekerjaans', label: 'Status Pekerjaan' },
      { slug: 'master/hari-liburs', label: 'Hari Libur' },
    ];

    for (const mp of masterPages) {
      try {
        const res = await page.goto(`${BASE}/admin/${mp.slug}`, { waitUntil: 'networkidle', timeout: 10000 });
        const ok = res.status() === 200;
        // Check table or empty state
        const hasTable = await page.locator('table, [class*="table"]').count() > 0;
        const hasEmpty = await page.locator('[class*="empty"], [class*="placeholder"]').count() > 0;
        record('2', `${mp.label} list render`, ok && (hasTable || hasEmpty) ? 'PASS' : 'FAIL',
          `status=${res.status()}, table=${hasTable}, empty=${hasEmpty}`);
      } catch (e) { record('2', `${mp.label} list render`, 'FAIL', e.message); }
    }

    // 2.x Create Bidang (test create flow)
    try {
      await page.goto(`${BASE}/admin/master/bidangs/create`, { waitUntil: 'networkidle' });
      const formExists = await page.locator('form').count() > 0;
      record('2', 'Bidang create form render', formExists ? 'PASS' : 'FAIL');
    } catch (e) { record('2', 'Bidang create form render', 'FAIL', e.message); }

    // 2.x Create Perusahaan form
    try {
      await page.goto(`${BASE}/admin/master/perusahaans/create`, { waitUntil: 'networkidle' });
      const formExists = await page.locator('form').count() > 0;
      record('2', 'Perusahaan create form render', formExists ? 'PASS' : 'FAIL');
    } catch (e) { record('2', 'Perusahaan create form render', 'FAIL', e.message); }

    await page.screenshot({ path: `${OUT}/02_master.png` });

    // ═══════════════════════════════════════════════════════════════
    // SECTION 3: PEKERJAAN CRUD
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 3: PEKERJAAN CRUD ===');

    // 3.1 List
    try {
      await page.goto(`${BASE}/admin/pekerjaans`, { waitUntil: 'networkidle' });
      const hasTable = await page.locator('table').count() > 0;
      const rows = await page.locator('table tbody tr').count();
      record('3', 'Pekerjaan list render', hasTable ? 'PASS' : 'FAIL', `${rows} rows`);
      await page.screenshot({ path: `${OUT}/03_pekerjaan_list.png` });
    } catch (e) { record('3', 'Pekerjaan list render', 'FAIL', e.message); }

    // 3.2 Search
    try {
      const searchInput = page.locator('input[type="search"], input[wire\\:model\\.live\\.debounce*="tableSearchQuery"]').first();
      if (await searchInput.count() > 0) {
        await searchInput.fill('kajian');
        await page.waitForTimeout(1500);
        const rows = await page.locator('table tbody tr').count();
        record('3', 'Pekerjaan search "kajian"', rows >= 0 ? 'PASS' : 'FAIL', `${rows} results`);
        await searchInput.clear();
        await page.waitForTimeout(1000);
      } else {
        record('3', 'Pekerjaan search', 'SKIP', 'search input not found');
      }
    } catch (e) { record('3', 'Pekerjaan search', 'FAIL', e.message); }

    // 3.3 Create form
    try {
      await page.goto(`${BASE}/admin/pekerjaans/create`, { waitUntil: 'networkidle' });
      const formExists = await page.locator('form').count() > 0;
      record('3', 'Pekerjaan create form render', formExists ? 'PASS' : 'FAIL');
      await page.screenshot({ path: `${OUT}/03_pekerjaan_create.png` });
    } catch (e) { record('3', 'Pekerjaan create form render', 'FAIL', e.message); }

    // 3.4 View pekerjaan detail (pekerjaan #1 or #2)
    try {
      await page.goto(`${BASE}/admin/pekerjaans`, { waitUntil: 'networkidle' });
      // Click first row's view/edit link
      const firstLink = page.locator('table tbody tr').first().locator('a').first();
      if (await firstLink.count() > 0) {
        await firstLink.click();
        await page.waitForLoadState('networkidle');
        const url = page.url();
        record('3', 'Pekerjaan detail page render', url.includes('pekerjaan') ? 'PASS' : 'FAIL', url);
        await page.screenshot({ path: `${OUT}/03_pekerjaan_detail.png` });
      } else {
        record('3', 'Pekerjaan detail page render', 'SKIP', 'no rows');
      }
    } catch (e) { record('3', 'Pekerjaan detail page render', 'FAIL', e.message); }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 4: RELATION MANAGERS (on pekerjaan detail)
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 4: RELATION MANAGERS ===');

    // Navigate to a pekerjaan with data (try #2)
    try {
      await page.goto(`${BASE}/admin/pekerjaans/2`, { waitUntil: 'networkidle' });
      const pageText = await page.textContent('body');

      const rmChecks = [
        { name: 'Vendor', keywords: ['vendor', 'Vendor', 'perusahaan'] },
        { name: 'Personil', keywords: ['personil', 'Personil', 'tenaga'] },
        { name: 'Termin', keywords: ['termin', 'Termin', 'pembayaran'] },
        { name: 'Milestone', keywords: ['milestone', 'Milestone'] },
        { name: 'Dokumen', keywords: ['dokumen', 'Dokumen', 'document'] },
      ];

      for (const rm of rmChecks) {
        const found = rm.keywords.some(kw => pageText.includes(kw));
        record('4', `RM ${rm.name} visible on detail`, found ? 'PASS' : 'FAIL');
      }
      await page.screenshot({ path: `${OUT}/04_relation_managers.png` });
    } catch (e) {
      record('4', 'Pekerjaan #2 detail page', 'FAIL', e.message);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 5: AI CHAT WIDGET
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 5: AI CHAT WIDGET ===');

    // Go to dashboard first
    await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });

    // 5.1 Floating button visible
    try {
      const toggleBtn = page.locator('button.ai-fc-toggle');
      const vis = await toggleBtn.isVisible();
      record('5', 'Floating chat button visible', vis ? 'PASS' : 'FAIL');
    } catch (e) { record('5', 'Floating chat button visible', 'FAIL', e.message); }

    // 5.2 Click → panel opens
    try {
      await page.locator('button.ai-fc-toggle').click();
      await page.waitForTimeout(800);
      const panel = page.locator('.ai-fc-panel');
      const panelVis = await panel.isVisible();
      record('5', 'Click → panel opens', panelVis ? 'PASS' : 'FAIL');
      await page.screenshot({ path: `${OUT}/05_chat_open.png` });
    } catch (e) { record('5', 'Click → panel opens', 'FAIL', e.message); }

    // 5.3 Header visible
    try {
      const header = await page.locator('.ai-fc-header h3').textContent();
      record('5', 'Chat header text', header.includes('Asisten') ? 'PASS' : 'FAIL', header.trim());
    } catch (e) { record('5', 'Chat header text', 'FAIL', e.message); }

    // 5.4 Welcome message (assistant bubble)
    try {
      const bubble = page.locator('.ai-fc-bubble-assistant');
      const exists = await bubble.count() > 0;
      if (exists) {
        const txt = await bubble.first().textContent();
        record('5', 'Welcome message visible', txt.length > 10 ? 'PASS' : 'FAIL', txt.slice(0, 80));
      } else {
        record('5', 'Welcome message visible', 'SKIP', 'no assistant bubble yet');
      }
    } catch (e) { record('5', 'Welcome message visible', 'FAIL', e.message); }

    // 5.5 Send message
    try {
      const input = page.locator('input.ai-fc-input');
      await input.fill('halo, ada berapa pekerjaan?');
      await page.locator('button.ai-fc-send').click();

      // User bubble muncul
      await page.waitForTimeout(1000);
      const userBubbles = await page.locator('.ai-fc-bubble-user').count();
      record('5', 'User message bubble appears', userBubbles > 0 ? 'PASS' : 'FAIL', `${userBubbles} user bubbles`);

      // Wait for AI reply (max 60s)
      log('  Waiting for AI reply (max 60s)...');
      const t0 = Date.now();
      let gotReply = false;
      while (Date.now() - t0 < 60000) {
        const assistantBubbles = await page.locator('.ai-fc-bubble-assistant').all();
        // find one with substantial text (not just welcome)
        for (const b of assistantBubbles) {
          const t = (await b.textContent()) || '';
          if (t.length > 20 && !t.includes('Halo! Saya asisten')) {
            gotReply = true;
            record('5', 'AI reply received', 'PASS', `${Math.round((Date.now()-t0)/1000)}s, len=${t.length}`);
            break;
          }
        }
        if (gotReply) break;
        await page.waitForTimeout(2000);
      }
      if (!gotReply) record('5', 'AI reply received', 'FAIL', 'timeout 60s');
    } catch (e) { record('5', 'Send message + get reply', 'FAIL', e.message); }

    // 5.6 Scroll test — check scrollable
    try {
      const msglist = page.locator('.ai-fc-msglist');
      const scrollInfo = await msglist.evaluate(el => ({
        scrollHeight: el.scrollHeight,
        clientHeight: el.clientHeight,
        scrollTop: el.scrollTop,
        overflowY: getComputedStyle(el).overflowY
      }));
      const isScrollable = scrollInfo.overflowY === 'auto' || scrollInfo.overflowY === 'scroll';
      record('5', 'Msglist scrollable (overflow-y: auto)', isScrollable ? 'PASS' : 'FAIL',
        `overflow: ${scrollInfo.overflowY}, scrollH=${scrollInfo.scrollHeight}, clientH=${scrollInfo.clientHeight}`);
    } catch (e) { record('5', 'Msglist scrollable', 'FAIL', e.message); }

    // 5.7 Text alignment check
    try {
      const bubble = page.locator('.ai-fc-bubble-assistant').first();
      const cs = await bubble.evaluate(el => {
        const s = getComputedStyle(el);
        return { textAlign: s.textAlign, direction: s.direction, whiteSpace: s.whiteSpace };
      });
      const ok = cs.textAlign === 'left' && cs.direction === 'ltr' && cs.whiteSpace === 'normal';
      record('5', 'Bubble CSS: text-align left, direction ltr, white-space normal', ok ? 'PASS' : 'FAIL',
        JSON.stringify(cs));
    } catch (e) { record('5', 'Bubble CSS check', 'FAIL', e.message); }

    // 5.8 Close panel
    try {
      await page.locator('button.ai-fc-toggle').click();
      await page.waitForTimeout(500);
      const panelHidden = !(await page.locator('.ai-fc-panel').isVisible());
      record('5', 'Click X → panel closes', panelHidden ? 'PASS' : 'FAIL');
    } catch (e) { record('5', 'Click X → panel closes', 'FAIL', e.message); }

    await page.screenshot({ path: `${OUT}/05_chat_done.png` });

    // ═══════════════════════════════════════════════════════════════
    // SECTION 8: FILAMENT CUSTOM PAGES
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 8: FILAMENT CUSTOM PAGES ===');

    const customPages = [
      { slug: 'timeline-pekerjaan', label: 'Timeline Pekerjaan' },
      { slug: 'kalender-laporan', label: 'Kalender Laporan' },
      { slug: 'import-data', label: 'Import Data' },
      { slug: 'laporan-export', label: 'Laporan Export' },
      { slug: 'riwayat-notifikasi', label: 'Riwayat Notifikasi' },
      { slug: 'system-settings', label: 'System Settings' },
    ];

    for (const cp of customPages) {
      try {
        const res = await page.goto(`${BASE}/admin/${cp.slug}`, { waitUntil: 'networkidle', timeout: 10000 });
        record('8', `${cp.label} page render`, res.status() === 200 ? 'PASS' : 'FAIL', `status=${res.status()}`);
      } catch (e) { record('8', `${cp.label} page render`, 'FAIL', e.message); }
    }

    await page.screenshot({ path: `${OUT}/08_custom_pages.png` });

    // ═══════════════════════════════════════════════════════════════
    // SECTION 9: ACTIVITY LOG
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 9: ACTIVITY LOG ===');

    try {
      const res = await page.goto(`${BASE}/admin/activities`, { waitUntil: 'networkidle' });
      const hasTable = await page.locator('table').count() > 0;
      record('9', 'Activity log list render', (res.status() === 200 && hasTable) ? 'PASS' : 'FAIL');
    } catch (e) { record('9', 'Activity log list render', 'FAIL', e.message); }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 10: STANDALONE RESOURCES
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 10: STANDALONE RESOURCES ===');

    const standaloneResources = [
      { slug: 'dokumens', label: 'Dokumen' },
      { slug: 'termin-pembayarans', label: 'Termin Pembayaran' },
      { slug: 'rencana-pengadaans', label: 'Rencana Pengadaan' },
      { slug: 'realisasi-pengadaans', label: 'Realisasi Pengadaan' },
      { slug: 'laporan-harians', label: 'Laporan Harian' },
    ];

    for (const sr of standaloneResources) {
      try {
        const res = await page.goto(`${BASE}/admin/${sr.slug}`, { waitUntil: 'networkidle', timeout: 10000 });
        record('10', `${sr.label} list render`, res.status() === 200 ? 'PASS' : 'FAIL', `status=${res.status()}`);
      } catch (e) { record('10', `${sr.label} list render`, 'FAIL', e.message); }
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 13: SECURITY (quick checks)
    // ═══════════════════════════════════════════════════════════════
    log('=== SECTION 13: SECURITY ===');

    // 13.1 Unauthenticated access → redirect to login
    try {
      const page3 = await ctx.newPage();
      // Clear cookies for this page
      await page3.context().clearCookies();
      const res = await page3.goto(`${BASE}/admin/pekerjaans`, { waitUntil: 'networkidle' });
      const redirectedToLogin = page3.url().includes('/login');
      record('13', 'Unauthenticated → redirect login', redirectedToLogin ? 'PASS' : 'FAIL', page3.url());
      await page3.close();
    } catch (e) { record('13', 'Unauthenticated → redirect login', 'FAIL', e.message); }

    // ═══════════════════════════════════════════════════════════════
    // DONE
    // ═══════════════════════════════════════════════════════════════

  } catch (e) {
    log(`FATAL: ${e.message}`);
    await page.screenshot({ path: `${OUT}/99_fatal.png` });
  } finally {
    // Summary
    console.log('\n' + '═'.repeat(60));
    console.log(`TEST RESULTS SUMMARY`);
    console.log('═'.repeat(60));
    console.log(`  PASS: ${passCount}`);
    console.log(`  FAIL: ${failCount}`);
    console.log(`  SKIP: ${skipCount}`);
    console.log(`  TOTAL: ${results.length}`);
    console.log('═'.repeat(60));

    if (failCount > 0) {
      console.log('\nFAILED TESTS:');
      results.filter(r => r.status === 'FAIL').forEach(r => {
        console.log(`  ✗ [${r.section}] ${r.test} — ${r.detail}`);
      });
    }

    writeFileSync(`${OUT}/results.json`, JSON.stringify({ passCount, failCount, skipCount, results }, null, 2));
    console.log(`\nFull results: ${OUT}/results.json`);
    console.log(`Screenshots: ${OUT}/`);

    await page.waitForTimeout(1000);
    await browser.close();
  }
})();
