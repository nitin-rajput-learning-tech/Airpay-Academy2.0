// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: the Moodle 5.3 compatibility screenshots (docs/visual-evidence/2026-10-08/moodle53/README.md, items 1 to
// 19), taken on the LOCAL XAMPP site (Moodle 5.1.3+, where the dual-target changes already run). Same safety pattern
// as persona_journeys.mjs (see evidence_lib.mjs). Prerequisites: provision_local_personas.php,
// seed_journey_content.php, seed_moodle53_evidence.php (writes .moodle53-evidence.local.json).
//
//   node moodle53_checks.mjs [--only 1,2,4] [--persona learner,author,admin,siteadmin] [--out <dir>]
//
// Every item runs on its own and records "captured" or "not captured: reason" (with a debug screenshot when it can),
// so one failure never stops the rest. Dialogs are opened and CANCELLED: nothing is saved, approved, refunded, enrolled
// or applied (the Switchboard "Apply changes" and every Save are never clicked).

import fs from 'node:fs';
import path from 'node:path';
import { BASE, go as goBase, has, HERE, launch, opt, readJson, scan, session, shots, newLocalContext, sleep } from './evidence_lib.mjs';

const CTX = readJson(path.join(HERE, '.moodle53-evidence.local.json'));
if (!CTX) {
  console.error('.moodle53-evidence.local.json missing - run seed_moodle53_evidence.php first.');
  process.exit(2);
}
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-10-08/moodle53')));
const ONLY = opt('--only').split(',').map(s => s.trim()).filter(Boolean);
const PERSONAS = opt('--persona', 'learner,author,admin,siteadmin,public').split(',');
fs.mkdirSync(OUT, { recursive: true });
const RESULTS = path.join(OUT, 'results-local.json');
const results = readJson(RESULTS)?.results || [];
const flush = () => fs.writeFileSync(RESULTS, JSON.stringify({ generated: new Date().toISOString(), base: BASE, results }, null, 1));
const upsert = r => {
  const i = results.findIndex(x => x.id === r.id && x.persona === r.persona);
  if (i >= 0) results[i] = r; else results.push(r);
  flush();
};
const want = id => !ONLY.length || ONLY.includes(String(id).replace(/[a-z]$/, '')) || ONLY.includes(String(id));

const T0 = Date.now();
const stamp = () => ((Date.now() - T0) / 60000).toFixed(1) + 'min';
const go = async (page, url) => { console.error(`  [${stamp()}] go ${url}`); return goBase(page, url); };
const C = CTX.course;
const I = `/course/view.php?id=${C}`;

// Run one item. `fn(rec)` takes the screenshots and fills rec.shows / rec.notes; a throw becomes "not captured".
async function item(id, fix, persona, title, page, fn) {
  if (!want(id)) return;
  const rec = { id: String(id), fix, persona, title, status: 'captured', files: [], notes: [], console: [] };
  try {
    await fn(rec);
  } catch (e) {
    rec.status = 'not captured';
    rec.reason = String(e.message).split('\n')[0].slice(0, 300);
    try {
      const f = await shots(page, OUT, `${id}-DEBUG-${persona}`, { mobile: false });
      rec.debug = f[0];
      rec.url = page.url().replace(BASE, '');
      // What the page offers, so the next pass can pick a selector.
      rec.controls = await page.evaluate(() => [...document.querySelectorAll('button, a.btn, [data-action], [role="button"], .dropdown-item')]
        .filter(e => e.offsetParent !== null).map(e => ((e.innerText || e.getAttribute('aria-label') || '').trim().slice(0, 40)
          + (e.dataset.action ? ' {' + e.dataset.action + '}' : ''))).filter(Boolean).slice(0, 70));
    } catch (e2) { /* ignore */ }
  }
  rec.console = page.errs.splice(0);
  upsert(rec);
  console.log(`${id} [${persona}] ${rec.status}${rec.reason ? ': ' + rec.reason : ''}${rec.notes.length ? ' | ' + rec.notes.join(' | ') : ''}`);
}


// The local copy holds real users. A dashboard's Leaderboard lists colleagues by name: before any screenshot those names are
// replaced in the DOM (never in the database) by "[name hidden]", so no real person appears in the evidence. Returns how many.
async function maskPersonal(page) {
  return page.evaluate(() => {
    let n = 0;
    const containers = new Set();
    for (const el of document.querySelectorAll('h1,h2,h3,h4,h5,h6,div,span,p,strong,b')) {
      const own = [...el.childNodes].filter(x => x.nodeType === 3).map(x => x.textContent).join('').trim();
      if (!/^Leaderboard$/i.test(own)) continue;
      let c = el.parentElement;
      for (let i = 0; i < 8 && c; i++) { if (/\bpts\b/.test(c.innerText || '') && (c.innerText || '').length > 60) break; c = c.parentElement; }
      if (c) containers.add(c);
    }
    for (const c of containers) {
      const walker = document.createTreeWalker(c, NodeFilter.SHOW_TEXT);
      const nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      for (const node of nodes) {
        const t = (node.nodeValue || '').trim();
        if (!t || /^\d+$/.test(t) || /pts$/.test(t) || /^\(?you\)?$/i.test(t) || /^Leaderboard$/i.test(t) || /^Your (department|team)/i.test(t) || /^VP\b/.test(t) || /\(You\)/.test(t)) continue;
        node.nodeValue = /^[A-Z]{1,3}$/.test(t) ? '--' : '[name hidden]';
        n++;
      }
    }
    return n;
  });
}

// Take the shots of the current page into rec (checks the page for defects too).
async function snap(page, rec, name, opts = {}) {
  const masked = await maskPersonal(page);
  if (masked) rec.notes.push(`${name}: ${masked} leaderboard name(s) masked in the DOM before the screenshot (real users on the local copy)`);
  const s = await scan(page);
  const f = await shots(page, OUT, name, opts);
  rec.files.push(...f);
  if (s.problems.length) rec.notes.push(`${name}: ${s.problems.join('; ')}`);
  if (f.overflow && (f.overflow.desktop > 1 || f.overflow.mobile > 1)) rec.notes.push(`${name}: horizontal overflow ${JSON.stringify(f.overflow)}`);
  return s;
}

// Open a modal-opening click, shoot the modal (the whole viewport), return the modal locator.
async function modalShot(page, rec, name, clickLocator, modalSel = '.modal.show') {
  await clickLocator.first().scrollIntoViewIfNeeded().catch(() => {});
  await clickLocator.first().click({ timeout: 30000 }).catch(() => clickLocator.first().evaluate(e => e.click()));
  const modal = page.locator(modalSel).first();
  await modal.waitFor({ state: 'visible', timeout: 90000 });
  // The title and body of some dialogs arrive over AJAX: wait for a title, and for a spinner to go.
  await page.waitForFunction(() => { const m = document.querySelector('.modal.show'); const t = m && m.querySelector('.modal-title'); return t && t.textContent.trim().length > 0 && !m.querySelector('.modal-body [data-region="loading-icon"], .modal-body .loading-icon, .modal-body .fa-spin'); },
    null, { timeout: 90000, polling: 1000 }).catch(() => {});
  await page.waitForTimeout(1200);
  await maskPersonal(page);
  const files = [];
  for (const [label, w, h] of [['desktop', 1440, 900], ['mobile', 590, 1000]]) {
    await page.setViewportSize({ width: w, height: h });
    await page.waitForTimeout(700);
    const file = `${name}-${label}.png`;
    await page.screenshot({ path: path.join(OUT, file) });
    files.push(file);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  rec.files.push(...files);
  return modal;
}

async function cancelModal(page) {
  const cancel = page.locator('.modal.show [data-action="cancel"], .modal.show button:has-text("Cancel")').first();
  if (await cancel.count()) await cancel.click({ timeout: 30000 }).catch(() => {});
  else await page.keyboard.press('Escape');
  await page.waitForTimeout(1000);
}


// Click the edit-mode switch and wait for the page to RELOAD (the switch calls a web service, then reloads; on this slow box that
// takes a while, and a screenshot taken before it is the old page with a ticked box). Returns whether editing is on afterwards.
async function toggleEdit(page, sel = 'form.editmode-switch-form input[name="setmode"]', trace = null) {
  const toggle = page.locator(sel).first();
  await page.evaluate(() => { window.__vpmarker = true; });
  const onResp = r => { if (trace && /editmode|ajax\/service|edit_switch|setmode/.test(r.url())) trace.push(`${r.request().method()} ${r.status()} ${r.url().replace(BASE, '').slice(0, 110)}`); };
  if (trace) page.on('response', onResp);
  await toggle.click({ timeout: 60000 });
  await page.waitForFunction(() => !window.__vpmarker, null, { timeout: 240000, polling: 1000 }).catch(() => {});
  await page.waitForLoadState('load', { timeout: 120000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(2500);
  if (trace) page.off('response', onResp);
  const editing = await page.evaluate(() => document.body.classList.contains('editing'));
  // The course editor draws its controls after the page's AMD modules load: give it time when editing is on.
  if (editing) await page.waitForFunction(() => document.querySelectorAll('#region-main .action-menu, #region-main [data-for="section_title"], #region-main [data-region="add-activity"], #region-main .section-add-activity, #region-main .dropdown-toggle').length > 0,
    null, { timeout: 45000, polling: 1000 }).catch(() => {});
  return editing;
}

console.error(`[${stamp()}] launching Chrome`);
const browser = await launch();
try {
  // ======================================================================= learner
  if (PERSONAS.includes('learner')) {
    console.error(`[${stamp()}] logging in vp_learner1`);
    const { context, page } = await session(browser, 'vp_learner1');

    await item(1, 'FX-01', 'learner', 'Dashboard and a course page render with their header', page, async rec => {
      await go(page, '/my/dashboard.php');
      await snap(page, rec, '01-dashboard-learner');
      await go(page, I);
      await snap(page, rec, '01-course-learner');
      const hasSwitch = await page.locator('form.editmode-switch-form, [data-region="editswitch"], input[name="setmode"]').count();
      rec.notes.push(`edit switch present for the learner: ${hasSwitch ? 'YES (wrong)' : 'no (item 3: correct)'}`);
      rec.item3 = !hasSwitch;
    });

    await item(4, 'FX-03', 'learner', 'Activity page with Opened / Due dates, and one without dates', page, async rec => {
      await go(page, `/mod/assign/view.php?id=${CTX.assign_cmid}`);
      const t = await page.locator('body').innerText();
      rec.notes.push(/Opened:|Due:/i.test(t) ? 'Opened / Due line present' : 'NO Opened/Due text on the assignment page');
      await snap(page, rec, '04-activity-dates-assign');
      await go(page, `/mod/page/view.php?id=${CTX.page_cmid}`);
      await snap(page, rec, '04b-activity-nodates-page');
    });

    await item(15, 'FX-19', 'learner', 'Course overview card view (my courses)', page, async rec => {
      await go(page, '/my/courses.php');
      // The overview block fills over AJAX: wait for a course card (or its empty message).
      await page.waitForFunction(() => document.querySelector('[data-region="paged-content-page"] a[href*="course/view.php"], [data-region="course-content"] a[href*="course/view.php"], .dashboard-card, .course-card') || /no courses|not enrolled/i.test(document.body.innerText),
        null, { timeout: 150000, polling: 2000 }).catch(() => {});
      await page.waitForTimeout(2000);
      const cards = await page.locator('[data-region="paged-content-page"] a[href*="course/view.php"], .dashboard-card, .course-card').count();
      rec.notes.push(`course cards in the overview: ${cards}`);
      await snap(page, rec, '15-my-courses-learner');
    });

    await item(17, 'FX-20', 'learner', 'Skill page: Self-rate dialog', page, async rec => {
      await go(page, `/local/sentientia_skills/view.php?id=${CTX.skill}`);
      await snap(page, rec, '17-skill-page-learner');
      const btn = page.locator('[data-action*="self-rate"], [data-action*="selfrate"], button:has-text("Self-rate"), a:has-text("Self-rate"), button:has-text("Self rate")');
      if (!(await btn.count())) throw new Error('no Self-rate button on the skill page');
      await modalShot(page, rec, '17-selfrate-dialog', btn);
      // Save with no level chosen: the warning must show and the dialog must stay open.
      const save = page.locator('.modal.show [data-action="save"], .modal.show button:has-text("Save")').first();
      if (await save.count()) {
        await save.click({ timeout: 30000 }).catch(() => {});
        await page.waitForTimeout(4000);
        const still = await page.locator('.modal.show').count();
        const alerts = await page.evaluate(() => [...document.querySelectorAll('.alert, .toast, [data-region="notification"], #user-notifications > *')]
          .map(e => ({ text: (e.innerText || '').trim().slice(0, 120), shown: e.offsetParent !== null })).filter(a => a.text));
        const containers = await page.evaluate(() => document.querySelectorAll('#user-notifications, [data-region="notifications"], .toast-wrapper').length);
        rec.notes.push(`Save with no level: dialog ${still ? 'stays open' : 'closed'}; alerts in the page: ${JSON.stringify(alerts)}; notification containers in the page: ${containers}`);
        const f = `17-selfrate-empty-level-warning-desktop.png`;
        await page.screenshot({ path: path.join(OUT, f) });
        rec.files.push(f);
      }
      await cancelModal(page);
      await page.waitForTimeout(800);
      const f2 = `17-selfrate-after-cancel-desktop.png`;
      await page.screenshot({ path: path.join(OUT, f2) });
      rec.files.push(f2);
    });
    await context.close();
  }

  // ======================================================================= public learner
  if (PERSONAS.includes('public')) {
    console.error(`[${stamp()}] logging in vp_public77`);
    const { context, page } = await session(browser, 'vp_public77');
    await item(6, 'FX-05', 'public', 'Priced course: enrol, pay with Airpay: the checkout placeholder modal', page, async rec => {
      await go(page, '/enrol/index.php?id=' + (CTX.fee_course || 455));
      await snap(page, rec, '06-priced-course-enrol-page');
      const pay = page.locator('[data-action="core_payment/triggerPayment"], button:has-text("Enrol me"), button:has-text("Select payment type")').first();
      if (!(await pay.count())) {
        const t = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
        throw new Error('no payment button on the enrol page: ' + t.slice(t.indexOf('Enrolment options'), t.indexOf('Enrolment options') + 240));
      }
      await modalShot(page, rec, '06-select-payment-modal', pay);
      const gw = page.locator('.modal.show').getByText(/airpay/i).first();
      rec.notes.push('Airpay offered in the payment modal: ' + (await gw.count() ? 'yes' : 'no'));
      await cancelModal(page);
    });
    await context.close();
  }

  // ======================================================================= author (trainer)
  if (PERSONAS.includes('author')) {
    const { context, page } = await session(browser, 'vp_courseauthor1');

    await item(2, 'FX-02', 'author', 'Edit mode switch (course and activity pages), off then on then off', page, async rec => {
      await go(page, I);
      const form = page.locator('form.editmode-switch-form').first();
      const info = await page.evaluate(() => {
        const f = document.querySelector('form.editmode-switch-form');
        return f ? { action: f.getAttribute('action'), method: f.getAttribute('method'), cls: f.className,
          noscript: !!f.querySelector('noscript'), hidden: [...f.querySelectorAll('input[type=hidden]')].map(i => i.name) } : null;
      });
      rec.notes.push('switch form: ' + JSON.stringify(info));
      if (!info) throw new Error('no edit-mode switch form on the course page for the trainer');
      await snap(page, rec, '02-course-editswitch-off');
      const trace1 = [];
      const on = await toggleEdit(page, undefined, trace1);
      rec.notes.push(`after the click and the reload, body.editing: ${on}; requests: ${JSON.stringify(trace1)}`);
      const editUi = await page.locator('[data-action="addSection"], .section-actions, [data-for="section_title"] ~ * [data-bs-toggle="dropdown"], .activity-actions, [data-region="add-activity"]').count();
      rec.notes.push(`course-editor controls visible: ${editUi}`);
      await snap(page, rec, '02-course-editswitch-on');
      // activity page while editing
      await go(page, `/mod/assign/view.php?id=${CTX.assign_cmid}`);
      await snap(page, rec, '02-activity-editswitch-on');
      // turn editing off again
      if (await page.locator('form.editmode-switch-form').count()) {
        const trace2 = [];
        const before = await page.locator('form.editmode-switch-form input[name="setmode"]').first().isChecked();
        const off = await toggleEdit(page, undefined, trace2);
        rec.notes.push(`second click (box was ${before ? 'ticked' : 'unticked'}): body.editing afterwards: ${off}; requests: ${JSON.stringify(trace2)}`);
        await snap(page, rec, '02-activity-editswitch-off');
      }
    });

    await item('2b', 'FX-02', 'author', 'Edit-mode switch form with JavaScript disabled (noscript Set mode button)', page, async rec => {
      const state = await context.storageState();
      const ctxNoJs = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false, storageState: state, serviceWorkers: 'block' });
      const p = await ctxNoJs.newPage();
      p.setDefaultTimeout(240000);
      p.errs = [];
      try {
        await go(p, I);
        const html = await p.content();
        const m = html.match(/<form[^>]*editmode-switch-form[\s\S]*?<\/form>/);
        rec.notes.push('form markup (JS off, first 600 chars): ' + (m ? m[0].replace(/\s+/g, ' ').slice(0, 600) : 'NO editmode-switch-form in the page source'));
        await snap(p, rec, '02b-course-js-disabled');
      } finally {
        await ctxNoJs.close();
      }
    });

    await item('2c', 'FX-02', 'author', 'Edit switch state on the course page and on an activity page while editing is on (diagnostic)', page, async rec => {
      const state = () => page.evaluate(() => {
        const i = document.querySelector('form.editmode-switch-form input[name="setmode"]');
        return { url: location.pathname + location.search, bodyEditing: document.body.classList.contains('editing'),
          switchPresent: !!i, switchChecked: i ? i.checked : null, labelPrimary: !!document.querySelector('form.editmode-switch-form label.text-primary'),
          dataPageurl: i ? i.dataset.pageurl : null };
      });
      await go(page, I);
      let st = await state();
      rec.notes.push('course page, on arrival: ' + JSON.stringify(st));
      if (!st.bodyEditing && st.switchPresent) {
        await toggleEdit(page);
        st = await state();
        rec.notes.push('course page, after switching editing on: ' + JSON.stringify(st));
      }
      await go(page, `/mod/assign/view.php?id=${CTX.assign_cmid}`);
      rec.notes.push('assign page, on arrival with editing on: ' + JSON.stringify(await state()));
      await go(page, I);
      rec.notes.push('course page again: ' + JSON.stringify(await state()));
      // leave editing off
      st = await state();
      if (st.bodyEditing && st.switchPresent) {
        await toggleEdit(page);
        rec.notes.push('course page, after switching editing off: ' + JSON.stringify(await state()));
      }
    });

    await item(15, 'FX-19', 'author', 'Dashboard in edit mode (teacher): block controls on the title row', page, async rec => {
      await go(page, '/my/dashboard.php');
      await snap(page, rec, '15-dashboard-teacher');
      const sw = page.locator('form.editmode-switch-form input[name="setmode"], button:has-text("Customise this page"), button:has-text("Customize this page")').first();
      if (!(await sw.count())) {
        rec.notes.push('no edit switch / "Customise this page" button on /my/dashboard.php for the trainer (the theme dashboard is custom)');
        return;
      }
      const on = await toggleEdit(page, 'form.editmode-switch-form input[name="setmode"]');
      rec.notes.push(`dashboard editing after the click: ${on}`);
      await snap(page, rec, '15-dashboard-teacher-editing');
      if (await page.locator('form.editmode-switch-form').count()) await toggleEdit(page);
    });
    await context.close();
  }

  // ======================================================================= tenant admin
  if (PERSONAS.includes('admin')) {
    const { context, page } = await session(browser, 'vp_admin1');

    await item(5, 'FX-04', 'admin', 'E-mail template editor live preview with a tenant override; template list preview', page, async rec => {
      const previewInfo = async () => page.evaluate(() => {
        const f = document.getElementById('preview-frame');
        if (!f) return { frame: false };
        let text = '';
        try { text = (f.contentDocument.body.innerText || '').trim().slice(0, 160); } catch (e) { text = 'unreadable: ' + e.message; }
        return { frame: true, srcdocLength: (f.srcdoc || '').length, bodyText: text, height: f.style.height };
      });
      await go(page, `/local/sentientia_emails/editor.php?template=${encodeURIComponent(CTX.email_template)}&tenant=1`);
      await page.waitForFunction(() => { const f = document.getElementById('preview-frame'); try { return f && f.contentDocument && f.contentDocument.body && f.contentDocument.body.innerText.trim().length > 0; } catch (e) { return true; } }, null, { timeout: 60000 }).catch(() => {});
      const info = await previewInfo();
      rec.notes.push('override template preview frame: ' + JSON.stringify(info));
      const s = await snap(page, rec, '05-email-editor-preview-override', { maxHeight: 2600 });
      rec.notes.push(/Preview error/i.test(s.text) ? 'PREVIEW ERROR text on the page' : 'no "Preview error" on the page');
      await go(page, '/local/sentientia_emails/manage.php?tab=templates');
      await snap(page, rec, '05-email-manage-templates');
      const other = await page.evaluate((cur) => [...document.querySelectorAll('a[href*="editor.php?template="]')].map(a => a.getAttribute('href'))
        .find(h => !h.includes(encodeURIComponent(cur)) && !h.includes(cur)) || null, CTX.email_template);
      if (other) {
        await go(page, other.replace(BASE, ''));
        await page.waitForFunction(() => { const f = document.getElementById('preview-frame'); try { return f && f.contentDocument && f.contentDocument.body && f.contentDocument.body.innerText.trim().length > 0; } catch (e) { return true; } }, null, { timeout: 60000 }).catch(() => {});
        rec.notes.push('a template WITHOUT an override (' + other.slice(-60) + '): ' + JSON.stringify(await previewInfo()));
        await snap(page, rec, '05-email-editor-preview-no-override', { maxHeight: 2600 });
      } else rec.notes.push('no second template link found on the templates tab');
    });

    await item(7, 'FX-06', 'admin', 'Cart admin orders: Refund dialog (Save and Cancel in the footer)', page, async rec => {
      await go(page, '/local/sentientia_cart/admin_orders.php');
      await page.waitForFunction(() => /#|Order/i.test(document.body.innerText) && document.querySelector('table tbody tr'), null, { timeout: 180000 }).catch(() => {});
      await snap(page, rec, '07-cart-admin-orders');
      let btn = page.locator('[data-action="refund-order"]');
      if (!(await btn.count())) {
        // Nothing in the product renders the trigger (git grep finds the handler in amd/src/admin_orders.js and no template or
        // PHP that emits data-action="refund-order"). To still show the dialog the FX-06 fix repaired, add the attributes the handler
        // reads to a button in the page: the dialog that opens is the real one. Nothing is submitted.
        rec.notes.push('NO Refund button is rendered by the orders page (no template or PHP emits data-action="refund-order"); the dialog below was opened from a button INJECTED into the page with the attributes the handler reads');
        await page.evaluate(oid => {
          const b = document.createElement('button');
          b.id = 'vp-injected-refund';
          b.className = 'btn btn-outline-primary btn-sm';
          b.textContent = 'Refund (injected for the screenshot)';
          Object.assign(b.dataset, { action: 'refund-order', historyid: '1', total: '2000.00', orderid: String(oid) });
          const t = document.querySelector('table');
          (t ? t.parentElement : document.body).append(b);
        }, CTX.orders_tenant1_orderid || 923430);
        btn = page.locator('#vp-injected-refund');
      }
      const modal = await modalShot(page, rec, '07-refund-dialog', btn);
      const footer = await modal.locator('.modal-footer button').allInnerTexts().catch(() => []);
      rec.notes.push('dialog footer buttons: ' + JSON.stringify(footer.map(s => s.trim())));
      await cancelModal(page);
    });

    await item(8, 'FX-06', 'admin', 'Enrolled users: Enrol user dialog (Save and Cancel)', page, async rec => {
      await go(page, `/local/sentientia_courses/enrolledusers.php?id=${C}`);
      await page.waitForFunction(() => document.querySelector('table tbody tr'), null, { timeout: 180000 }).catch(() => {});
      await snap(page, rec, '08-enrolled-users');
      const btn = page.locator('[data-action="enrol-user-modal"]');
      if (!(await btn.count())) throw new Error('no Enrol user button on the page');
      const modal = await modalShot(page, rec, '08-enrol-user-dialog', btn);
      const footer = await modal.locator('.modal-footer button').allInnerTexts().catch(() => []);
      rec.notes.push('dialog footer buttons: ' + JSON.stringify(footer.map(s => s.trim())));
      await cancelModal(page);
    });

    await item(9, 'FX-06b', 'admin', 'Pending requests: Approve and Reject dialogs (the learner-side Request enrolment button is in no template)', page, async rec => {
      await go(page, '/local/sentientia_request/approvals.php');
      await page.waitForFunction(() => document.querySelector('[data-action="decide-request"]') || /no pending|nothing/i.test(document.body.innerText), null, { timeout: 180000 }).catch(() => {});
      await snap(page, rec, '09-pending-approvals');
      const approve = page.locator('[data-action="decide-request"][data-decision="approved"]');
      const reject = page.locator('[data-action="decide-request"][data-decision="rejected"]');
      if (!(await approve.count())) throw new Error('no Approve button: the pending request is not listed for this approver');
      await modalShot(page, rec, '09-approve-dialog', approve);
      await page.locator('.modal.show textarea').first().fill('typed text that must not survive Cancel').catch(() => {});
      await cancelModal(page);
      await modalShot(page, rec, '09-reject-dialog', reject);
      const val = await page.locator('.modal.show textarea').first().inputValue().catch(() => '?');
      rec.notes.push(`re-opened dialog textarea after Cancel is ${val === '' ? 'empty (correct)' : 'NOT empty: ' + JSON.stringify(val)}`);
      await cancelModal(page);
    });

    await item(18, 'FX-20', 'admin', 'Org tree collapse and row menu; evaluation question card menu', page, async rec => {
      await go(page, '/local/sentientia_org/index.php');
      await snap(page, rec, '18-org-tree');
      const coll = page.locator('[data-toggle="collapse"], [data-bs-toggle="collapse"]').first();
      if (await coll.count()) {
        await coll.click({ timeout: 30000 }).catch(() => {});
        await page.waitForTimeout(1000);
        const f = '18-org-tree-collapse-clicked-desktop.png';
        await page.screenshot({ path: path.join(OUT, f) });
        rec.files.push(f);
      } else rec.notes.push('no collapse toggle found on the org page');
      const menu = page.locator('.dropdown-toggle, [data-toggle="dropdown"], [data-bs-toggle="dropdown"]:not(.nav-link)').filter({ hasNot: page.locator('.never') });
      const n = await menu.count();
      rec.notes.push(`${n} dropdown toggles on the org page`);
      if (n) {
        const m = menu.last();
        await m.scrollIntoViewIfNeeded().catch(() => {});
        await m.click({ timeout: 30000 }).catch(() => {});
        await page.waitForTimeout(900);
        const openAfterOne = await page.locator('.dropdown-menu.show').count();
        rec.notes.push(`row menu open after one click: ${openAfterOne ? 'yes (stays open)' : 'NO (closed)'}`);
        const f = '18-org-row-menu-open-desktop.png';
        await page.screenshot({ path: path.join(OUT, f) });
        rec.files.push(f);
      }
    });
    await context.close();
  }

  // ======================================================================= site admin
  if (PERSONAS.includes('siteadmin')) {
    const { context, page } = await session(browser, 'vp_siteadmin');

    await item(10, 'FX-07', 'siteadmin', 'Certificate templates: Issue certificates dialog (Days preselected)', page, async rec => {
      await go(page, '/admin/tool/certificate/manage_templates.php');
      await snap(page, rec, '10-certificate-templates');
      // Each template row has a kebab (aria-label "Actions"); its menu holds "Issue new certificate from this template".
      const actions = page.locator('[aria-label="Actions"], button[title="Actions"], .dropdown-toggle[aria-label*="ctions"]').first();
      if (!(await actions.count())) throw new Error('no Actions kebab on the templates list');
      await actions.scrollIntoViewIfNeeded().catch(() => {});
      await actions.click({ timeout: 30000 }).catch(() => actions.evaluate(e => e.click()));
      await page.waitForTimeout(1500);
      const f0 = '10-templates-actions-menu-open-desktop.png';
      await page.screenshot({ path: path.join(OUT, f0) });
      rec.files.push(f0);
      const items = await page.evaluate(() => [...document.querySelectorAll('.dropdown-menu a, .dropdown-menu button, [role="menu"] a, [role="menuitem"], .menu a')]
        .filter(e => e.offsetParent !== null).map(e => (e.innerText || e.getAttribute('aria-label') || '').trim()).filter(Boolean));
      rec.notes.push('visible Actions menu items after the click: ' + JSON.stringify(items));
      const markup = await actions.evaluate(e => { const c = e.closest('.dropdown, .action-menu, td') || e.parentElement; return c.outerHTML.replace(/\s+/g, ' ').slice(0, 900); });
      rec.notes.push('kebab markup: ' + markup);
      // The menu does not open on a click if it is a Bootstrap 5 dropdown in the Bootstrap 4.6 theme: the item still exists in
      // the DOM, hidden. Click that item through the DOM so the dialog it opens can be shown.
      let b2 = page.locator('.dropdown-menu a:visible, [role="menuitem"]:visible, .menu a:visible').filter({ hasText: /issue/i }).first();
      if (!(await b2.count())) {
        const hidden = page.locator('[aria-label="Actions"], .dropdown-toggle').first().locator('xpath=ancestor::*[contains(@class,"dropdown") or contains(@class,"action-menu") or self::td][1]//a[contains(translate(., "ISUE", "isue"), "issue")]').first();
        if (await hidden.count()) {
          rec.notes.push('the Actions menu did NOT open on a click; the "Issue" item exists in the DOM and was clicked through the DOM');
          b2 = hidden;
        } else throw new Error('no issue item in the Actions menu, visible or hidden');
      }
      await modalShot(page, rec, '10-issue-certificates-dialog', b2);
      // The form is a dynamic form: it arrives over AJAX after the dialog opens.
      const loaded = await page.waitForSelector('.modal.show .modal-body form select, .modal.show .modal-body form input:not([type="hidden"]), .modal.show .modal-body [data-fieldtype]', { timeout: 120000 }).then(() => true).catch(() => false);
      rec.notes.push(`the dialog's form fields ${loaded ? 'arrived' : 'NEVER arrived within 120 s (the dialog stays empty: only the title, Cancel and Save)'}`);
      await page.waitForTimeout(1500);
      for (const [label, w, h] of [['desktop', 1440, 900], ['mobile', 590, 1000]]) {
        await page.setViewportSize({ width: w, height: h });
        await page.waitForTimeout(700);
        const f = `10-issue-certificates-dialog-after-wait-${label}.png`;
        await page.screenshot({ path: path.join(OUT, f) });
        rec.files.push(f);
      }
      await page.setViewportSize({ width: 1440, height: 900 });
      const unit = await page.locator('.modal.show select[name*="expires"], .modal.show select[name*="unit"], .modal.show select').evaluateAll(
        els => els.map(e => ({ name: e.name, value: e.value, text: e.selectedOptions[0] ? e.selectedOptions[0].text : '' }))).catch(() => []);
      rec.notes.push('selects in the dialog: ' + JSON.stringify(unit));
      await cancelModal(page);
    });

    await item(13, 'FX-14', 'siteadmin', 'A core modal (delete activity): title at normal size, close button', page, async rec => {
      await go(page, I);
      const sw = page.locator('form.editmode-switch-form input[name="setmode"]').first();
      if (await sw.count() && !(await sw.isChecked())) {
        rec.notes.push(`editing on: ${await toggleEdit(page)}`);
      }
      const item = page.locator(`[data-for="cmitem"][data-id="${CTX.assign_cmid}"], li#module-${CTX.assign_cmid}`).first();
      await item.waitFor({ state: 'visible', timeout: 120000 });
      const menu = item.locator('button:has-text("Edit"), [data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle').first();
      await menu.scrollIntoViewIfNeeded().catch(() => {});
      await menu.evaluate(e => e.click());
      await page.waitForTimeout(1500);
      const del = item.locator('[data-action="cmDelete"], .dropdown-menu a:has-text("Delete"), .dropdown-item:has-text("Delete")').first();
      await modalShot(page, rec, '13-core-modal-delete-activity', del);
      const title = await page.locator('.modal.show .modal-title').first().evaluate(e => ({ text: e.textContent.trim(), size: getComputedStyle(e).fontSize, tag: e.tagName })).catch(() => null);
      rec.notes.push('modal title: ' + JSON.stringify(title));
      await cancelModal(page);
      // editing off again
      const sw2 = page.locator('form.editmode-switch-form input[name="setmode"]').first();
      if (await sw2.count() && await sw2.isChecked()) await toggleEdit(page);
    });

    await item(16, 'FX-20', 'siteadmin', 'Switchboard: change a flag, press Apply: the Review changes dialog (Cancel only)', page, async rec => {
      await go(page, '/local/sentientia_platform/admin/switchboard.php');
      await snap(page, rec, '16-switchboard', { maxHeight: 2400 });
      // Stage a change (client side only: nothing is sent until "Apply changes" in the dialog) on a flag this pass may touch:
      // sentientia.evaluation.learner_history, force ON for the viewed scope. The dialog is only ever cancelled.
      const row = page.locator('[data-flag-row][data-flag-key="sentientia.evaluation.learner_history"]').first();
      if (!(await row.count())) throw new Error('no switchboard row for sentientia.evaluation.learner_history');
      await row.scrollIntoViewIfNeeded().catch(() => {});
      await row.locator('[data-action="toggle-on"]').first().click({ timeout: 30000 });
      await page.waitForTimeout(800);
      const modified = await row.getAttribute('data-flag-modified');
      rec.notes.push(`row marked modified after the click: ${modified}`);
      const apply = page.locator('[data-action="open-apply"]').first();
      await modalShot(page, rec, '16-switchboard-review-dialog', apply);
      const buttons = await page.locator('.modal.show .modal-footer button').allInnerTexts().catch(() => []);
      rec.notes.push('dialog footer buttons: ' + JSON.stringify(buttons.map(s => s.trim())));
      await cancelModal(page);
      rec.notes.push('Cancel pressed; Apply changes was never clicked. The staged toggle is discarded by leaving the page.');
    });

    await item('18b', 'FX-20', 'siteadmin', 'Evaluation question card menu stays open after one click', page, async rec => {
      await go(page, `/local/sentientia_evaluation/questions.php?id=${CTX.evaluation_form}`);
      await snap(page, rec, '18b-evaluation-questions');
      const toggles = page.locator('.dropdown-toggle:visible, [data-toggle="dropdown"]:visible, [data-bs-toggle="dropdown"]:visible').filter({ hasNot: page.locator('.nav-link') });
      const n = await toggles.count();
      rec.notes.push(`${n} dropdown toggles on the questions page`);
      if (!n) throw new Error('no question-card menu toggle on the page');
      const t = toggles.last();
      await t.scrollIntoViewIfNeeded().catch(() => {});
      await t.click({ timeout: 30000 }).catch(() => t.evaluate(e => e.click()));
      await page.waitForTimeout(1200);
      const open1 = await page.locator('.dropdown-menu.show').count();
      rec.notes.push(`menus open after one click: ${open1}`);
      const f = '18b-question-card-menu-open-desktop.png';
      await page.screenshot({ path: path.join(OUT, f) });
      rec.files.push(f);
      await page.waitForTimeout(1500);
      rec.notes.push(`menus still open 1.5 s later: ${await page.locator('.dropdown-menu.show').count()}`);
    });

    await item(19, 'FX-21', 'siteadmin', 'Log in as a learner (the audit row is read back by the CLI check below)', page, async rec => {
      await go(page, `/user/view.php?id=${CTX.users.vp_learner1}&course=1`);
      const link = page.locator('a[href*="/course/loginas.php"]').first();
      if (!(await link.count())) throw new Error('no "Log in as" link on the learner profile page');
      await link.click({ timeout: 60000 });
      await page.waitForLoadState('load', { timeout: 120000 }).catch(() => {});
      const confirmBtn = page.locator('form button:has-text("Continue"), form input[type="submit"][value="Continue"], .continuebutton button, button:has-text("Yes")').first();
      if (await confirmBtn.count()) {
        await confirmBtn.click({ timeout: 60000 }).catch(() => {});
        await page.waitForLoadState('load', { timeout: 120000 }).catch(() => {});
      }
      await page.waitForTimeout(3000);
      rec.notes.push('landed on ' + page.url().replace(BASE, ''));
      await snap(page, rec, '19-logged-in-as-learner');
      rec.notes.push('the audit read-back is in the commit message / README (audit_log::sensitive_actions through the CLI)');
    });
    await context.close();
  }
} finally {
  await browser.close();
  flush();
}
