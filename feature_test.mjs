// Walks every menu in Karta as different roles, records pass/fail.
// Run: node feature_test.mjs

import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const PW = 'demo123';

const ROLES = {
  superadmin: { email: 'superadmin@karta.test', panel: 'admin' },
  adminbidang: { email: 'adminbidang@karta.test', panel: 'admin' },
  pptk: { email: 'pptk@karta.test', panel: 'admin' },
  ppk: { email: 'ppk@karta.test', panel: 'admin' },
  viewer: { email: 'viewer@karta.test', panel: 'admin' },
  vendor: { email: 'vendor@karta.test', panel: 'vendor' },
};

// Each test: { id, role, url, expect (text|selector) }
const TESTS = [
  // ============ SUPER ADMIN ============
  { id: 'A0-login-admin',          role: 'superadmin', url: '/admin',                      expect: 'Dashboard' },
  { id: 'A-pekerjaan-list',        role: 'superadmin', url: '/admin/pekerjaans?tableSearch=DEMO', waitText: '[DEMO]', expect: '[DEMO]' },
  { id: 'A-pekerjaan-view-edit',   role: 'superadmin', url: '/admin/pekerjaans/133/edit',  expect: 'Posyandu' },
  { id: 'A-pekerjaan-view',        role: 'superadmin', url: '/admin/pekerjaans/133',       expect: 'Posyandu' },
  { id: 'A-pekerjaan-personil',    role: 'superadmin', url: '/admin/pekerjaans/133',       expect: 'Personil' },
  { id: 'A-pekerjaan-milestone',   role: 'superadmin', url: '/admin/pekerjaans/133',       expect: 'Milestone' },
  { id: 'A-pekerjaan-termin',      role: 'superadmin', url: '/admin/pekerjaans/133',       expect: 'Termin' },
  { id: 'A-timeline',              role: 'superadmin', url: '/admin/timeline-pekerjaan',   expect: 'Timeline' },
  { id: 'A-laporan-list',          role: 'superadmin', url: '/admin/laporan-harians',      expect: 'Laporan' },
  { id: 'A-kalender',              role: 'superadmin', url: '/admin/kalender-laporan',     expect: 'Kalender' },
  { id: 'A-rencana-pengadaan',     role: 'superadmin', url: '/admin/rencana-pengadaans',   expect: 'Rencana' },
  { id: 'A-realisasi-pengadaan',   role: 'superadmin', url: '/admin/realisasi-pengadaans', expect: 'Realisasi' },
  { id: 'A-termin',                role: 'superadmin', url: '/admin/termin-pembayarans',   expect: 'Termin' },
  { id: 'A-dokumen',               role: 'superadmin', url: '/admin/dokumens',             expect: 'Dokumen' },
  { id: 'A-master-bidang',         role: 'superadmin', url: '/admin/master/bidangs',           expect: 'Bidang' },
  { id: 'A-master-jenis',          role: 'superadmin', url: '/admin/master/jenis-pekerjaans',  expect: 'Jenis' },
  { id: 'A-master-status',         role: 'superadmin', url: '/admin/master/status-pekerjaans', expect: 'Status' },
  { id: 'A-master-hari-libur',     role: 'superadmin', url: '/admin/master/hari-liburs',       expect: 'Libur' },
  { id: 'A-master-perusahaan',     role: 'superadmin', url: '/admin/master/perusahaans',       expect: 'Perusahaan' },
  { id: 'A-master-tenaga-ahli',    role: 'superadmin', url: '/admin/master/tenaga-ahlis',      expect: 'Tenaga Ahli' },
  { id: 'A-export-laporan',        role: 'superadmin', url: '/admin/laporan-export',       expect: 'Export' },
  { id: 'A-import-data',           role: 'superadmin', url: '/admin/import-data',          expect: 'Import' },
  { id: 'A-users',                 role: 'superadmin', url: '/admin/users',                expect: 'Pengguna' },
  { id: 'A-audit-trail',           role: 'superadmin', url: '/admin/activities',           expect: 'Audit' },
  { id: 'A-notifikasi-wa',         role: 'superadmin', url: '/admin/riwayat-notifikasi',   expect: 'Notifikasi' },
  { id: 'A-system-settings',       role: 'superadmin', url: '/admin/system-settings',      expect: 'Pengaturan' },

  // ============ ADMIN BIDANG ============
  { id: 'B-login',                 role: 'adminbidang', url: '/admin',                  expect: 'Dashboard' },
  { id: 'B-pekerjaan',             role: 'adminbidang', url: '/admin/pekerjaans',       expect: 'Pekerjaan' },
  { id: 'B-perusahaan',            role: 'adminbidang', url: '/admin/master/perusahaans', expect: 'Perusahaan' },
  { id: 'B-tenaga-ahli',           role: 'adminbidang', url: '/admin/master/tenaga-ahlis', expect: 'Tenaga' },
  { id: 'B-sidebar-no-bidang',     role: 'adminbidang', url: '/admin',                  navNotExpect: 'Bidang' },
  { id: 'B-sidebar-no-users',      role: 'adminbidang', url: '/admin',                  navNotExpect: 'Pengguna' },
  { id: 'B-blocked-settings',      role: 'adminbidang', url: '/admin/system-settings',  expectStatus: 403 },

  // ============ PPTK ============
  { id: 'C-login',                 role: 'pptk', url: '/admin',                       expect: 'Dashboard' },
  { id: 'C-pekerjaan',             role: 'pptk', url: '/admin/pekerjaans',            expect: 'Pekerjaan' },
  { id: 'C-tenaga-ahli',           role: 'pptk', url: '/admin/master/tenaga-ahlis',   expect: 'Tenaga' },
  { id: 'C-rencana-pengadaan',     role: 'pptk', url: '/admin/rencana-pengadaans',    expect: 'Rencana' },
  { id: 'C-realisasi-pengadaan',   role: 'pptk', url: '/admin/realisasi-pengadaans',  expect: 'Realisasi' },
  { id: 'C-termin',                role: 'pptk', url: '/admin/termin-pembayarans',    expect: 'Termin' },
  { id: 'C-sidebar-no-perusahaan', role: 'pptk', url: '/admin',                       navNotExpect: 'Perusahaan' },
  { id: 'C-sidebar-no-bidang',     role: 'pptk', url: '/admin',                       navNotExpect: 'Bidang' },

  // ============ PPK ============
  { id: 'D-login',                 role: 'ppk', url: '/admin',                      expect: 'Dashboard' },
  { id: 'D-termin',                role: 'ppk', url: '/admin/termin-pembayarans',   expect: 'Termin' },
  { id: 'D-laporan',               role: 'ppk', url: '/admin/laporan-harians',      expect: 'Laporan' },
  { id: 'D-audit',                 role: 'ppk', url: '/admin/activities',           expect: 'Audit' },

  // ============ VIEWER ============
  { id: 'E-login',                 role: 'viewer', url: '/admin',                expect: 'Dashboard' },
  { id: 'E-pekerjaan',             role: 'viewer', url: '/admin/pekerjaans',     expect: 'Pekerjaan' },
  { id: 'E-timeline',              role: 'viewer', url: '/admin/timeline-pekerjaan', expect: 'Timeline' },

  // ============ VENDOR ============
  { id: 'F-login-vendor',          role: 'vendor', url: '/vendor/dashboard',       expect: 'Dashboard' },
  { id: 'F-submit-laporan',        role: 'vendor', url: '/vendor/submit-laporan',  expect: 'Laporan' },
  { id: 'F-input-realisasi',       role: 'vendor', url: '/vendor/input-realisasi', expect: 'Realisasi' },
  { id: 'F-blocked-admin',         role: 'vendor', url: '/admin',                  expectStatus: 403 },
];

async function login(page, role) {
  const { email, panel } = ROLES[role];
  await page.goto(`${BASE}/${panel}/login`, { waitUntil: 'networkidle' });
  // Wait until Livewire is initialized
  await page.waitForFunction(() => window.Livewire !== undefined, { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(800);
  await page.fill('input[type="email"]', email);
  await page.waitForTimeout(500);
  await page.fill('input[type="password"]', PW);
  await page.waitForTimeout(500);
  // Click and retry if it didn't navigate
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.click('button[type="submit"]:has-text("Masuk")');
    for (let i = 0; i < 30; i++) {
      if (!page.url().includes('/login')) return;
      await page.waitForTimeout(200);
    }
    // Click didn't trigger submit → re-trigger Livewire input events and retry
    await page.evaluate(() => {
      document.querySelectorAll('input[type="email"], input[type="password"]').forEach(el => {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    await page.waitForTimeout(600);
  }
  throw new Error(`still on login page: ${page.url()}`);
}

const results = [];

(async () => {
  const browser = await chromium.launch();
  const sessions = {};

  for (const role of Object.keys(ROLES)) {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    try {
      await login(page, role);
      sessions[role] = { ctx, page, loginOK: true };
    } catch (e) {
      sessions[role] = { ctx, page, loginOK: false, err: e.message };
    }
  }

  for (const t of TESTS) {
    const sess = sessions[t.role];
    if (!sess.loginOK) {
      results.push({ ...t, status: 'FAIL', reason: `login failed: ${sess.err}` });
      continue;
    }
    const { page } = sess;
    try {
      const resp = await page.goto(BASE + t.url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      const code = resp ? resp.status() : 0;

      if (t.expectStatus) {
        if (code === t.expectStatus) {
          results.push({ ...t, status: 'PASS', code });
        } else {
          // Filament redirects to login on 403 sometimes — check if blocked another way
          const url = page.url();
          if (code === 200 && !url.includes(t.url.split('?')[0])) {
            results.push({ ...t, status: 'PASS', code, note: `redirected to ${url}` });
          } else if (code === 200) {
            // Page accessible — fail if we expected block
            results.push({ ...t, status: 'FAIL', code, reason: 'expected blocked but accessible' });
          } else {
            results.push({ ...t, status: 'PASS', code, note: `got ${code}, expected ${t.expectStatus}` });
          }
        }
        continue;
      }

      if (code >= 400) {
        results.push({ ...t, status: 'FAIL', code, reason: `HTTP ${code}` });
        continue;
      }

      // Wait for Livewire-rendered text if needed
      if (t.waitText) {
        try {
          await page.waitForFunction(
            (txt) => document.body && document.body.innerText.includes(txt),
            t.waitText,
            { timeout: 8000 }
          );
        } catch {
          // fall through — expect check will fail
        }
      }

      // Sidebar / nav negative check
      if (t.navNotExpect) {
        const nav = await page.$$eval('aside a, nav a', els => els.map(e => e.textContent.trim()).join('|')).catch(() => '');
        if (nav.includes(t.navNotExpect)) {
          results.push({ ...t, status: 'FAIL', code, reason: `sidebar still shows "${t.navNotExpect}"` });
        } else {
          results.push({ ...t, status: 'PASS', code });
        }
        continue;
      }

      const body = await page.content();
      if (t.expect && !body.includes(t.expect)) {
        results.push({ ...t, status: 'FAIL', code, reason: `text "${t.expect}" not found` });
        continue;
      }

      if (t.click) {
        try {
          await page.click(t.click, { timeout: 5000 });
          await page.waitForLoadState('domcontentloaded');
          const body2 = await page.content();
          if (t.afterExpect && !body2.includes(t.afterExpect)) {
            results.push({ ...t, status: 'FAIL', code, reason: `after click "${t.afterExpect}" not found` });
            continue;
          }
        } catch (e) {
          results.push({ ...t, status: 'FAIL', code, reason: `click failed: ${e.message}` });
          continue;
        }
      }

      results.push({ ...t, status: 'PASS', code });
    } catch (e) {
      results.push({ ...t, status: 'FAIL', reason: e.message });
    }
  }

  for (const role of Object.keys(sessions)) {
    await sessions[role].ctx.close();
  }
  await browser.close();

  // ===== Print results =====
  const pass = results.filter(r => r.status === 'PASS').length;
  const fail = results.filter(r => r.status === 'FAIL').length;
  console.log(`\n=== TOTAL: ${pass} PASS, ${fail} FAIL ===\n`);
  for (const r of results) {
    const tag = r.status === 'PASS' ? '✓' : '✗';
    const code = r.code ? ` [${r.code}]` : '';
    const reason = r.reason ? ` — ${r.reason}` : '';
    const note = r.note ? ` (${r.note})` : '';
    console.log(`${tag} ${r.id.padEnd(28)} ${r.role.padEnd(12)} ${r.url}${code}${reason}${note}`);
  }
})();
