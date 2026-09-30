// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: screen checks 49-50 (ADR-031 role 9) - the profile pencil.
//   49. Tenant admin /1 on a colleague's profile: the pencil opens the Sentientia
//       "Edit user" modal (never core /user/editadvanced.php).
//   49b. Tenant admin /1 on a site admin's profile inside /1: no pencil, no camera.
//   50. Site admin: the pencil still links to /user/editadvanced.php.
//
//   node profile_checks.mjs [--out <dir>] --colleague <userid> --siteadmin-in-tenant <userid>

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = 'http://localhost:8080';
const argv = process.argv.slice(2);
const opt = (n, d) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : d; };
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-09-29/profile-pencil')));
const COLLEAGUE = opt('--colleague');
const ADMININTENANT = opt('--siteadmin-in-tenant');
const creds = JSON.parse(fs.readFileSync(path.join(HERE, '.personas.local.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];

async function session(persona) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  page.setDefaultTimeout(240000);
  await page.goto(BASE + '/login/index.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#username', persona);
  await page.fill('#password', creds[persona].password);
  await page.click('#loginbtn', { noWaitAfter: true });
  await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
  return { context, page };
}

async function shots(page, name) {
  await page.screenshot({ path: path.join(OUT, `${name}-desktop.png`), fullPage: false });
  await page.setViewportSize({ width: 590, height: 1000 });
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, `${name}-mobile.png`), fullPage: false });
  await page.setViewportSize({ width: 1440, height: 900 });
}

async function pencilInfo(page) {
  return page.evaluate(() => {
    const links = [...document.querySelectorAll('a,button')].filter(e =>
      /editadvanced\.php/.test(e.getAttribute('href') || '') || e.dataset.action === 'edit-user');
    const loginas = [...document.querySelectorAll('a')].some(a => /loginas\.php/.test(a.getAttribute('href') || ''));
    const camera = !!document.querySelector('a[href*="photo.php"], [data-action="change-photo"]');
    return {
      pencils: links.map(e => ({ tag: e.tagName, href: e.getAttribute('href') || '', action: e.dataset.action || '' })),
      loginas, camera,
    };
  });
}

// 49 + 49b: tenant admin /1.
{
  const { context, page } = await session('vp_admin1');
  await page.goto(`${BASE}/local/sentientia_users/profile.php?id=${COLLEAGUE}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
  const info = await pencilInfo(page);
  await shots(page, '49-admin1-colleague-profile');
  let modal = false;
  let formFields = 0;
  const consoleErrors = [];
  const ajaxErrors = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 300)); });
  page.on('response', async r => {
    if (!/lib\/ajax\/service\.php/.test(r.url())) return;
    try {
      const body = await r.text();
      if (r.status() >= 400 || /"error"\s*:\s*true|"exception"/.test(body)) ajaxErrors.push(`${r.status()} ${body.slice(0, 300)}`);
    } catch (e) { /* body not available */ }
  });
  const pencil = page.locator('[data-action="edit-user"]').first();
  if (await pencil.count()) {
    await pencil.click();
    modal = await page.locator('.modal.show, .modal-dialog').first().waitFor({ timeout: 90000 }).then(() => true).catch(() => false);
    // ModalForm fetches the form over AJAX after the modal opens: an empty modal
    // is not a working edit path, so wait for real fields (slow local box).
    await page.locator('.modal.show form input:not([type="hidden"]), .modal.show form select')
      .first().waitFor({ timeout: 180000 }).catch(() => {});
    formFields = await page.locator('.modal.show form input:not([type="hidden"]), .modal.show form select').count();
    await page.waitForTimeout(1000);
    await shots(page, '49-admin1-colleague-edit-modal');
  }
  results.push({ check: '49 tenant admin -> colleague', ...info, modalOpened: modal, formFields,
    consoleErrors, ajaxErrors,
    pass: modal && formFields > 0 && ajaxErrors.length === 0 && !info.pencils.some(p => /editadvanced/.test(p.href)) });

  await page.goto(`${BASE}/local/sentientia_users/profile.php?id=${ADMININTENANT}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
  const info2 = await pencilInfo(page);
  const text2 = await page.evaluate(() => document.body.innerText.slice(0, 400));
  await shots(page, '49b-admin1-siteadmin-profile');
  results.push({ check: '49b tenant admin -> site admin in /1', ...info2,
    note: /not available/i.test(text2) ? 'profile refused' : 'profile shown',
    pass: info2.pencils.length === 0 && !info2.camera });
  await context.close();
}

// 50: site admin.
{
  const { context, page } = await session('vp_siteadmin');
  await page.goto(`${BASE}/local/sentientia_users/profile.php?id=${COLLEAGUE}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
  const info = await pencilInfo(page);
  await shots(page, '50-siteadmin-colleague-profile');
  results.push({ check: '50 site admin -> colleague', ...info,
    pass: info.pencils.some(p => /editadvanced/.test(p.href)) });
  await context.close();
}

await browser.close();
fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
for (const r of results) console.log(`${r.pass ? 'PASS' : 'CHECK'}  ${r.check}  ${JSON.stringify(r)}`);
