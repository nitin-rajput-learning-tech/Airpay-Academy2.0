// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY Playwright screenshot pass for the ADR-031 screen-check list.
//
//   node visual_pass.mjs [--out <dir>] [--only <persona,...>]
//
// Logs in as the vp_* personas created by provision_local_personas.php (their
// generated test passwords are read from .personas.local.json, gitignored), visits
// each persona's pages on http://localhost:8080 one at a time, and saves desktop
// (1440) and mobile (590, the theme's primary breakpoint) screenshots plus
// results.json / results.md with, per page: HTTP status, final URL, title,
// PHP/Moodle error markers, missing-string markers ([[...]]), whether the page
// refused access, and JS console errors. Refuses any base URL that is not
// localhost. Uses the installed Chrome (channel 'chrome'), headless, one page at
// a time to keep CPU low.

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = 'http://localhost:8080';
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(BASE)) {
  throw new Error('visual pass is local-only');
}
const argv = process.argv.slice(2);
const opt = (name, dflt) => { const i = argv.indexOf(name); return i >= 0 ? argv[i + 1] : dflt; };
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-09-29')));
const ONLY = (opt('--only', '') || '').split(',').filter(Boolean);
const creds = JSON.parse(fs.readFileSync(path.join(HERE, '.personas.local.json'), 'utf8'));

// persona -> [slug, path, expectation]; expectation is 'ok' or 'refused'.
const PLAN = {
  vp_siteadmin: [
    ['courses', '/local/sentientia_courses/index.php', 'ok'],
    ['featured', '/local/sentientia_courses/featured.php', 'ok'],
    ['roles', '/local/sentientia_roles/index.php', 'ok'],
    ['emails-manage', '/local/sentientia_emails/manage.php', 'ok'],
    ['privacy-dpdp', '/local/sentientia_privacy/index.php', 'ok'],
    ['compliance', '/local/sentientia_compliance_report/index.php', 'ok'],
    ['analytics', '/local/sentientia_analytics/index.php', 'ok'],
    ['scim', '/local/sentientia_api/scim.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
  ],
  vp_admin1: [
    ['courses', '/local/sentientia_courses/index.php', 'ok'],
    ['featured', '/local/sentientia_courses/featured.php', 'ok'],
    ['share', '/local/sentientia_courses/share.php', 'refused'],
    ['manage-requests', '/local/sentientia_courses/manage_requests.php', 'refused'],
    ['users', '/local/sentientia_users/index.php', 'ok'],
    ['roles', '/local/sentientia_roles/index.php', 'ok'],
    ['org-admin', '/local/sentientia_org/admin.php', 'ok'],
    ['classroom', '/local/sentientia_classroom/index.php', 'ok'],
    ['programs', '/local/sentientia_programs/index.php', 'ok'],
    ['learningpath', '/local/sentientia_learningpath/index.php', 'ok'],
    ['recompletion', '/local/sentientia_recompletion/index.php', 'ok'],
    ['evaluation', '/local/sentientia_evaluation/index.php', 'ok'],
    ['exams', '/local/sentientia_exams/index.php', 'ok'],
    ['skills-admin', '/local/sentientia_skills/admin.php', 'refused'],
    ['skills-mapping', '/local/sentientia_skills/course_mapping.php', 'ok'],
    ['emails-manage', '/local/sentientia_emails/manage.php', 'ok'],
    ['notifications', '/local/sentientia_notifications/index.php', 'refused'],
    ['notifications-logs', '/local/sentientia_notifications/logs.php', 'ok'],
    ['reports', '/local/sentientia_reports/index.php', 'ok'],
    ['request-all', '/local/sentientia_request/all.php', 'ok'],
    ['cart-prices', '/local/sentientia_cart/set_price.php', 'ok'],
    ['challenges', '/local/sentientia_challenge/index.php', 'ok'],
    ['leaderboards', '/local/sentientia_leaderboard/index.php', 'ok'],
    ['ai-ledger', '/local/sentientia_ai/index.php', 'refused'],
    ['compliance', '/local/sentientia_compliance_report/index.php', 'ok'],
    ['analytics', '/local/sentientia_analytics/index.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
  ],
  vp_admin177: [
    ['courses', '/local/sentientia_courses/index.php', 'ok'],
    ['users', '/local/sentientia_users/index.php', 'ok'],
    ['org-admin', '/local/sentientia_org/admin.php', 'ok'],
    ['emails-manage', '/local/sentientia_emails/manage.php', 'ok'],
    ['compliance', '/local/sentientia_compliance_report/index.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
  ],
  vp_manager1: [
    ['team', '/local/sentientia_manager/index.php', 'ok'],
    ['compliance', '/local/sentientia_compliance_report/index.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
  ],
  vp_learner1: [
    ['catalog', '/local/sentientia_catalog/index.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
    ['leaderboards', '/local/sentientia_leaderboard/index.php', 'ok'],
    ['skills', '/local/sentientia_skills/index.php', 'ok'],
    ['challenges', '/local/sentientia_challenge/index.php', 'ok'],
    ['users-admin', '/local/sentientia_users/index.php', 'refused'],
  ],
  vp_learner177: [
    ['catalog', '/local/sentientia_catalog/index.php', 'ok'],
    ['dashboard', '/my/dashboard.php', 'ok'],
  ],
  vp_author1: [
    ['authoring', '/local/sentientia_authoring/index.php', 'ok'],
    ['templates', '/local/sentientia_authoring/templates.php', 'ok'],
    ['evaluation', '/local/sentientia_evaluation/index.php', 'ok'],
  ],
  guest: [
    ['catalog', '/local/sentientia_catalog/index.php', 'ok'],
    ['login', '/login/index.php', 'ok'],
  ],
};

const REFUSAL = /do not currently have permissions|not available to you|do not have access to this tenant|Only a cross-tenant|cannot be accessed|Access denied|requires a cross-tenant/i;
const PHPERR = /Coding error detected|Exception - |Stack trace:|Debug info:|error\/moodle|Fatal error|Warning: |Notice: |Deprecated: /;
const MISSING = /\[\[[a-z0-9_:\/.-]+\]\]/gi;

fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
let n = 0;

async function login(context, persona) {
  const page = await context.newPage();
  page.setDefaultTimeout(120000);
  await page.goto(BASE + '/login/index.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#username', persona);
  await page.fill('#password', creds[persona].password);
  // The local login POST can take minutes on a cold cache: wait for the
  // redirect away from the login page instead of checking the URL at once.
  await page.click('#loginbtn', { noWaitAfter: true });
  let ok = true;
  try {
    await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
  } catch (e) {
    ok = false;
    const why = await page.locator('.alert, .loginerrors, #loginerrormessage').allInnerTexts().catch(() => []);
    console.log(`${persona}: still on the login page after 240s: ${why.join(' | ').slice(0, 200)}`);
  }
  await page.close();
  return ok;
}

for (const [persona, pages] of Object.entries(PLAN)) {
  if (ONLY.length && !ONLY.includes(persona)) continue;
  // serviceWorkers 'block': the Sentientia PWA service worker (sw.php) takes over
  // navigations in headless Chrome and the post-login /my/ requests it issues
  // lose the session, looping back to the login page. Pages render the same
  // without it (a first visit has no worker either).
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  if (persona !== 'guest') {
    const ok = await login(context, persona);
    if (!ok) {
      results.push({ persona, slug: 'login', path: '/login/index.php', outcome: 'LOGIN FAILED' });
      console.log(`${persona}: LOGIN FAILED`);
      await context.close();
      continue;
    }
  }
  for (const [slug, url, expect] of pages) {
    n += 1;
    const page = await context.newPage();
    page.setDefaultTimeout(120000);
    const consoleErrors = [];
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
    page.on('pageerror', e => consoleErrors.push(('pageerror: ' + e.message).slice(0, 200)));
    let status = 0;
    try {
      const resp = await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
      status = resp ? resp.status() : 0;
      await page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {});
    } catch (e) {
      consoleErrors.push('navigation: ' + e.message.slice(0, 200));
    }
    const text = await page.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
    const title = await page.title().catch(() => '');
    const refused = REFUSAL.test(text);
    const phperr = (text.match(PHPERR) || [])[0] || '';
    const missing = [...new Set(text.match(MISSING) || [])].slice(0, 10);
    const base = `${String(n).padStart(2, '0')}-${persona.replace('vp_', '')}-${slug}`;
    await page.screenshot({ path: path.join(OUT, `${base}-desktop.png`), fullPage: true }).catch(() => {});
    await page.setViewportSize({ width: 590, height: 1000 });
    await page.waitForTimeout(800);
    await page.screenshot({ path: path.join(OUT, `${base}-mobile.png`), fullPage: true }).catch(() => {});
    let outcome = 'PASS';
    if (status >= 500 || phperr) outcome = 'FAIL (error)';
    else if (expect === 'ok' && refused) outcome = 'FAIL (refused)';
    else if (expect === 'refused' && !refused) outcome = 'CHECK (expected a refusal)';
    else if (missing.length) outcome = 'FAIL (missing strings)';
    results.push({ persona, slug, path: url, expect, status, finalurl: page.url().replace(BASE, ''), title,
      refused, phperr, missing, consoleErrors: consoleErrors.slice(0, 5), shot: base, outcome });
    console.log(`${base}: ${outcome} (HTTP ${status}${refused ? ', refused' : ''}${phperr ? ', ' + phperr : ''})`);
    await page.close();
  }
  await context.close();
}
await browser.close();

fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
const md = ['| # | Persona | Page | Expect | HTTP | Outcome | Notes |', '|---|---|---|---|---|---|---|'];
for (const r of results) {
  const notes = [r.phperr, (r.missing || []).join(' '), (r.consoleErrors || []).length ? `${r.consoleErrors.length} console error(s)` : '']
    .filter(Boolean).join('; ');
  md.push(`| ${r.shot || '-'} | ${r.persona} | \`${r.path}\` | ${r.expect || '-'} | ${r.status ?? '-'} | ${r.outcome} | ${notes} |`);
}
fs.writeFileSync(path.join(OUT, 'results.md'), md.join('\n') + '\n');
const tally = results.reduce((a, r) => { a[r.outcome] = (a[r.outcome] || 0) + 1; return a; }, {});
console.log('TALLY', JSON.stringify(tally));
