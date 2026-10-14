// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY helpers shared by the 2026-10-14 evidence scripts (gst_checks.mjs, eval_checks.mjs, ...). Same pattern
// as persona_journeys.mjs: base URL fixed to http://localhost:8080; every request that is not localhost is aborted
// in the browser (except the two Google Fonts hosts, static CSS/font files, so screenshots use the real typeface);
// logins read .personas.local.json (gitignored) and passwords are never printed; a dropped connection (local Apache
// restart) waits for port 8080 and retries once; a login that ends in "session has timed out" is retried in a fresh
// browser context.

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const HERE = path.dirname(fileURLToPath(import.meta.url));
export const BASE = 'http://localhost:8080';
const LOCAL = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?(\/|$)/i;
const ALLOWED_EXTERNAL = /^https?:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com)\//i;
if (!LOCAL.test(BASE)) {
  throw new Error('evidence scripts are local-only');
}
const CONN_ERR = /ERR_CONNECTION_(RESET|REFUSED|CLOSED|ABORTED)|ERR_EMPTY_RESPONSE|ECONNRESET|ECONNREFUSED|socket hang up|ERR_NETWORK_CHANGED/i;
const IGNORE_CONSOLE = [/ERR_FAILED/i, /ERR_BLOCKED_BY_CLIENT/i, /ERR_NETWORK_CHANGED/i, /favicon/i, /Download the React DevTools/i];

export const argv = process.argv.slice(2);
export const has = n => argv.includes(n);
export const opt = (n, d = '') => {
  const i = argv.indexOf(n);
  return i >= 0 && argv[i + 1] !== undefined && !String(argv[i + 1]).startsWith('--') ? argv[i + 1] : d;
};
if (has('--base') || has('--url')) {
  console.error('The base URL is fixed to http://localhost:8080 - this harness never targets another host.');
  process.exit(2);
}

export const readJson = f => (fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')) : null);
export const creds = readJson(path.join(HERE, '.personas.local.json'));
if (!creds) {
  console.error('.personas.local.json missing - run provision_local_personas.php first (cwd = moodle5/public).');
  process.exit(2);
}

export const sleep = ms => new Promise(r => setTimeout(r, ms));

export async function waitForServer(maxMs = 240000) {
  const t0 = Date.now();
  while (Date.now() - t0 < maxMs) {
    const up = await new Promise(resolve => {
      const sock = net.createConnection({ host: '127.0.0.1', port: 8080 });
      sock.setTimeout(3000);
      sock.on('connect', () => { sock.destroy(); resolve(true); });
      sock.on('timeout', () => { sock.destroy(); resolve(false); });
      sock.on('error', () => resolve(false));
    });
    if (up) {
      await sleep(6000);
      return true;
    }
    await sleep(3000);
  }
  return false;
}

export async function launch() {
  return chromium.launch({ channel: 'chrome', headless: true });
}

// A browser context with the local-only guarantee.
export async function newLocalContext(browser) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  await context.route(u => /^https?:$/.test(u.protocol) && !LOCAL.test(u.href) && !ALLOWED_EXTERNAL.test(u.href), route => {
    route.abort('blockedbyclient').catch(() => {});
  });
  return context;
}

async function loginOnce(context, username) {
  const page = await context.newPage();
  page.setDefaultTimeout(240000);
  try {
    for (let g = 1; g <= 2; g++) {
      try {
        await page.goto(BASE + '/login/index.php', { waitUntil: 'domcontentloaded', timeout: 240000 });
        break;
      } catch (err) {
        if (g === 2) throw err;
        if (CONN_ERR.test(String(err.message))) await waitForServer();
        else await page.waitForTimeout(10000);
      }
    }
    await page.fill('#username', username);
    await page.fill('#password', creds[username].password);
    await page.click('#loginbtn', { noWaitAfter: true });
    const outcome = await Promise.race([
      page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 }).then(() => 'ok'),
      page.waitForFunction(() => location.pathname.includes('/login/')
        && /session has timed out|invalid login|account has been locked|too many/i.test(document.body ? document.body.innerText : ''),
      null, { timeout: 240000, polling: 1000 }).then(() => 'error'),
    ]);
    if (outcome === 'ok') {
      return { ok: true };
    }
    const why = (await page.locator('.alert, .loginerrors, #loginerrormessage').allInnerTexts().catch(() => [])).join(' | ');
    return { ok: false, timedOut: /session has timed out/i.test(why), reason: `login refused: ${why.slice(0, 200)}` };
  } catch (err) {
    return { ok: false, reason: `login did not complete: ${String(err.message).split('\n')[0].slice(0, 120)}` };
  } finally {
    await page.close().catch(() => {});
  }
}

// Log a persona in (up to 3 tries, each in a fresh context). Returns { context, page } or throws.
export async function session(browser, username) {
  let last = null;
  for (let attempt = 1; attempt <= 4; attempt++) {
    const context = await newLocalContext(browser);
    const r = await loginOnce(context, username);
    if (r.ok) {
      const page = await context.newPage();
      page.setDefaultTimeout(240000);
      page.errs = [];
      page.on('console', m => {
        if (m.type() === 'error' && !IGNORE_CONSOLE.some(re => re.test(m.text()))) page.errs.push(m.text().slice(0, 240));
      });
      page.on('pageerror', e => page.errs.push('pageerror: ' + String(e.message).slice(0, 240)));
      return { context, page };
    }
    await context.close().catch(() => {});
    last = r;
    if (r.conn) {
      // The local Apache dropped the connection (worker crash, watchdog restart): wait for it and try again.
      await waitForServer();
      continue;
    }
    if (!r.timedOut) break;
    await sleep(5000);
  }
  throw new Error(`login failed for ${username}: ${last && last.reason}`);
}

// Navigate with one retry on a 5xx or a dropped connection. Returns the Response (or null).
export async function go(page, url) {
  const abs = /^https?:/i.test(url) ? url : BASE + url;
  if (!LOCAL.test(abs)) throw new Error('refusing a non-local URL');
  let res = null;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      res = await page.goto(abs, { waitUntil: 'domcontentloaded', timeout: 240000 });
      if (res && res.status() >= 500 && attempt < 3) {
        await page.waitForTimeout(10000);
        continue;
      }
      break;
    } catch (err) {
      const msg = String(err.message).split('\n')[0];
      if (attempt === 3) throw err;
      if (CONN_ERR.test(msg)) await waitForServer();
      else await page.waitForTimeout(10000);
    }
  }
  await page.waitForLoadState('load', { timeout: 90000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  // An AJAX table that timed out on this slow box: reload (twice at most).
  for (let i = 0; i < 2; i++) {
    const t = await page.locator('body').innerText().catch(() => '');
    if (!/Failed to load data/i.test(t)) break;
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 240000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  }
  await page.waitForTimeout(500);
  return res;
}

// Real defects on a page: a PHP message, a Moodle debug dump, a missing-string marker, an unrendered mustache tag.
const PHPERR = /Coding error detected|Exception - |(?:Warning|Notice|Deprecated|Fatal error): .{0,200} on line \d+|Call to undefined|Uncaught /;
const DEBUGTXT = /Debug info:|Error code: |Stack trace:/;
const MISSING = /\[\[[A-Za-z0-9_:/.,-]+\]\]/g;
const UNRENDERED = /\{\{[#^/>]?[A-Za-z_.]+\}\}/;
export async function scan(page) {
  const text = await page.locator('body').innerText().catch(() => '');
  const problems = [];
  if (PHPERR.test(text)) problems.push('PHP message on page');
  if (DEBUGTXT.test(text)) problems.push('Moodle debug dump on page');
  const missing = text.match(MISSING);
  if (missing) problems.push('missing string marker(s): ' + [...new Set(missing)].slice(0, 5).join(' '));
  if (UNRENDERED.test(text)) problems.push('unrendered mustache tag');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    .catch(() => 0);
  return { text, problems, overflow };
}

// The height needed to show the whole page: the document, or an inner scroll container (the theme scrolls inside
// its own box on some layouts) plus the chrome around it. Capped so a runaway page cannot make a huge PNG.
async function fullHeight(page) {
  return page.evaluate(() => {
    let h = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (/(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 20 && el.clientHeight > 300) {
        h = Math.max(h, window.innerHeight - el.clientHeight + el.scrollHeight);
      }
    }
    return h;
  });
}

// Desktop (1440 wide) and mobile (590 wide) screenshots of the whole page into <out>/<name>-{desktop,mobile}.png.
// Returns the file names. `prepare` (async, optional) runs after each resize, for things the resize resets.
export async function shots(page, out, name, { prepare = null, maxHeight = 3400, mobile = true, minHeight = 900 } = {}) {
  fs.mkdirSync(out, { recursive: true });
  const files = [];
  files.overflow = {};
  const sizes = [['desktop', 1440, 900]];
  if (mobile) sizes.push(['mobile', 590, 1000]);
  for (const [label, w, h] of sizes) {
    await page.setViewportSize({ width: w, height: h });
    await page.waitForTimeout(700);
    if (prepare) await prepare(label);
    const need = Math.min(Math.max(await fullHeight(page).catch(() => h), minHeight) + 8, maxHeight);
    if (need > h) {
      await page.setViewportSize({ width: w, height: need });
      await page.waitForTimeout(700);
    }
    files.overflow[label] = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      .catch(() => 0);
    const file = `${name}-${label}.png`;
    await page.screenshot({ path: path.join(out, file) });
    files.push(file);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  return files;
}
