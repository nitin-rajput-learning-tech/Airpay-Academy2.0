// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: log in as one vp_* persona and print, per URL, the HTTP status and
// the page's first error / notice message (no screenshots). Used to read the
// exact refusal a page gives.
//
//   node probe_messages.mjs <persona> <path> [<path> ...]

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = 'http://localhost:8080';
const [persona, ...paths] = process.argv.slice(2);
const creds = JSON.parse(fs.readFileSync(path.join(HERE, '.personas.local.json'), 'utf8'));
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const context = await browser.newContext({ serviceWorkers: 'block' });
const page = await context.newPage();
page.setDefaultTimeout(240000);
await page.goto(BASE + '/login/index.php', { waitUntil: 'domcontentloaded' });
await page.fill('#username', persona);
await page.fill('#password', creds[persona].password);
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
for (const p of paths) {
  const started = Date.now();
  try {
    const resp = await page.goto(BASE + p, { waitUntil: 'domcontentloaded', timeout: 300000 });
    const msg = await page.locator('.errorbox .errormessage, .alert-danger, .alert-warning, .alert-info, [role=alert]')
      .first().innerText({ timeout: 5000 }).catch(() => '(no alert box)');
    console.log(`${p} -> HTTP ${resp ? resp.status() : 0} in ${Math.round((Date.now() - started) / 1000)}s | `
      + `${msg.replace(/\s+/g, ' ').slice(0, 220)}`);
  } catch (e) {
    console.log(`${p} -> NAVIGATION FAILED after ${Math.round((Date.now() - started) / 1000)}s: `
      + e.message.split('\n')[0].slice(0, 160));
  }
}
await browser.close();
