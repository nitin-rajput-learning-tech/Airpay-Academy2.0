// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: the cart screen checks of docs/visual-evidence/2026-09-29/README.md
// ("Cart (ADR-031 decision 3)"), on the orders seed_cart_evidence.php creates.
//   1. Site admin, admin_orders.php: Staff notes column; the paid order with a
//      withheld line shows the refund-due note; #/User/Total/Status are filled.
//   2. Tenant admin /1, admin_orders.php: what that persona gets.
//   3. Learner /1, notifications: the payment message lists only the granted
//      course plus the withheld/refund line.
//   4. Site admin, notifications: "New order #N - Refund due".
//
//   node cart_checks.mjs --order <orderid> [--failed-order <orderid>] [--out <dir>]

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = 'http://localhost:8080';
const argv = process.argv.slice(2);
const opt = (n, d) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : d; };
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-09-29/cart')));
const ORDER = opt('--order');
const FAILED = opt('--failed-order', '');
const creds = JSON.parse(fs.readFileSync(path.join(HERE, '.personas.local.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];

// The local box is slow and sometimes answers a request with a 500 after PHP's
// 120 s limit (session lock / cache rebuild). Retry a navigation a few times.
async function gotoRetry(page, url) {
  for (let attempt = 1; ; attempt++) {
    try {
      return await page.goto(url, { waitUntil: 'domcontentloaded' });
    } catch (e) {
      if (attempt >= 4) throw e;
      console.error(`retry ${attempt} ${url}: ${String(e.message).split('\n')[0]}`);
      await page.waitForTimeout(20000);
    }
  }
}

async function session(persona) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  page.setDefaultTimeout(240000);
  await gotoRetry(page, BASE + '/login/index.php');
  await page.fill('#username', persona);
  await page.fill('#password', creds[persona].password);
  await page.click('#loginbtn', { noWaitAfter: true });
  await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
  return { context, page };
}

// The theme scrolls inside its own container, so fullPage captures only the
// viewport: scroll `focus` (a locator) into view first when given.
async function shots(page, name, focus = null) {
  if (focus && await focus.count()) await focus.first().scrollIntoViewIfNeeded().catch(() => {});
  await page.screenshot({ path: path.join(OUT, `${name}-desktop.png`) });
  await page.setViewportSize({ width: 590, height: 1000 });
  await page.waitForTimeout(800);
  if (focus && await focus.count()) await focus.first().scrollIntoViewIfNeeded().catch(() => {});
  await page.screenshot({ path: path.join(OUT, `${name}-mobile.png`) });
  await page.setViewportSize({ width: 1440, height: 900 });
}

const bodyText = page => page.locator('body').innerText();

// The cells of the table row that mentions `needle`, or null.
async function rowCells(page, needle) {
  const rows = page.locator('table tbody tr', { hasText: needle });
  if (!(await rows.count())) {
    return null;
  }
  return rows.first().locator('td').allInnerTexts();
}

async function ordersPage(persona, name, check) {
  const { context, page } = await session(persona);
  // The table is filled over AJAX (list_orders): wait for our order or a refusal;
  // "Failed to load data" on this slow box is a timed-out request - reload.
  for (let attempt = 1; attempt <= 3; attempt++) {
    await gotoRetry(page, `${BASE}/local/sentientia_cart/admin_orders.php`);
    await page.waitForFunction(o => document.body.innerText.includes(o)
        || /permission|not available|nopermissions|Failed to load data/i.test(document.body.innerText),
      ORDER, { timeout: 240000 }).catch(() => {});
    if (!/Failed to load data/.test(await bodyText(page))) break;
    console.error(`${persona}: table failed to load, attempt ${attempt}`);
  }
  await page.waitForTimeout(1500);
  const body = await bodyText(page);
  const headers = await page.locator('table thead th').allInnerTexts().catch(() => []);
  const row = await rowCells(page, ORDER).catch(() => null);
  const failedRow = FAILED ? await rowCells(page, FAILED).catch(() => null) : null;
  await shots(page, name, page.locator('table tbody tr', { hasText: ORDER }));
  await context.close();
  const refused = /permission|not available/i.test(body) && !row;
  const r = { check, persona, headers: headers.map(h => h.trim()), row, failedRow, refused };
  results.push(r);
  return r;
}

async function notificationsPage(persona, name, check, expect) {
  const { context, page } = await session(persona);
  await gotoRetry(page, `${BASE}/message/output/popup/notifications.php`);
  await page.waitForFunction(e => document.body.innerText.includes(e), expect, { timeout: 240000 }).catch(() => {});
  // Open the matching notification so its full text shows.
  const item = page.locator('[data-region="notification-content-item-container"]', { hasText: expect }).first();
  if (await item.count()) {
    await item.click().catch(() => {});
    await page.waitForTimeout(3000);
  }
  const body = await bodyText(page);
  await shots(page, name);
  await context.close();
  const at = body.indexOf(expect);
  results.push({ check, persona, found: at >= 0, excerpt: at >= 0 ? body.slice(at, at + 700) : '' });
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
}

// 1 + 2: the orders list.
const sa = await ordersPage('vp_siteadmin', '01-siteadmin-admin-orders', '1 site admin admin_orders');
sa.pass = !!sa.row && sa.headers.some(h => /notes/i.test(h))
  && sa.row.some(c => /Refund due/.test(c)) && sa.row.some(c => c.includes(ORDER));
await ordersPage('vp_admin1', '02-admin1-admin-orders', '2 tenant admin /1 admin_orders');

// 3 + 4: the messages.
await notificationsPage('vp_learner1', '03-learner1-notifications', '3 learner payment message', 'Your order has been placed');
await notificationsPage('vp_siteadmin', '04-siteadmin-notifications', '4 site admin refund due', `New order #${ORDER} - Refund due`);

await browser.close();
fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
for (const r of results) console.log(JSON.stringify(r));
