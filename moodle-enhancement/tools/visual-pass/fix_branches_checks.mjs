// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: the screenshots owed by the six fix branches of 2026-10-15 (gst-invoices, eval-followups, ui-fix-emails,
// ui-fix-theme, ui-fix-request, ui-fix-skills-org), taken on the LOCAL XAMPP site (http://localhost:8080) where all six
// are deployed together. Same safety pattern as the other evidence scripts (see evidence_lib.mjs): the base URL is
// fixed, every request that is not localhost is aborted in the browser, passwords are read from the gitignored
// .personas.local.json and never printed, dialogs are opened and CANCELLED (nothing is saved, refunded, approved or
// requested unless a section says so in its own comment).
//
// The local copy holds real people. Before every screenshot the names and e-mail addresses of every account that is
// not a test persona (vp_*, qa_*) are replaced in the DOM (never in the database) by "[name hidden]" / "[email hidden]".
// The list comes from a scratch file written by a read-only PHP probe (--realnames, default
// D:/Claude Local/kit-scratch/shots2/realnames.json); results.json records only how many were masked, never the text.
//
//   node fix_branches_checks.mjs --only cart,emails,theme,request,skills [--out <dir>] [--realnames <file>]
//
// Each section writes into <out>/<section>/ (default D:/Claude Local/kit-scratch/shots2/out) and a results.json there.
// The evaluation shots come from eval_checks.mjs (with gst_evidence_flags.php on/off evaluation 77). Per section:
//   cart, emails, theme, skills  no seed, no flag (the data is already on the local copy)
//   request  php fix_request_seed.php (one routed request), then php fix_request_flags.php save + apply before the run;
//            the section runs restore itself (run restore again afterwards if the run died: it is idempotent)

import fs from 'node:fs';
import path from 'node:path';
import { BASE, HERE, go, has, launch, opt, readJson, scan, session, shots, sleep } from './evidence_lib.mjs';

const OUT = path.resolve(opt('--out', 'D:/Claude Local/kit-scratch/shots2/out'));
const ONLY = opt('--only', '').split(',').map(s => s.trim()).filter(Boolean);
const PF = opt('--personas', '').split(',').map(s => s.trim()).filter(Boolean);
const P = name => !PF.length || PF.includes(name);
const REAL = readJson(opt('--realnames', 'D:/Claude Local/kit-scratch/shots2/realnames.json')) || { names: [], emails: [] };
// A "name" that is really the product or a role ("Airpay Academy", "Site Administrator") is not a person: masking it would
// blank the logo text and the footer. Only person-like names stay in the list.
REAL.names = REAL.names.filter(n => !/airpay|academy|sentientia|moodle|support|helpdesk|admin|learning|test|demo|team|manager|trainer|learner/i.test(n));
const CTX = readJson(path.join(HERE, '.fix-branches-evidence.local.json')) || {};
if (!REAL.names.length) console.error('WARNING: no real-name list: masking is a no-op, do not commit these shots');

const stamp = () => new Date().toTimeString().slice(0, 8);
const log = m => console.error(`[${stamp()}] ${m}`);

// ----------------------------------------------------------------------------------------------------------------- masking
// Replace, in text nodes and common attributes, every real person's full name and e-mail address. Returns the count.
async function maskReal(page) {
  return page.evaluate(({ names, emails }) => {
    const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const parts = [...emails, ...names].filter(s => s && s.length >= 5).sort((a, b) => b.length - a.length).map(esc);
    if (!parts.length) return 0;
    const re = new RegExp('(?<![\\w])(?:' + parts.join('|') + ')(?![\\w])', 'gi');
    let n = 0;
    const sub = t => t.replace(re, m => { n++; return m.includes('@') ? '[email hidden]' : '[name hidden]'; });
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
      const v = node.nodeValue || '';
      if (v.length >= 5) { const r = sub(v); if (r !== v) node.nodeValue = r; }
    }
    for (const el of document.querySelectorAll('[title],[aria-label],[alt],[placeholder],[data-name],[data-fullname],input,textarea')) {
      for (const a of ['title', 'aria-label', 'alt', 'placeholder', 'data-name', 'data-fullname']) {
        const v = el.getAttribute(a);
        if (v && v.length >= 5) { const r = sub(v); if (r !== v) el.setAttribute(a, r); }
      }
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.type !== 'password' && el.type !== 'hidden' && el.value && el.value.length >= 5) {
        const r = sub(el.value); if (r !== el.value) el.value = r;
      }
    }
    return n;
  }, REAL).catch(() => 0);
}

// ----------------------------------------------------------------------------------------------------------------- results
class Section {
  constructor(name) {
    this.name = name;
    this.dir = path.join(OUT, name);
    fs.mkdirSync(this.dir, { recursive: true });
    this.file = path.join(this.dir, 'results.json');
    this.results = (readJson(this.file) || {}).results || [];
  }
  flush() {
    fs.writeFileSync(this.file, JSON.stringify({ generated: new Date().toISOString(), base: BASE, results: this.results }, null, 1));
  }
  add(rec) {
    const i = this.results.findIndex(r => r.name === rec.name);
    if (i >= 0) this.results[i] = rec; else this.results.push(rec);
    this.flush();
    log(`${this.name}/${rec.name}: ${rec.http ?? '-'} ${rec.problems && rec.problems.length ? 'PROBLEMS ' + rec.problems.join('; ') : ''}${rec.note ? ' | ' + rec.note : ''}`);
  }
}

// Open a URL, optionally wait for a selector, mask, scan, shoot desktop + 590 px. `after` (async, optional) may
// inspect the page and return extra fields for the record. Returns { rec, scan }.
async function view(sec, page, name, url, persona, shows, { wait = null, mobile = true, after = null, prepare = null, minHeight = 900, maxHeight = 3400, settle = 1500 } = {}) {
  const res = url ? await go(page, url) : null;
  if (wait) await page.waitForSelector(wait, { timeout: 120000 }).catch(() => {});
  await page.waitForTimeout(settle);
  let masked = await maskReal(page);
  const extra = after ? await after(page) : {};
  const s = await scan(page);
  const files = await shots(page, sec.dir, name, {
    mobile, minHeight, maxHeight,
    prepare: async label => { masked += await maskReal(page); if (prepare) await prepare(label, page); },
  });
  const rec = { name, url, persona, shows, http: res ? res.status() : null, files: [...files], overflow: files.overflow,
    problems: s.problems, console: page.errs.splice(0), masked, ...extra };
  sec.add(rec);
  return { rec, scan: s };
}

// Click a locator that opens a dialog, shoot the dialog (viewport) at 1440 and 590, leave it open. Returns the record fields.
async function dialogShot(sec, page, name, click, { modalSel = '.modal.show', after = null } = {}) {
  await click.first().scrollIntoViewIfNeeded().catch(() => {});
  await click.first().click({ timeout: 30000 }).catch(() => click.first().evaluate(e => e.click()));
  const modal = page.locator(modalSel).first();
  await modal.waitFor({ state: 'visible', timeout: 90000 });
  await page.waitForFunction(() => {
    const m = document.querySelector('.modal.show');
    const t = m && m.querySelector('.modal-title');
    return t && t.textContent.trim().length > 0 && !m.querySelector('.modal-body [data-region="loading-icon"], .modal-body .loading-icon');
  }, null, { timeout: 90000, polling: 1000 }).catch(() => {});
  await page.waitForTimeout(1200);
  let masked = await maskReal(page);
  const extra = after ? await after(page) : {};
  const files = [];
  for (const [label, w, h] of [['desktop', 1440, 900], ['mobile', 590, 1000]]) {
    await page.setViewportSize({ width: w, height: h });
    await page.waitForTimeout(800);
    masked += await maskReal(page);
    const file = `${name}-${label}.png`;
    await page.screenshot({ path: path.join(sec.dir, file) });
    files.push(file);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  const text = await modal.innerText().catch(() => '');
  return { files, masked, modalText: text.replace(/\s+/g, ' ').slice(0, 500), ...extra };
}

async function cancelDialog(page) {
  const cancel = page.locator('.modal.show [data-action="cancel"], .modal.show button:has-text("Cancel")').first();
  if (await cancel.count()) await cancel.click({ timeout: 30000 }).catch(() => {});
  else await page.keyboard.press('Escape');
  await page.waitForTimeout(1000);
  await page.locator('.modal.show').first().waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
}

// The cells of a datatable, as text (after masking).
// Wait until a datatable has real rows (its first row is not the "Loading" placeholder) or says there are none.
const rowsReady = page => page.waitForFunction(() => {
  const r = [...document.querySelectorAll('[data-region="airpay-datatable"] table tbody tr')];
  return r.length > 0 && !/^\s*loading/i.test(r[0].innerText || '');
}, null, { timeout: 240000, polling: 1000 }).catch(() => {});

// rowsReady, and when the table says "Failed to load data" (a request the slow local box timed out at 120 s) reload and wait again.
async function ensureRows(page, tries = 4) {
  for (let i = 0; i < tries; i++) {
    await rowsReady(page);
    const t = await page.locator('[data-region="airpay-datatable"]').first().innerText().catch(() => '');
    if (!/Failed to load data/i.test(t)) return true;
    log('table failed to load, reloading');
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 240000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  }
  return false;
}

const tableRows = page => page.evaluate(() => [...document.querySelectorAll('table tbody tr')]
  .map(tr => [...tr.children].map(c => (c.innerText || '').replace(/\s+/g, ' ').trim())));
const tableHeads = page => page.evaluate(() => [...document.querySelectorAll('table thead th')]
  .map(th => (th.innerText || '').replace(/\s+/g, ' ').trim()));


// At 590 px a wide datatable scrolls inside its own box. Shoot the viewport with that box scrolled to its right end, to show the
// columns that are off-screen (a button in the last column is reachable, not missing). Returns the record fields.
async function scrolledMobile(sec, page, name, persona, shows, { selector = '[data-region="airpay-datatable"]' } = {}) {
  await page.setViewportSize({ width: 590, height: 1000 });
  await page.waitForTimeout(800);
  const info = await page.evaluate(sel => {
    const root = document.querySelector(sel);
    if (!root) return null;
    let box = null;
    for (const el of [root, ...root.querySelectorAll('*')]) {
      const cs = getComputedStyle(el);
      if (/(auto|scroll)/.test(cs.overflowX) && el.scrollWidth > el.clientWidth + 5) { box = el; break; }
    }
    if (!box) return { scrolled: false };
    box.scrollLeft = box.scrollWidth;
    box.scrollIntoView({ block: 'start' });
    return { scrolled: true, scrollWidth: box.scrollWidth, clientWidth: box.clientWidth };
  }, selector);
  await page.waitForTimeout(600);
  await maskReal(page);
  const file = `${name}-mobile.png`;
  await page.screenshot({ path: path.join(sec.dir, file) });
  await page.setViewportSize({ width: 1440, height: 900 });
  sec.add({ name, persona, shows, files: [file], scroll: info });
}

// ======================================================================================================================
// cart (branch claude/gst-invoices, UI pass of 2026-10-15: cart 1.2.1)
//   Orders are the ones already in the local database (the GST pass of 2026-10-14 and the persona pass): paid,
//   partial_refund, refunded and failed rows. No flag is needed: the Refund button, the Placed date, the status in words
//   and the symbol do not sit behind a flag. Nothing is refunded: the dialog is opened and cancelled.
// ======================================================================================================================
async function cart(browser) {
  const sec = new Section('cart');
  const ADMIN = '/local/sentientia_cart/admin_orders.php';
  const HIST = '/local/sentientia_cart/history.php';
  const rowsLoaded = '[data-region="airpay-datatable"] table tbody tr';

  const tableInfo = async page => {
    await ensureRows(page);
    await maskReal(page);
    const heads = await tableHeads(page);
    const rows = await tableRows(page);
    const buttons = await page.locator('[data-action="refund-order"]').count();
    return { heads, rows, refundButtons: buttons };
  };

  // 1-2: site admin, the all-orders page and the Refund dialog of a partial_refund row.
  if (P('vp_siteadmin')) {
    const { context, page } = await session(browser, 'vp_siteadmin');
    await view(sec, page, '01-siteadmin-admin-orders', ADMIN, 'vp_siteadmin (site admin, holds :viewallorders and :refund)',
      'All orders: an Actions column; a Refund button only on the paid and partial refund rows (not on refunded or failed); Placed is the payment date; Status in words; totals with the rupee symbol',
      { wait: rowsLoaded, after: tableInfo });
    await scrolledMobile(sec, page, '01b-siteadmin-admin-orders-scrolled', 'vp_siteadmin',
      'The same page at 590 px with the table scrolled to its right end: Status, Staff notes and the Refund buttons are in the columns that sit off-screen until the table is scrolled');
    const row = page.locator('table tbody tr', { has: page.locator('[data-action="refund-order"]') }).first();
    if (await row.count()) {
      const d = await dialogShot(sec, page, '02-siteadmin-refund-dialog', row.locator('[data-action="refund-order"]'));
      sec.add({ name: '02-siteadmin-refund-dialog', persona: 'vp_siteadmin', shows: 'The Refund dialog opened from a row button (cancelled, nothing refunded)', ...d });
      await cancelDialog(page);
    } else {
      sec.add({ name: '02-siteadmin-refund-dialog', note: 'NOT CAPTURED: no Refund button on the page' });
    }
    // 3: the buyer's order history, opened by the SAME site admin: no Refund button, no extra headerless cell.
    await view(sec, page, '03-siteadmin-order-history-no-refund', HIST, 'vp_siteadmin (site admin)',
      'The order history page (history.php) opened by a site admin: Placed, Total and Status as on the admin page, and NO Refund button and no extra empty column',
      { wait: rowsLoaded, after: tableInfo });
    await context.close();
  }

  // 4-5: tenant admin /1: the paid order carries the Refund button, the failed one does not.
  if (P('vp_admin1')) {
    const { context, page } = await session(browser, 'vp_admin1');
    await view(sec, page, '04-admin1-admin-orders', ADMIN, 'vp_admin1 (tenant admin /1, holds :viewallorders and :refund)',
      'The tenant admin\'s orders (tenant /1 only): the paid order has the Refund button, the failed order has none',
      { wait: rowsLoaded, after: tableInfo });
    const btn = page.locator('[data-action="refund-order"]');
    if (await btn.count()) {
      const d = await dialogShot(sec, page, '05-admin1-refund-dialog-paid-order', btn);
      sec.add({ name: '05-admin1-refund-dialog-paid-order', persona: 'vp_admin1', shows: 'The Refund dialog of the paid order (cancelled)', ...d });
      await cancelDialog(page);
    }
    await context.close();
  }

  // 6: the buyer (a /77 learner) and the /1 learner: their own history.
  if (P('vp_public77')) {
    const { context, page } = await session(browser, 'vp_public77');
    await view(sec, page, '06-public77-order-history', HIST, 'vp_public77 (buyer, /77, no :viewallorders)',
      'The buyer\'s own history: three partial refund orders, Placed is the payment date, Status "Partial refund", totals with the rupee symbol, no Refund button',
      { wait: rowsLoaded, after: tableInfo });
    await context.close();
  }
  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    await view(sec, page, '07-learner1-order-history', HIST, 'vp_learner1 (buyer, /1)',
      'A paid and a failed order in words (Paid, Failed) with the rupee symbol, no Refund button',
      { wait: rowsLoaded, after: tableInfo });
    await context.close();
  }
}

// ======================================================================================================================
// emails (branch claude/ui-fix-emails, emails 2026101501)
//   1. The template editor's live preview renders on first load. editor.mustache wrote the iframe srcdoc with triple
//      braces, so the first double quote of the preview HTML (the XHTML doctype's PUBLIC "...") ended the attribute:
//      about 22 characters reached the frame. Now s() escaped: the whole document arrives.
//   2. .ap-studio lost its -1rem side margins, so the editor no longer runs 16 px past the viewport.
//   Read-only: the editor is opened, nothing is saved or reverted, no test mail is sent.
// ======================================================================================================================
async function emails(browser) {
  const sec = new Section('emails');
  const KEY = 'compliance/welcome_enrolled';
  const URL = `/local/sentientia_emails/editor.php?template=${encodeURIComponent(KEY)}`;
  const measure = async page => {
    const iframe = await page.evaluate(() => {
      const f = document.querySelector('.ap-studio iframe[srcdoc], iframe[srcdoc]');
      const studio = document.querySelector('.ap-studio');
      const r = studio ? studio.getBoundingClientRect() : null;
      return {
        srcdocLength: f ? (f.getAttribute('srcdoc') || '').length : -1,
        srcdocHasDoctypeQuote: f ? /PUBLIC\s+"/.test(f.getAttribute('srcdoc') || '') : false,
        sandbox: f ? f.getAttribute('sandbox') : null,
        studioLeft: r ? Math.round(r.left) : null, studioRight: r ? Math.round(r.right) : null,
        viewport: document.documentElement.clientWidth, pageScrollWidth: document.documentElement.scrollWidth,
      };
    });
    const frame = page.frames().find(fr => fr !== page.mainFrame() && fr.url() === 'about:srcdoc');
    const frameText = frame ? await frame.evaluate(() => (document.body ? document.body.innerText : '').replace(/\s+/g, ' ').trim()).catch(() => '') : '';
    return { ...iframe, previewTextLength: frameText.length, previewTextStart: frameText.slice(0, 120) };
  };
  if (P('vp_siteadmin')) {
    const { context, page } = await session(browser, 'vp_siteadmin');
    await view(sec, page, '01-siteadmin-template-editor-first-load', URL, 'vp_siteadmin (site admin, cross-tenant)',
      'The template editor on first load: the live preview shows the whole e-mail (the srcdoc carries the full document, doctype quotes included); the editor fits the viewport with no horizontal scroll',
      { wait: '.ap-studio', settle: 4000, after: measure });
    // The same measurements at 590 px, recorded separately (the shot above already has the 590 px image).
    await page.setViewportSize({ width: 590, height: 1000 });
    await page.waitForTimeout(1500);
    sec.add({ name: '01-siteadmin-template-editor-first-load-590-measure', persona: 'vp_siteadmin', shows: 'measurements at 590 px', ...(await measure(page)) });
    await page.setViewportSize({ width: 1440, height: 900 });
    await context.close();
  }
}

// ======================================================================================================================
// theme (branch claude/ui-fix-theme, theme_sentientia 2026101504)
//   1. The edit-mode switch is the theme's Bootstrap 4 custom-switch, not a bare browser checkbox.
//   2. A course author (editingteacher) keeps edit mode across a page load, the course content renders with its
//      editing controls, and the course editor runs without the two console errors it threw.
//   3. Dropdowns that carry only data-bs-toggle (core 5.x markup) open in this Bootstrap 4.6 theme, once per click.
//   Edit mode is per session (it ends with the browser context); the activity chooser is opened and closed, nothing is
//   added. No flag is involved.
// ======================================================================================================================
async function theme(browser) {
  const sec = new Section('theme');
  const COURSE = '/course/view.php?id=448';   // vp_journey_free, vp_courseauthor1 is its editingteacher
  const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';
  const editState = async page => page.evaluate(sel => {
    const cb = document.querySelector(sel);
    const label = cb ? document.querySelector(`label[for="${cb.id}"]`) : null;
    const before = label ? getComputedStyle(label, '::before') : null;
    return {
      bodyEditing: document.body.classList.contains('editing'),
      switchChecked: cb ? cb.checked : null,
      switchIsCustom: !!(cb && cb.closest('.custom-control.custom-switch') && cb.classList.contains('custom-control-input')),
      switchTrackWidth: before ? before.width : null,
      chooserButtons: document.querySelectorAll('[data-action="open-chooser"]').length,
      sectionEditMenus: document.querySelectorAll('.section [data-region="section-actionmenu"], .course-section .action-menu').length,
      bulkEditButton: document.querySelectorAll('[data-action="toggleBulkEdit"], #bulkedit, button[data-action*="bulk"]').length,
    };
  }, SWITCH);
  const dropdownClicks = async (page, toggleSel) => {
    const t = page.locator(toggleSel).first();
    if (!(await t.count())) return { found: false };
    await t.scrollIntoViewIfNeeded().catch(() => {});
    const open = async () => page.evaluate(sel => {
      const el = document.querySelector(sel);
      const menu = el && (el.parentElement.querySelector('.dropdown-menu') || document.querySelector(`[aria-labelledby="${el.id}"]`));
      return { expanded: el ? el.getAttribute('aria-expanded') : null, menuShown: !!(menu && menu.classList.contains('show')) };
    }, toggleSel);
    const initial = await open();
    await t.click(); await page.waitForTimeout(700);
    const afterFirst = await open();
    return { found: true, initial, afterFirst };
  };

  if (P('vp_courseauthor1')) {
    const { context, page } = await session(browser, 'vp_courseauthor1');
    // Leave edit mode off for the first shot whatever the session remembers.
    await go(page, COURSE);
    if (await page.locator(SWITCH).first().isChecked().catch(() => false)) {
      await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), page.locator(SWITCH).first().click()]);
    }
    await view(sec, page, '01-courseauthor-course-edit-switch-off', COURSE, 'vp_courseauthor1 (editingteacher of the test course vp_journey_free)',
      'The course page with edit mode off: the edit switch is the theme\'s styled toggle (Bootstrap 4 custom-switch), not a bare checkbox',
      { wait: SWITCH, after: editState });
    // Turn edit mode on with the switch (core/edit_switch posts and reloads).
    await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), page.locator(SWITCH).first().click()]);
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await view(sec, page, '02-courseauthor-course-edit-mode-on', null, 'vp_courseauthor1',
      'Edit mode on: the switch is on, the course content renders with its editing controls (add an activity, section menus), no console error',
      { wait: SWITCH, settle: 3000, after: editState });
    // Reload: edit mode must still be on.
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 240000 }).catch(() => {});
    await view(sec, page, '03-courseauthor-course-after-reload-still-editing', null, 'vp_courseauthor1',
      'The same page after a reload: still in edit mode, editing controls still there (before the fix every load came back in viewing mode)',
      { wait: SWITCH, settle: 3000, after: editState });
    // The activity chooser opens (then closed, nothing added).
    const chooser = page.locator('[data-action="open-chooser"]');
    if (await chooser.count()) {
      const d = await dialogShot(sec, page, '04-courseauthor-activity-chooser', chooser);
      sec.add({ name: '04-courseauthor-activity-chooser', persona: 'vp_courseauthor1', shows: 'Add an activity or resource opens the chooser (closed again, nothing added)', ...d, console: page.errs.splice(0) });
      await page.keyboard.press('Escape'); await page.waitForTimeout(800);
    } else {
      sec.add({ name: '04-courseauthor-activity-chooser', note: 'NOT CAPTURED: no open-chooser button in edit mode' });
    }
    // Leave edit mode off again.
    await go(page, COURSE);
    if (await page.locator(SWITCH).first().isChecked().catch(() => false)) {
      await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), page.locator(SWITCH).first().click()]);
    }
    await context.close();
  }

  // A data-bs-toggle-only dropdown: the My courses sort selector (theme override of block_myoverview nav-sort-selector).
  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    await go(page, '/my/courses.php');
    await page.waitForSelector('#sortingdropdown', { timeout: 120000 }).catch(() => {});
    await page.waitForTimeout(1500);
    const clicks = await dropdownClicks(page, '#sortingdropdown');
    await view(sec, page, '05-learner-mycourses-sort-dropdown-open', null, 'vp_learner1',
      'My courses: the sort selector (Bootstrap 5 markup, data-bs-toggle only) opened by ONE click in this Bootstrap 4.6 theme',
      { settle: 500, mobile: false, after: async () => ({ clicks }) });
    const t = page.locator('#sortingdropdown').first();
    await t.click().catch(() => {}); await page.waitForTimeout(700);
    const closed = await page.evaluate(() => { const el = document.querySelector('#sortingdropdown'); return { expanded: el && el.getAttribute('aria-expanded') }; });
    sec.add({ name: '05b-learner-mycourses-sort-dropdown-second-click', persona: 'vp_learner1', shows: 'A second click closes it', closed });
    await context.close();
  }

  // A core action menu (data-bs-toggle only): the certificate templates row menu.
  if (P('vp_siteadmin')) {
    const { context, page } = await session(browser, 'vp_siteadmin');
    await go(page, '/admin/tool/certificate/manage_templates.php');
    const sel = 'table tbody tr .action-menu [data-bs-toggle="dropdown"], table tbody tr [data-bs-toggle="dropdown"]';
    await page.waitForSelector(sel, { timeout: 240000 }).catch(() => {});
    await page.waitForTimeout(1500);
    const first = page.locator(sel).first();
    let clicks = { found: false };
    if (await first.count()) {
      await first.scrollIntoViewIfNeeded().catch(() => {});
      const st = () => first.evaluate(el => {
        const menu = el.parentElement.querySelector('.dropdown-menu');
        return { expanded: el.getAttribute('aria-expanded'), menuShown: !!(menu && menu.classList.contains('show')) };
      });
      const initial = await st();
      await first.click(); await page.waitForTimeout(700);
      clicks = { found: true, initial, afterFirst: await st() };
    }
    await view(sec, page, '06-siteadmin-certificate-templates-row-menu-open', null, 'vp_siteadmin',
      'Certificate templates: a core row action menu (data-bs-toggle only) opened by ONE click',
      { settle: 500, mobile: false, after: async () => ({ clicks }) });
    if (clicks.found) {
      await first.click().catch(() => {}); await page.waitForTimeout(700);
      const closed = await first.evaluate(el => ({ expanded: el.getAttribute('aria-expanded') })).catch(() => null);
      sec.add({ name: '06b-siteadmin-row-menu-second-click', persona: 'vp_siteadmin', shows: 'A second click closes it', closed });
    }
    await context.close();
  }
}

// ======================================================================================================================
// request (branch claude/ui-fix-request, request 2026101504, catalog 2026101502)
//   7(a) behind sentientia.request.catalog_button (default OFF): the catalogue offers "Request enrolment" where a request
//        is the learner's way in (a course with only manual enrolment, and a free course with open self-enrolment,
//        which the theme makes unreachable). While a request is pending the card says so.
//   7(b) no flag: the line manager (an ordinary employee) holds :approve and can act on the request routed to them.
//   The flag must be ON for tenant 1 before this section runs (gst_evidence_flags.php on request 1); the section turns
//   it OFF itself for shot 12 and leaves it OFF. ONE request is submitted (vp_learner1, course 11) and left pending.
// ======================================================================================================================
async function request(browser) {
  const sec = new Section('request');
  const { execFileSync } = await import('node:child_process');
  const flags = cmd => execFileSync('C:/xampp/php/php.exe', [path.join(HERE, 'fix_request_flags.php'), cmd],
    { cwd: 'C:/xampp/htdocs/moodle5/public', encoding: 'utf8' }).trim().split('\n').pop();
  const REQ = 13;   // A0008: free, manual enrolment only, in tenant 1's catalogue, vp_learner1 not enrolled
  const SELF = 5;   // A0001: free, manual + open self-enrolment
  const CAT = q => `/local/sentientia_catalog/index.php?q=${encodeURIComponent(q)}`;
  const DETAIL = id => `/local/sentientia_catalog/course.php?id=${id}`;
  const card = id => `[data-action="request-course"][data-courseid="${id}"]`;
  const cardState = id => async page => page.evaluate(cid => ({
    requestButtons: document.querySelectorAll(`[data-action="request-course"][data-courseid="${cid}"]`).length,
    pendingChips: document.querySelectorAll('.airpay-catalog__btn--pending').length,
    buttonText: (document.querySelector(`[data-action="request-course"][data-courseid="${cid}"]`) || {}).innerText || null,
  }), id);
  const dark = async (page, on) => { await page.evaluate(v => localStorage.setItem('airpay-theme', v), on ? 'dark' : 'light'); };

  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    await view(sec, page, '07-learner-card-request-button', CAT('A0008'), 'vp_learner1 (learner /1; for this run, tenant 1: request button ON, one-click enrolment OFF)',
      'A catalogue card for a course with only manual enrolment: "Request enrolment" where the card used to lead nowhere',
      { wait: card(REQ), after: cardState(REQ) });
    await view(sec, page, '08-learner-card-selfenrol-course-request', CAT('A0001'), 'vp_learner1',
      'A free course with an enabled (open) self-enrolment instance also offers "Request enrolment" (self-enrolment is unreachable on this theme)',
      { wait: card(SELF), after: cardState(SELF) });
    await view(sec, page, '11-learner-course-page-request', DETAIL(REQ), 'vp_learner1',
      'The same course on the catalogue course page: the Request enrolment button',
      { wait: card(REQ), after: cardState(REQ) });
    // Dark mode of shot 7 (the card), then back to light.
    await dark(page, true);
    await view(sec, page, '13a-learner-card-request-button-dark', CAT('A0008'), 'vp_learner1 (dark mode)',
      'Shot 7 in dark mode', { wait: card(REQ), after: cardState(REQ) });
    await dark(page, false);
    // 9: the dialog, then submit ONE request (local, noemailever).
    await go(page, CAT('A0008'));
    await page.waitForSelector(card(REQ), { timeout: 120000 });
    const d = await dialogShot(sec, page, '09-learner-request-dialog', page.locator(card(REQ)));
    sec.add({ name: '09-learner-request-dialog', persona: 'vp_learner1', shows: 'The Request enrolment dialog: a reason field, Save and Cancel', ...d });
    await page.locator('.modal.show #request_reason').fill('Local visual-evidence test request (2026-10-19); safe to reject.');
    await page.locator('.modal.show [data-action="save"]').first().click();
    await page.waitForTimeout(4000);
    await view(sec, page, '10-learner-card-request-pending', CAT('A0008'), 'vp_learner1',
      'After a valid request, the catalogue reloaded: the card shows "Request pending" instead of the button',
      { settle: 2500, after: cardState(REQ) });
    await dark(page, true);
    await view(sec, page, '13b-learner-card-request-pending-dark', CAT('A0008'), 'vp_learner1 (dark mode)',
      'Shot 10 in dark mode (the pending chip uses --ap-color-bg-surface-alt)', { settle: 2500, after: cardState(REQ) });
    await dark(page, false);
    await view(sec, page, '10b-learner-course-page-request-pending', DETAIL(REQ), 'vp_learner1',
      'The course page while the request is pending', { settle: 2500, after: cardState(REQ) });
    await context.close();
  }

  // 7(b): the request vp_learner1 sent for course 11 (fix_request_seed.php, request id 52) routed to vp_manager1.
  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    // 4 (finding 7b, the negative): an employee with no reports has no Pending approvals entry.
    await view(sec, page, '04-employee-no-approvals-entry-preferences', '/user/preferences.php', 'vp_learner1 (no reports)',
      'Preferences of an employee with no reports and nothing assigned: no "Pending approvals" link',
      { after: async p => ({ approvalsLinks: await p.locator('a[href*="/local/sentientia_request/approvals.php"]').count() }) });
    await view(sec, page, '04b-employee-my-requests-no-button', '/local/sentientia_request/index.php', 'vp_learner1',
      'My requests for the same employee: the pending request, and no Pending approvals button',
      { settle: 2500, after: async p => ({ approvalsLinks: await p.locator('a[href*="/local/sentientia_request/approvals.php"]').count() }) });
    // 5: notification preferences, every employee.
    await view(sec, page, '05-notification-prefs-request-rows', '/message/notificationpreferences.php', 'vp_learner1',
      'Notification preferences of an ordinary employee: the Request rows "Request needs your approval (approver)" and "Request escalated past SLA"',
      { settle: 2500, maxHeight: 6000, after: async p => ({ rows: await p.evaluate(() => [...document.querySelectorAll('[data-region="preference-component"] , .preference-row, tr')]
        .map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(t => /request/i.test(t)).slice(0, 8)) }) });
    await context.close();
  }

  if (P('vp_manager1')) {
    const { context, page } = await session(browser, 'vp_manager1');
    const links = async p => ({ approvalsLinks: await p.locator('a[href*="/local/sentientia_request/approvals.php"]').count(),
      linkText: await p.locator('a[href*="/local/sentientia_request/approvals.php"]').first().innerText().catch(() => null) });
    await view(sec, page, '01-linemanager-pending-approvals-entry', '/user/preferences.php', 'vp_manager1 (line manager of vp_learner1, no Moodle manager role)',
      'Preferences of the line manager: "Pending approvals" with the count', { after: links });
    await view(sec, page, '02-linemanager-approvals-page', '/local/sentientia_request/approvals.php', 'vp_manager1',
      'The approvals page opens (no "you do not have permission"): the learner\'s request with Approve and Reject (not pressed)',
      { settle: 3000, after: async p => ({ denied: /do not currently have permissions|nopermissions/i.test(await p.content()),
        approveButtons: await p.locator('button:has-text("Approve"), [data-action="approve"]').count() }) });
    await view(sec, page, '03-linemanager-my-requests-button', '/local/sentientia_request/index.php', 'vp_manager1',
      'My requests for the line manager: the Pending approvals button', { settle: 2500, after: links });
    await context.close();
  }

  // 12: flag OFF, the cards show what they showed before the fix.
  log('flags restored: ' + flags('restore'));
  await sleep(35000);
  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    await view(sec, page, '12-learner-card-flag-off', CAT('A0001'), 'vp_learner1 (flag OFF)',
      'Flags restored (request button OFF, one-click ON as before): the card offers Enroll again', { settle: 2500, after: cardState(SELF) });
    await context.close();
  }
  log('flags after: ' + flags('status'));
}

// ======================================================================================================================
// skills (branch claude/ui-fix-skills-org: skills 2026101502, org 2026101502, users 2026101501)
//   (a) The self-rate dialog warns INSIDE the dialog when Save is pressed with no level (it used to raise a page
//       notification hidden behind the backdrop). (b) Skill, skill category and org names print "&" once, not
//       "&amp;". (c) The profile's Suggested next courses names are plain text (no gap course for the local personas:
//       left to the unit test, see README). Nothing is saved: the dialog is cancelled. No flag is involved.
// ======================================================================================================================
async function skills(browser) {
  const sec = new Section('skills-org');
  const amp = async page => page.evaluate(() => {
    const t = document.body.innerText;
    const names = (t.match(/[A-Z][A-Za-z]+ & [A-Z][A-Za-z]+/g) || []).slice(0, 8);
    const attrs = [...document.querySelectorAll('[data-name],[title],[aria-label]')]
      .filter(e => /&amp;/.test(e.getAttribute('data-name') || '') || /&amp;/.test(e.getAttribute('title') || '') || /&amp;/.test(e.getAttribute('aria-label') || '')).length;
    return { visibleAmpEntity: (t.match(/&amp;/g) || []).length, attrsWithAmpEntity: attrs, ampersandNames: [...new Set(names)] };
  });

  if (P('vp_learner1')) {
    const { context, page } = await session(browser, 'vp_learner1');
    await view(sec, page, '01-learner-my-skills', '/local/sentientia_skills/index.php', 'vp_learner1',
      'My Skills: category and skill names with an ampersand ("Compliance & Risk", "Data Privacy & GDPR") print it once', { settle: 3000, after: amp });
    await view(sec, page, '02-learner-skill-view', '/local/sentientia_skills/view.php?id=3', 'vp_learner1',
      'A skill whose name has an ampersand ("Data Privacy & GDPR"): the title and breadcrumb print it once', { wait: '[data-action="open-self-rate"]', settle: 2000, after: amp });
    const open = page.locator('[data-action="open-self-rate"]');
    if (await open.count()) {
      await open.first().click();
      await page.locator('.modal.show').first().waitFor({ state: 'visible', timeout: 60000 }).catch(() => {});
      await page.waitForTimeout(1200);
      // Save with no level picked.
      await page.locator('.modal.show [data-action="save"]').first().click().catch(() => {});
      // The warning is a language string fetched on demand: about 9 s on this slow box. Wait for it (60 s at most).
      await page.waitForFunction(() => {
        const r = document.querySelector('.modal.show [data-region="self-rate-feedback"]');
        return r && r.textContent.trim().length > 0;
      }, null, { timeout: 60000, polling: 1000 }).catch(() => {});
      await page.waitForTimeout(800);
      const warn = await page.evaluate(() => {
        const m = document.querySelector('.modal.show');
        const region = m && m.querySelector('[data-region="self-rate-feedback"]');
        const sel = m && m.querySelector('#airpay-self-rate-level');
        return { dialogStillOpen: !!m, warning: region ? region.textContent.trim() : null, selectInvalid: sel ? sel.classList.contains('is-invalid') : null,
          ariaInvalid: sel ? sel.getAttribute('aria-invalid') : null };
      });
      const files = [];
      for (const [label, w, h] of [['desktop', 1440, 900], ['mobile', 590, 1000]]) {
        await page.setViewportSize({ width: w, height: h }); await page.waitForTimeout(800);
        await maskReal(page);
        const file = `03-learner-self-rate-no-level-warning-${label}.png`;
        await page.screenshot({ path: path.join(sec.dir, file) }); files.push(file);
      }
      await page.setViewportSize({ width: 1440, height: 900 });
      sec.add({ name: '03-learner-self-rate-no-level-warning', persona: 'vp_learner1',
        shows: 'Save pressed with no level: the warning shows inside the dialog under the select, the select is marked invalid, the dialog stays open (then cancelled, nothing saved)',
        files, ...warn, console: page.errs.splice(0) });
      await cancelDialog(page);
    } else {
      sec.add({ name: '03-learner-self-rate-no-level-warning', note: 'NOT CAPTURED: no self-rate button on the skill page' });
    }
    await context.close();
  }

  if (P('vp_siteadmin')) {
    const { context, page } = await session(browser, 'vp_siteadmin');
    await view(sec, page, '04-siteadmin-skills-admin', '/local/sentientia_skills/admin.php', 'vp_siteadmin',
      'Skills administration: the skill and category names in the table print "&" once', { settle: 4000, after: async p => { await ensureRows(p); return amp(p); } });
    // The tenant cards are collapsed: open the Airpay tenant (/1), whose units include "Alliances & Partnerships".
    await go(page, '/local/sentientia_org/admin.php');
    const opener = page.locator('[data-toggle="collapse"][href^="#org-"]').first();
    await opener.waitFor({ state: 'visible', timeout: 120000 }).catch(() => {});
    await opener.click().catch(() => {});
    await page.waitForTimeout(2000);
    await view(sec, page, '05-siteadmin-org-tree', null, 'vp_siteadmin',
      'Organisation tree, the Airpay tenant opened: unit names such as "Alliances & Partnerships" and "Learning & Development" print "&" once, and the row menus carry the plain name',
      { settle: 1000, maxHeight: 6000, after: async p => ({ ...(await amp(p)),
        dataNamesWithAmpersand: await p.evaluate(() => [...document.querySelectorAll('[data-name]')].filter(e => / & /.test(e.getAttribute('data-name'))).length) }) });
    await context.close();
  }
}

// ======================================================================================================================
const SECTIONS = { cart, emails, theme, request, skills };
const want = ONLY.length ? ONLY : Object.keys(SECTIONS);
const browser = await launch();
try {
  for (const name of want) {
    if (!SECTIONS[name]) { console.error(`unknown section ${name}`); continue; }
    log(`section ${name}`);
    try {
      await SECTIONS[name](browser);
    } catch (err) {
      console.error(`section ${name} FAILED: ${String(err.stack || err).split('\n').slice(0, 4).join(' | ')}`);
    }
  }
} finally {
  await browser.close();
}
