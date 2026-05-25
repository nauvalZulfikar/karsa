import { chromium } from 'playwright';
const log = (m) => console.log(`[${new Date().toISOString().slice(11,19)}] ${m}`);
(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 60 });
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  try {
    log('login...');
    await page.goto('http://localhost:8010/admin/login', { waitUntil: 'networkidle' });
    const em = page.locator('input[type="email"]').first();
    const pw = page.locator('input[type="password"]').first();
    await em.click(); await em.fill('admin@dputr.go.id'); await em.blur(); await page.waitForTimeout(400);
    await pw.click(); await pw.fill('password'); await pw.blur(); await page.waitForTimeout(400);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL((u) => !u.toString().includes('/login'));
    await page.waitForLoadState('networkidle');
    await page.locator('button.ai-fc-toggle').click();
    await page.waitForTimeout(2000);
    // wait for bubble to appear
    await page.waitForSelector('.ai-fc-bubble-assistant', { timeout: 10000 }).catch(() => {});
    // Inspect computed styles of assistant bubble
    const info = await page.evaluate(() => {
      const bubble = document.querySelector('.ai-fc-bubble-assistant');
      if (!bubble) return { error: 'no bubble found' };
      const cs = getComputedStyle(bubble);
      const parents = [];
      let cur = bubble.parentElement;
      while (cur && parents.length < 6) {
        const pcs = getComputedStyle(cur);
        parents.push({
          tag: cur.tagName,
          cls: cur.className.toString().slice(0, 80),
          text_align: pcs.textAlign,
          direction: pcs.direction,
        });
        cur = cur.parentElement;
      }
      return {
        bubble_text_align: cs.textAlign,
        bubble_direction: cs.direction,
        bubble_unicode_bidi: cs.unicodeBidi,
        bubble_classes: bubble.className.toString(),
        bubble_inner_html: bubble.innerHTML.slice(0, 200),
        parents,
      };
    });
    console.log(JSON.stringify(info, null, 2));
  } finally {
    await page.waitForTimeout(500);
    await browser.close();
  }
})();
