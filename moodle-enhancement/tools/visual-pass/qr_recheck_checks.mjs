// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: re-check screens for the final review of the QR-attendance work (2026-09-30).
//   18  the Hindi "already marked" page uses "chihnit" (marked), not "darj" (recorded)
//   19  the trainer's grid Save keeps a QR mark that landed after the grid was loaded
//   21  attendance page, QR entry-point flag ON: "Show QR for this session" link
//   22  attendance page, flag OFF: no link, the page as it was
//   23  the grid writes only what the trainer touched: an untouched learner has no row and can still scan
//   24  a Save with nothing changed says so and writes nothing
//
// Uses only the throwaway vpqr_* accounts that seed_qr_recheck.php creates (never the vp_* personas).
//
//   node qr_recheck_checks.mjs --data <seed.json> [--out <dir>] [--only 18,19]
//
// --only 21 needs the flag sentientia.classroom.qr_attendance ON for tenant /1, --only 22 needs it OFF
// (the checks assert the state, so a wrong setting shows as a failing check, not a wrong screenshot).
// results.json in --out is merged: only the numbers run here are replaced.
//
// Environment:
//   PLAYWRIGHT_CORE_DIR  folder of the playwright-core package (when not installed next to this file)
//   PERSONAS_FILE        the credentials file seed_qr_recheck.php wrote (default: .personas.local.json
//                        next to this file). Passwords are read from it and never printed.

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

const RESULTS = path.join(OUT, 'results.json');
const num = r => String(r.check).slice(0, 2);
let results = [];
if (fs.existsSync(RESULTS)) {
  try { results = JSON.parse(fs.readFileSync(RESULTS, 'utf8')).filter(r => !ONLY || !ONLY.includes(num(r))); } catch (e) { results = []; }
}
function record(r) {
  results = results.filter(x => num(x) !== num(r));
  results.push(r);
  results.sort((a, b) => num(a).localeCompare(num(b)));
  fs.writeFileSync(RESULTS, JSON.stringify(results, null, 1));
  console.log(`${r.pass ? 'PASS' : 'CHECK'}  ${r.check}  ${JSON.stringify({ ...r, check: undefined, pass: undefined })}`);
}

const browser = await chromium.launch({ channel: 'chrome', headless: true });

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
  for (let attempt = 1; ; attempt++) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
    try {
      const page = await context.newPage();
      page.setDefaultTimeout(240000);
      await goto(page, '/login/index.php');
      await page.fill('#username', persona);
      await page.fill('#password', creds[persona].password);
      await page.click('#loginbtn', { noWaitAfter: true });
      await page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 });
      return { context, page };
    } catch (e) {
      await context.close();
      if (attempt >= 4) throw e;
      await new Promise(r => setTimeout(r, 45000));
    }
  }
}

async function asPersona(ids, persona, fn) {
  if (!ids.some(want)) return;
  let context = null;
  try {
    const s = await session(persona);
    context = s.context;
    await fn(s.page);
  } catch (e) {
    for (const id of ids.filter(want)) {
      record({ check: `${id} (${persona})`, pass: false, error: String(e).split('\n')[0].slice(0, 300) });
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

const scanUrl = s => `/local/sentientia_pages/qr_scan.php?sessionid=${s.id}&token=${s.token}`;
const gridUrl = s => `/local/sentientia_classroom/attendance.php?sessionid=${s.id}`;
const L1 = DATA.users.vpqr_learner1;
const L2 = DATA.users.vpqr_learner2;

// The learner the trainer already marked Absent, scanning in Hindi.
await asPersona(['18'], 'vpqr_learner1', async page => {
  await open(page, scanUrl(DATA.sessionA) + '&lang=hi');
  const t = await alertText(page);
  await shots(page, '18-scan-already-marked-hindi');
  record({
    check: '18 the trainer marked this learner Absent; the same scan in Hindi says "already marked" (chihnit), not "recorded" (darj)',
    heading: t.heading, body: t.body,
    pass: t.heading === 'पहले से चिह्नित है' && t.body.includes('पहले ही चिह्नित')
      && !t.body.includes('दर्ज') && !t.heading.includes('दर्ज'),
  });
});

// The trainer's grid, checked as the tenant admin /1.
const radio = (uid, status) => `tr[data-userid="${uid}"] input[type=radio][data-status="${status}"]`;
const checkedStatus = (page, uid) => page.evaluate(id => {
  const r = document.querySelector(`tr[data-userid="${id}"] input[type=radio]:checked`);
  return r ? parseInt(r.dataset.status, 10) : null;
}, uid);
const notice = page => page.evaluate(() => {
  const n = document.querySelector('#user-notifications .alert, [data-region="notifications"] .alert, .toast-message');
  return n ? n.textContent.replace(/\s+/g, ' ').trim() : '';
});
const linkState = page => page.evaluate(() => {
  const a = document.querySelector('[data-region="show-qr"]');
  return { present: !!a, href: a ? a.getAttribute('href') : '', text: a ? a.textContent.replace(/\s+/g, ' ').trim() : '' };
});

await asPersona(['21', '22'], 'vpqr_admin1', async page => {
  await open(page, gridUrl(DATA.sessionG));
  const link = await linkState(page);
  const hint = await page.evaluate(() => !!document.querySelector('[data-region="untouched-hint"]'));
  if (want('21')) {
    await shots(page, '21-attendance-show-qr-link-flag-on');
    record({
      check: '21 flag ON: the attendance page offers "Show QR for this session" for a user holding :attendance',
      link, hint, pass: link.present && /Show QR for this session/.test(link.text)
        && link.href.includes(`qr_attendance.php?sessionid=${DATA.sessionG.id}`) && hint,
    });
  }
  if (want('22')) {
    await shots(page, '22-attendance-no-qr-link-flag-off');
    record({
      check: '22 flag OFF (default): no QR link on the attendance page, everything else unchanged',
      link, hint, pass: !link.present && hint,
    });
  }
});

// 23 + 24: a Save writes only what the trainer touched.
if (want('23') || want('24')) {
  let trainerCtx = null;
  let learnerCtx = null;
  try {
    const t = await session('vpqr_admin1');
    trainerCtx = t.context;
    await open(t.page, gridUrl(DATA.sessionG));
    // Nothing touched yet: a Save says so.
    await t.page.click('[data-action="save-attendance"]');
    await t.page.waitForFunction(() => /Nothing to save/.test(document.body.innerText), null, { timeout: 120000 });
    const nothing = await notice(t.page);
    if (want('24')) {
      await shots(t.page, '24-save-with-nothing-changed');
      record({ check: '24 a Save with nothing changed says so and writes nothing', notification: nothing,
        pass: /Nothing to save/.test(nothing) });
    }
    // The trainer ticks learner1 Present and saves; learner2 is never touched.
    await t.page.click(radio(L1, 1));
    await t.page.click('[data-action="save-attendance"]');
    await t.page.waitForFunction(() => /1 attendance saved/.test(document.body.innerText), null, { timeout: 120000 });
    const saved = await notice(t.page);
    const l1 = await checkedStatus(t.page, L1);
    const l2 = await checkedStatus(t.page, L2);
    // Learner2 has no row, so their scan, inside the window, records Present.
    const l = await session('vpqr_learner2');
    learnerCtx = l.context;
    await open(l.page, scanUrl(DATA.sessionG));
    const scanHeading = (await alertText(l.page)).heading;
    await learnerCtx.close();
    learnerCtx = null;
    if (want('23')) {
      await shots(t.page, '23-untouched-learner-keeps-no-row');
      record({
        check: '23 the trainer marks learner1 only: learner2 is not written as Absent, and their scan is recorded afterwards',
        notification: saved, learner1Status: l1, learner2Status: l2, learner2ScanHeading: scanHeading,
        note: 'Rows in the database are listed by seed_qr_recheck.php --report (learner2 has one row, written by the scan).',
        pass: /1 attendance saved/.test(saved) && l1 === 1 && l2 === 0 && scanHeading === 'Attendance Marked!',
      });
    }
  } catch (e) {
    // A check that already passed in this run (24 comes before the scan that failed) keeps its result.
    for (const id of ['23', '24'].filter(want)) {
      if (!results.some(r => num(r) === id && r.pass && r.notification)) {
        record({ check: `${id} (grid save writes only touched rows)`, pass: false, error: String(e).split('\n')[0].slice(0, 300) });
      }
    }
  } finally {
    if (learnerCtx) await learnerCtx.close();
    if (trainerCtx) await trainerCtx.close();
  }
}

// 19: the trainer sets learner1 Absent on purpose from a grid loaded BEFORE learner1 scanned.
if (want('19')) {
  let trainerCtx = null;
  let learnerCtx = null;
  try {
    const t = await session('vpqr_admin1');
    trainerCtx = t.context;
    await open(t.page, gridUrl(DATA.sessionF));
    const before = await checkedStatus(t.page, L1);

    const l = await session('vpqr_learner1');
    learnerCtx = l.context;
    await open(l.page, scanUrl(DATA.sessionF));
    const scanHeading = (await alertText(l.page)).heading;
    await learnerCtx.close();
    learnerCtx = null;

    // Two seconds on, so this scan is older than the Save below (a scan in the same second as a Save
    // still counts as newer). Choosing Absent on the learner is the trainer touching that row; then Save.
    await t.page.waitForTimeout(2500);
    await t.page.click(radio(L1, 0));
    await t.page.click('[data-action="save-attendance"]');
    await t.page.waitForFunction(() => /marked by someone else/.test(document.body.innerText), null, { timeout: 120000 });
    const after = await checkedStatus(t.page, L1);
    const note = await notice(t.page);
    await shots(t.page, '19-grid-save-keeps-qr-mark');

    // The trainer has seen the scan now: choosing Absent again and saving writes it (new load time).
    await t.page.click(radio(L1, 1));
    await t.page.click(radio(L1, 0));
    await t.page.click('[data-action="save-attendance"]');
    await t.page.waitForFunction(() => /1 attendance saved/.test(document.body.innerText), null, { timeout: 120000 });
    const corrected = await checkedStatus(t.page, L1);
    record({
      check: '19 trainer sets Absent from a grid loaded before a QR scan: the scan stays and the grid says so; a second Save then writes the correction',
      scanHeading, presentBeforeSave: before === 1, presentAfterFirstSave: after === 1, notification: note,
      absentAfterSecondSave: corrected === 0,
      pass: scanHeading === 'Attendance Marked!' && before === 0 && after === 1
        && /marked by someone else/.test(note) && corrected === 0,
    });
  } catch (e) {
    record({ check: '19 (grid save versus scan)', pass: false, error: String(e).split('\n')[0].slice(0, 300) });
  } finally {
    if (learnerCtx) await learnerCtx.close();
    if (trainerCtx) await trainerCtx.close();
  }
}

await browser.close();
const failed = results.filter(r => !r.pass).length;
console.log(`${results.length} check(s) recorded, ${failed} not passing.`);
process.exit(failed ? 1 : 0);
