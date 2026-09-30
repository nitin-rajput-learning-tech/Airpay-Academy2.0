// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: screen checks for the 2026-09-30 QR-attendance and "Log in as" changes.
//   qr_scan.php        the result states a learner can land on
//   qr_attendance.php  the trainer's QR page with the "classroom - session title" heading
//   profile header     "Log in as" shown for a learner, hidden for a site admin and a suspended user
//
// Needs the data file written by seed_qr_evidence.php (session ids, tokens, user ids).
//
//   node qr_loginas_checks.mjs --data <seed.json> [--out <dir>] [--only 06,07,08]
//
// --only re-runs just those numbered checks (the screens keep their numbers, and the other
// results in results.json are kept). The scan checks change the data (check 01 records
// attendance), so a full run needs a fresh seed_qr_evidence.php run first.
//
// results.json is rewritten after every check, so a crash part-way keeps what was done.
//
// Environment (both optional):
//   PLAYWRIGHT_CORE_DIR  folder of the playwright-core package, when it is not installed next to this file
//   PERSONAS_FILE        the .personas.local.json that provision_local_personas.php wrote
//                        (default: next to this file). Passwords are read from it and never printed.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const pw = process.env.PLAYWRIGHT_CORE_DIR
  ? pathToFileURL(path.join(process.env.PLAYWRIGHT_CORE_DIR, 'index.mjs')).href
  : 'playwright-core';
const { chromium } = await import(pw);

const BASE = 'http://localhost:8080';
const argv = process.argv.slice(2);
const opt = (n, d) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : d; };
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-09-30/qr-and-loginas')));
const ONLY = opt('--only') ? opt('--only').split(',').map(x => x.trim()) : null;
const want = id => !ONLY || ONLY.includes(id);
const DATA = JSON.parse(fs.readFileSync(opt('--data'), 'utf8'));
const creds = JSON.parse(fs.readFileSync(process.env.PERSONAS_FILE || path.join(HERE, '.personas.local.json'), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

// results.json: kept across --only runs, rewritten after every check.
const RESULTS = path.join(OUT, 'results.json');
const num = r => String(r.check).slice(0, 2);
let results = [];
if (ONLY && fs.existsSync(RESULTS)) {
  try { results = JSON.parse(fs.readFileSync(RESULTS, 'utf8')).filter(r => !ONLY.includes(num(r))); } catch (e) { results = []; }
}
function record(r) {
  results = results.filter(x => num(x) !== num(r));
  results.push(r);
  results.sort((a, b) => num(a).localeCompare(num(b)));
  fs.writeFileSync(RESULTS, JSON.stringify(results, null, 1));
  console.log(`${r.pass ? 'PASS' : 'CHECK'}  ${r.check}  ${JSON.stringify({ ...r, check: undefined, pass: undefined })}`);
}

const browser = await chromium.launch({ channel: 'chrome', headless: true });

// The local box is slow, and PHP's 120 s limit answers with an empty 500 when it is busy
// (another browser session, or a cold cache after an Apache restart). Wait and try again.
async function goto(page, url) {
  for (let attempt = 1; ; attempt++) {
    try {
      return await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
    } catch (e) {
      if (attempt >= 4) throw e;
      await page.waitForTimeout(30000);
    }
  }
}

async function session(persona) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  page.setDefaultTimeout(240000);
  await goto(page, '/login/index.php');
  await page.fill('#username', persona);
  await page.fill('#password', creds[persona].password);
  await page.click('#loginbtn', { noWaitAfter: true });
  await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
  return { context, page };
}

// Log in as a persona, run fn(page), and never let one persona's failure stop the others.
async function asPersona(ids, persona, fn) {
  if (!ids.some(want)) return;
  let context = null;
  try {
    const s = await session(persona);
    context = s.context;
    await fn(s.page);
  } catch (e) {
    for (const id of ids.filter(want)) {
      if (!results.some(r => num(r) === id && r.pass)) {
        record({ check: `${id} (${persona})`, pass: false, error: String(e).split('\n')[0].slice(0, 300) });
      }
    }
  } finally {
    if (context) await context.close();
  }
}

async function open(page, url) {
  await goto(page, url);
  await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
}

async function shots(page, name) {
  await page.screenshot({ path: path.join(OUT, `${name}-desktop.png`), fullPage: false });
  await page.setViewportSize({ width: 590, height: 1000 });
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, `${name}-mobile.png`), fullPage: false });
  await page.setViewportSize({ width: 1440, height: 900 });
}

// The alert the scan page shows: its heading and body text.
async function alertText(page) {
  return page.evaluate(() => {
    const a = document.querySelector('.alert h3');
    const box = a ? a.closest('.alert') : null;
    return {
      heading: a ? a.textContent.trim() : '',
      body: box ? [...box.querySelectorAll('p')].map(p => p.textContent.trim()).join(' | ') : '',
    };
  });
}

const scanUrl = (s, token) => `/local/sentientia_pages/qr_scan.php?sessionid=${s.id}&token=${token ?? s.token}`;

async function scan(page, name, label, url, expectHeading, expectBodyPart) {
  if (!want(name.slice(0, 2))) return;
  await open(page, url);
  const t = await alertText(page);
  await shots(page, name);
  record({
    check: label, heading: t.heading, body: t.body,
    pass: t.heading === expectHeading && (!expectBodyPart || t.body.includes(expectBodyPart)),
  });
}

// Learner in /1 (on the roster of the active classroom and the cancelled one).
await asPersona(['01', '02', '03', '04', '05'], 'vp_learner1', async page => {
  await scan(page, '01-scan-recorded', '01 learner on the roster, valid token: recorded',
    scanUrl(DATA.sessionA), 'Attendance Marked!', 'successfully recorded');
  await scan(page, '02-scan-already-marked', '02 same learner scans again: already marked, nothing written',
    scanUrl(DATA.sessionA), 'Already Marked', 'already been recorded');
  await scan(page, '03-scan-expired-token', '03 old or forged token: QR code expired',
    scanUrl(DATA.sessionA, DATA.expiredtoken), 'QR Code Expired', 'has expired');
  await scan(page, '04-scan-classroom-cancelled', '04 classroom is cancelled: refused, nothing written',
    scanUrl(DATA.sessionB), 'Classroom Cancelled', 'has been cancelled');
  await scan(page, '05-scan-session-not-found', '05 session id with no session (valid token for that id)',
    scanUrl(DATA.missing), 'Session Not Found', 'no longer exists');
});

// /1 user who is not on the roster.
await asPersona(['06'], 'vp_manager1', async page => {
  await scan(page, '06-scan-not-enrolled', '06 not on the roster: not enrolled, nothing written',
    scanUrl(DATA.sessionA), 'Not Enrolled', 'not enrolled in this classroom');
});

// Learner in /177 scanning a /1 classroom (on its roster, but another organisation).
await asPersona(['07'], 'vp_learner177', async page => {
  await scan(page, '07-scan-other-organisation', '07 learner from another tenant: different-organisation error',
    scanUrl(DATA.sessionA), 'Error', 'different organisation');
});

// Site admin: the trainer's QR page, then three profiles.
await asPersona(['08', '09', '10', '11'], 'vp_siteadmin', async page => {
  if (want('08')) {
    await open(page, `/local/sentientia_pages/qr_attendance.php?sessionid=${DATA.sessionA.id}`);
    const qr = await page.evaluate(() => {
      const img = document.querySelector('img.airpay-qr__code');
      const s = document.querySelector('.airpay-qr__session');
      return { session: s ? s.textContent.trim() : '', qrRendered: !!(img && img.complete && img.naturalWidth > 0) };
    });
    await shots(page, '08-qr-attendance-heading');
    record({
      check: '08 trainer QR page: heading is "classroom - session title" and the QR image renders',
      ...qr, pass: /^VP Evidence QR classroom - Day 1/.test(qr.session) && qr.qrRendered,
    });
  }

  const loginasInfo = async (id, name, label, expectLink) => {
    if (!want(name.slice(0, 2))) return;
    await open(page, `/local/sentientia_users/profile.php?id=${id}`);
    const info = await page.evaluate(() => ({
      loginas: [...document.querySelectorAll('a')].some(a => /loginas\.php/.test(a.getAttribute('href') || '')),
      profileShown: !!document.querySelector('h1, h2'),
    }));
    await shots(page, name);
    record({ check: label, loginas: info.loginas, profileShown: info.profileShown,
      pass: info.loginas === expectLink && info.profileShown });
  };
  await loginasInfo(DATA.users.learner1, '09-profile-loginas-shown-for-learner',
    '09 site admin viewing a learner: "Log in as" is offered', true);
  await loginasInfo(DATA.users.siteadmin2, '10-profile-loginas-hidden-for-site-admin',
    '10 site admin viewing another site admin: no "Log in as"', false);
  await loginasInfo(DATA.users.suspended, '11-profile-loginas-hidden-for-suspended',
    '11 site admin viewing a suspended user: no "Log in as"', false);
});

await browser.close();
const failed = results.filter(r => !r.pass).length;
console.log(`${results.length} check(s) recorded, ${failed} not passing.`);
process.exit(failed ? 1 : 0);
