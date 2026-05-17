import { chromium } from 'playwright';
import { pathToFileURL } from 'url';

const HTML = 'C:\\Users\\Lenovo\\Documents\\karta_kontrak.html';
const PDF  = 'C:\\Users\\Lenovo\\Documents\\karta_kontrak.pdf';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.goto(pathToFileURL(HTML).href, { waitUntil: 'networkidle' });
await page.pdf({
  path: PDF,
  format: 'A4',
  printBackground: true,
  margin: { top: '2cm', right: '2cm', bottom: '2cm', left: '2cm' },
  displayHeaderFooter: false,
});
await browser.close();
console.log('PDF generated:', PDF);
