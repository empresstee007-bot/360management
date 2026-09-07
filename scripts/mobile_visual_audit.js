const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8095';
const outDir = process.env.AUDIT_OUT_DIR || '/private/tmp/mobile-audit-360';
const sessionId = process.env.AUDIT_SESSION_ID || 'codexvisualaudit';

const widths = [320, 360, 390, 430];
const pages = [
  ['login', '/login.php', false],
  ['warehouse-dashboard', '/beverage_warehouse.php?tab=tab-dash', true],
  ['warehouse-products', '/beverage_warehouse.php?tab=tab-inventory', true],
  ['pos-terminal', '/beverage_pos.php?tab=pos', true],
  ['hr-kyc', '/dashboard.php?tab=tab-hr-payroll&view=kyc&company=beverage', true],
  ['logistics-dispatch', '/dashboard.php?tab=tab-logistics&view=dispatch&company=beverage', true],
];

async function pageMetrics(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const viewport = doc.clientWidth;
    const elements = [...document.querySelectorAll('body *')];
    const offenders = elements
      .map((el) => {
        const rect = el.getBoundingClientRect();
        return {
          tag: el.tagName.toLowerCase(),
          id: el.id || '',
          cls: String(el.className || '').slice(0, 120),
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 90),
        };
      })
      .filter((item) => item.width > 0 && (item.right > viewport + 2 || item.left < -2))
      .sort((a, b) => b.right - a.right)
      .slice(0, 8);

    return {
      title: document.title,
      url: location.href,
      viewport,
      htmlScrollWidth: doc.scrollWidth,
      bodyScrollWidth: body ? body.scrollWidth : 0,
      scrollHeight: doc.scrollHeight,
      overflowX: Math.max(doc.scrollWidth, body ? body.scrollWidth : 0) - viewport,
      offenders,
      h1: [...document.querySelectorAll('h1,h2')].map((el) => el.textContent.trim()).filter(Boolean).slice(0, 4),
    };
  });
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const report = [];

  for (const width of widths) {
    const context = await browser.newContext({
      viewport: { width, height: 844 },
      deviceScaleFactor: 3,
      isMobile: true,
      hasTouch: true,
    });
    await context.addCookies([{
      name: 'PHPSESSID',
      value: sessionId,
      domain: '127.0.0.1',
      path: '/',
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    }]);

    for (const [name, route] of pages) {
      const page = await context.newPage();
      const url = `${base}${route}`;
      let item;
      try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(350);
        item = await pageMetrics(page);
        item.name = name;
        item.width = width;
        if (width === 390) {
          const file = path.join(outDir, `${name}-${width}.png`);
          await page.screenshot({ path: file, fullPage: true });
          item.screenshot = file;
        }
      } catch (error) {
        item = { name, width, url, error: error.message };
      } finally {
        await page.close().catch(() => {});
      }
      report.push(item);
    }
    await context.close();
  }

  await browser.close();
  const reportPath = path.join(outDir, 'mobile-report.json');
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));

  for (const item of report) {
    const status = item.error ? `ERROR ${item.error}` : `overflow=${item.overflowX}px height=${item.scrollHeight}`;
    console.log(`${item.width}px ${item.name}: ${status}`);
    if (item.offenders && item.offenders.length) {
      console.log(`  offenders: ${item.offenders.map((o) => `${o.tag}${o.id ? '#' + o.id : ''}${o.cls ? '.' + o.cls.split(/\s+/).slice(0, 2).join('.') : ''} right=${o.right} w=${o.width}`).join(' | ')}`);
    }
  }
  console.log(`report=${reportPath}`);
})();
