import { chromium } from 'playwright';
import { pathToFileURL } from 'url';

const HTML = 'C:\\Users\\Lenovo\\Documents\\karta_proposal.html';
const PDF  = 'C:\\Users\\Lenovo\\Documents\\karta_proposal.pdf';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.goto(pathToFileURL(HTML).href, { waitUntil: 'networkidle' });
await page.pdf({
  path: PDF,
  format: 'A4',
  printBackground: true,
  margin: { top: '0', right: '0', bottom: '0', left: '0' },
  displayHeaderFooter: false,
});
await browser.close();
console.log('PDF generated:', PDF);
