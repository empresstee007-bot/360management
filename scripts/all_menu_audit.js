const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8095';
const outDir = process.env.AUDIT_OUT_DIR || '/private/tmp/all-menu-audit-360';
const sessionId = process.env.AUDIT_SESSION_ID || 'codexvisualaudit';

const pages = [
  ['Dashboard', '/beverage_warehouse.php?tab=tab-dash'],
  ['Products', '/beverage_warehouse.php?tab=tab-inventory'],
  ['Stock Levels', '/beverage_warehouse.php?tab=tab-inventory&detail=available'],
  ['Warehouse Zones', '/beverage_warehouse.php?tab=tab-inventory&detail=zones'],
  ['Truck Loads', '/beverage_warehouse.php?tab=tab-truck-loads'],
  ['Daily Stock', '/beverage_warehouse.php?tab=tab-daily-stock'],
  ['Stock Movement', '/beverage_pos.php?tab=transfers'],
  ['Inventory Audit', '/beverage_pos.php?tab=pricing-audit'],
  ['POS Terminal', '/beverage_pos.php?tab=pos'],
  ['Sales Reports', '/beverage_pos.php?tab=sales-account'],
  ['Customers', '/beverage_pos.php?tab=customers'],
  ['Cash Drawer', '/beverage_pos.php?tab=recon'],
  ['Dispatch', '/dashboard.php?tab=tab-logistics&view=dispatch&company=beverage'],
  ['Returns', '/dashboard.php?tab=tab-logistics&view=returns&company=beverage'],
  ['Delivery POD', '/dashboard.php?tab=tab-logistics&view=pod&company=beverage'],
  ['HR Dashboard', '/dashboard.php?tab=tab-hr-payroll&view=dashboard&company=beverage'],
  ['Employees', '/dashboard.php?tab=tab-hr-payroll&view=employees&company=beverage'],
  ['Departments', '/dashboard.php?tab=tab-hr-payroll&view=departments&company=beverage'],
  ['KYC Verification', '/dashboard.php?tab=tab-hr-payroll&view=kyc&company=beverage'],
  ['Payroll Management', '/dashboard.php?tab=tab-hr-payroll&view=payroll&company=beverage'],
  ['Attendance', '/dashboard.php?tab=tab-geo-attendance&company=beverage'],
  ['Procurement', '/dashboard.php?tab=tab-procurement&company=beverage'],
  ['Payment Recon', '/dashboard.php?tab=tab-payment-recon&company=beverage'],
  ['Maintenance', '/dashboard.php?tab=tab-maintenance&company=beverage'],
  ['AI Assistant', '/dashboard.php?tab=tab-central-ai&company=beverage'],
  ['Analytics', '/dashboard.php?tab=tab-health-score&company=beverage'],
  ['Finance', '/finance.php?tab=tab-dash-finance'],
  ['Settings', '/dashboard.php?tab=tab-system-settings&company=beverage'],
  ['Notifications', '/dashboard.php?tab=tab-notifications&company=beverage'],
];

function compactText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

async function collect(page, label, route, width) {
  return page.evaluate(({ label, route, width }) => {
    const doc = document.documentElement;
    const body = document.body;
    const viewport = doc.clientWidth;
    const main = document.querySelector('main') || document.body;
    const mainText = (main.textContent || '').replace(/\s+/g, ' ').trim();
    const h = [...document.querySelectorAll('main h1, main h2, main h3')]
      .map((el) => el.textContent.trim())
      .filter(Boolean)
      .slice(0, 8);
    const cards = [...document.querySelectorAll('main .warehouse-card, main .overview-card, main .overview-kpi, main .hr-panel, main .pos-card, main .module-card, main .dash-card')];
    const forms = [...document.querySelectorAll('main form')];
    const tables = [...document.querySelectorAll('main table')];
    const hasEmptyState = !!document.querySelector('main .empty-state') || /no .* yet|no .* saved|no .* recorded/i.test(mainText);
    const activeNav = [...document.querySelectorAll('.bev-nav-item.active, .bev-nav-subitem.active')]
      .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
      .filter(Boolean);
    const invalidLinks = [...document.querySelectorAll('a[href]')]
      .map((a) => ({ text: (a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 80), href: a.getAttribute('href') || '' }))
      .filter((a) => a.href === '#' || a.href.toLowerCase().startsWith('javascript:'));
    const onclickFns = [...document.querySelectorAll('button[onclick], a[onclick]')]
      .map((el) => (el.getAttribute('onclick') || '').split('(')[0].trim())
      .filter((fn) => fn && fn !== 'document.getElementById' && !fn.includes('.'));
    const missingOnclickFunctions = [...new Set(onclickFns)].filter((fn) => typeof window[fn] !== 'function');
    const offenders = [...document.querySelectorAll('body *')]
      .map((el) => {
        const rect = el.getBoundingClientRect();
        return {
          tag: el.tagName.toLowerCase(),
          id: el.id || '',
          cls: String(el.className || '').slice(0, 90),
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
          text: (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 70),
        };
      })
      .filter((item) => item.width > 0 && (item.right > viewport + 2 || item.left < -2))
      .slice(0, 8);

    const visibleText = [...main.querySelectorAll('h1,h2,h3,p,a,button,th,td,label,small,strong,span')]
      .filter((el) => {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      })
      .map((el) => (el.textContent || '').replace(/\s+/g, ' ').trim())
      .filter(Boolean)
      .join(' ');
    const words = visibleText.toLowerCase().replace(/[^a-z0-9 ]/g, ' ').split(/\s+/).filter((w) => w.length > 2);
    const signature = words.slice(0, 250).join(' ');

    return {
      label,
      route,
      width,
      finalUrl: location.href,
      title: document.title,
      statusText: document.body ? String(document.body.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 140) : '',
      redirectedToLogin: /login\.php/i.test(location.href) || !!document.querySelector('form[action*="login.php"]'),
      headings: h,
      activeNav,
      mainTextLength: mainText.length,
      hasEmptyState,
      cardCount: cards.length,
      formCount: forms.length,
      tableCount: tables.length,
      invalidLinks,
      missingOnclickFunctions,
      overflowX: Math.max(doc.scrollWidth, body ? body.scrollWidth : 0) - viewport,
      scrollHeight: doc.scrollHeight,
      offenders,
      signature,
    };
  }, { label, route, width });
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const report = {
    startedAt: new Date().toISOString(),
    base,
    results: [],
    duplicates: [],
  };

  for (const width of [390, 1366]) {
    const context = await browser.newContext({
      viewport: { width, height: width < 700 ? 844 : 900 },
      deviceScaleFactor: width < 700 ? 3 : 1,
      isMobile: width < 700,
      hasTouch: width < 700,
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

    for (const [label, route] of pages) {
      const page = await context.newPage();
      const result = { label, route, width };
      const started = Date.now();
      page.on('console', (msg) => {
        if (['error', 'warning'].includes(msg.type())) {
          result.console = result.console || [];
          result.console.push({ type: msg.type(), text: msg.text() });
        }
      });
      page.on('requestfailed', (request) => {
        result.networkFailures = result.networkFailures || [];
        result.networkFailures.push({ url: request.url(), failure: request.failure()?.errorText || '' });
      });
      try {
        await page.goto(`${base}${route}`, { waitUntil: 'networkidle', timeout: 45000 });
        await page.waitForTimeout(350);
        Object.assign(result, await collect(page, label, route, width));
        result.loadMs = Date.now() - started;
        if (width === 390) {
          const safeName = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
          result.screenshot = path.join(outDir, `${safeName}-mobile.png`);
          await page.screenshot({ path: result.screenshot, fullPage: true });
        }
      } catch (error) {
        result.error = error.message;
        result.loadMs = Date.now() - started;
      } finally {
        report.results.push(result);
        await page.close().catch(() => {});
      }
    }
    await context.close();
  }

  const desktop = report.results.filter((item) => item.width === 1366 && !item.error && !item.redirectedToLogin);
  for (let i = 0; i < desktop.length; i += 1) {
    for (let j = i + 1; j < desktop.length; j += 1) {
      if (desktop[i].signature && desktop[i].signature === desktop[j].signature) {
        report.duplicates.push([desktop[i].label, desktop[j].label]);
      }
    }
  }

  const reportPath = path.join(outDir, 'all-menu-report.json');
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));

  for (const item of report.results) {
    const flags = [];
    if (item.error) flags.push('ERROR');
    if (item.redirectedToLogin) flags.push('LOGIN');
    if ((item.overflowX || 0) > 0) flags.push(`OVERFLOW ${item.overflowX}px`);
    if ((item.mainTextLength || 0) < 500 && !item.hasEmptyState) flags.push('LOW CONTENT');
    if (item.invalidLinks && item.invalidLinks.length) flags.push(`BAD LINKS ${item.invalidLinks.length}`);
    if (item.missingOnclickFunctions && item.missingOnclickFunctions.length) flags.push(`MISSING JS ${item.missingOnclickFunctions.join(',')}`);
    if (item.console && item.console.length) flags.push(`CONSOLE ${item.console.length}`);
    console.log(`${item.width}px ${item.label}: ${flags.join(' | ') || 'ok'} load=${item.loadMs || 0}ms cards=${item.cardCount || 0} forms=${item.formCount || 0} tables=${item.tableCount || 0}`);
  }
  if (report.duplicates.length) {
    console.log(`duplicates=${JSON.stringify(report.duplicates)}`);
  }
  console.log(`report=${reportPath}`);
  await browser.close();
})();
