const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8095';
const outDir = process.env.AUDIT_OUT_DIR || '/private/tmp/product-menu-audit-360';
const sessionId = process.env.AUDIT_SESSION_ID || 'codexvisualaudit';

function visibleRows(rows) {
  return rows.filter((row) => row.display !== 'none');
}

async function collectMetrics(page, label) {
  return page.evaluate((label) => {
    const doc = document.documentElement;
    const rows = [...document.querySelectorAll('#topInventoryTbody tr.inventory-row')].map((row) => ({
      sku: row.dataset.sku || '',
      name: row.dataset.name || '',
      stock: Number(row.dataset.stock || 0),
      display: getComputedStyle(row).display,
    }));
    const links = [...document.querySelectorAll('a[href]')]
      .map((a) => ({
        text: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80),
        href: a.getAttribute('href'),
      }))
      .filter((a) => !a.href || a.href === '#' || a.href.startsWith('javascript:'));
    const buttons = [...document.querySelectorAll('button[onclick]')]
      .map((btn) => (btn.getAttribute('onclick') || '').split('(')[0].trim())
      .filter((fn) => fn && fn !== 'document.getElementById');
    const missingFns = [...new Set(buttons)].filter((fn) => typeof window[fn] !== 'function');

    return {
      label,
      title: document.title,
      url: location.href,
      viewport: doc.clientWidth,
      overflowX: Math.max(doc.scrollWidth, document.body.scrollWidth) - doc.clientWidth,
      scrollHeight: doc.scrollHeight,
      totalRows: rows.length,
      visibleRows: rows.filter((row) => row.display !== 'none').length,
      countBadge: document.querySelector('#inventory_count_badge')?.textContent?.trim() || '',
      warehouseOptions: [...document.querySelectorAll('.warehouse-hero-actions select option')].map((o) => o.textContent.trim()),
      invalidLinks: links,
      missingOnclickFunctions: missingFns,
    };
  }, label);
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const report = {
    started_at: new Date().toISOString(),
    page: `${base}/beverage_warehouse.php?tab=tab-inventory`,
    console: [],
    networkFailures: [],
    checks: [],
  };

  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
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

  const page = await context.newPage();
  page.on('console', (msg) => {
    if (['error', 'warning'].includes(msg.type())) {
      report.console.push({ type: msg.type(), text: msg.text() });
    }
  });
  page.on('requestfailed', (request) => {
    report.networkFailures.push({
      url: request.url(),
      failure: request.failure()?.errorText || 'request failed',
    });
  });

  const start = Date.now();
  await page.goto(`${base}/beverage_warehouse.php?tab=tab-inventory`, { waitUntil: 'networkidle', timeout: 45000 });
  report.loadMs = Date.now() - start;
  await page.waitForTimeout(500);
  report.checks.push(await collectMetrics(page, 'initial'));
  await page.screenshot({ path: path.join(outDir, 'products-mobile-initial.png'), fullPage: true });

  const search = page.locator('#inventory_search_input');
  if (await search.count()) {
    await search.fill('pepsi');
    await page.waitForTimeout(250);
    report.checks.push(await collectMetrics(page, 'search-pepsi'));
    await page.locator('#inventory_search_clear').click().catch(() => {});
    await page.waitForTimeout(200);
  }

  const stockFilter = page.locator('#inventory_stock_filter');
  if (await stockFilter.count()) {
    await stockFilter.selectOption('low_stock').catch(() => {});
    await page.waitForTimeout(250);
    report.checks.push(await collectMetrics(page, 'filter-low-stock'));
    await stockFilter.selectOption('').catch(() => {});
  }

  await page.locator('#th_col_stock').click().catch(() => {});
  await page.waitForTimeout(250);
  report.checks.push(await collectMetrics(page, 'sort-stock'));

  await page.goto(`${base}/beverage_warehouse.php?tab=tab-inventory&detail=out-of-stock`, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(500);
  report.checks.push(await collectMetrics(page, 'detail-out-of-stock'));
  await page.goto(`${base}/beverage_warehouse.php?tab=tab-inventory`, { waitUntil: 'networkidle', timeout: 45000 });
  await page.waitForTimeout(500);

  const editButton = page.locator('button[aria-label="Edit Details"]').first();
  if (await editButton.count()) {
    await editButton.click();
    await page.waitForTimeout(250);
    report.editModal = {
      visible: await page.locator('#editProductModal').isVisible().catch(() => false),
      sku: await page.locator('#edit_sku').inputValue().catch(() => ''),
      name: await page.locator('#edit_name').inputValue().catch(() => ''),
      imageUrl: await page.locator('#edit_image_url').inputValue().catch(() => ''),
    };
    await page.evaluate(() => { const modal = document.getElementById('editProductModal'); if (modal) modal.style.display = 'none'; });
  }

  const auditButton = page.locator('button[aria-label="View Audit Trail"]').first();
  if (await auditButton.count()) {
    await auditButton.click();
    await page.waitForTimeout(250);
    report.auditModal = {
      visible: await page.locator('#productAuditModal').isVisible().catch(() => false),
      rows: await page.locator('#audit_modal_tbody tr').count().catch(() => 0),
    };
    await page.evaluate(() => { const modal = document.getElementById('productAuditModal'); if (modal) modal.style.display = 'none'; });
  }

  const importButton = page.locator('button[onclick="openExcelImportModal()"]').first();
  if (await importButton.count()) {
    await importButton.click();
    await page.waitForTimeout(250);
    report.importModal = {
      visible: await page.locator('#excelImportModal').isVisible().catch(() => false),
      hasFileInput: await page.locator('#excel_file_input').count().catch(() => 0),
    };
    await page.evaluate(() => { const modal = document.getElementById('excelImportModal'); if (modal) modal.style.display = 'none'; });
  }

  const imageButton = page.locator('button[aria-label="Find Online Image and Update"]').first();
  if (await imageButton.count()) {
    await imageButton.click();
    await Promise.race([
      page.locator('.image-result-card').first().waitFor({ state: 'visible', timeout: 12000 }).catch(() => {}),
      page.locator('#finder_empty_state').waitFor({ state: 'visible', timeout: 12000 }).catch(() => {}),
    ]);
    report.imageFinder = {
      visible: await page.locator('#onlineImageSearchModal').isVisible().catch(() => false),
      resultCards: await page.locator('.image-result-card').count().catch(() => 0),
      status: await page.locator('#finder_footer_status').textContent().catch(() => ''),
    };
  }

  await page.screenshot({ path: path.join(outDir, 'products-mobile-after-interactions.png'), fullPage: true });
  await page.close();
  await context.close();
  await browser.close();

  const reportPath = path.join(outDir, 'product-menu-report.json');
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    reportPath,
    loadMs: report.loadMs,
    console: report.console,
    networkFailures: report.networkFailures,
    checks: report.checks,
    editModal: report.editModal,
    auditModal: report.auditModal,
    importModal: report.importModal,
    imageFinder: report.imageFinder,
  }, null, 2));
})();
