// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY evidence for theme 2026111200 (Nitin's decisions A2 and A3, 2026-11-12):
//   footer   on a standard-layout page (Manage Users, vp_admin1) and a course page (vp_learner1) the footer is the last
//            element of #ap-shell-content: out of view at the top of a long page, in view after scrolling to the end
//   drawer   course edit mode as vp_courseauthor1 (non-admin, has the in-page Course Content sidebar): no core
//            course-index drawer, no left toggle, no drawer-open-index; as vp_siteadmin (no sidebar) the drawer stays
//
//   node theme_1112_checks.mjs [--out <dir>] [--course 448] [--only footer|drawer]
//
// Edit mode is turned on and back off for each persona (per session); nothing else changes. Exit 1 when a check fails.
import fs from 'node:fs';
import path from 'node:path';
import { HERE, go, launch, opt, session, shots } from './evidence_lib.mjs';

const ONLY = opt('--only', '');
const want = s => !ONLY || ONLY === s;
const OUT = opt('--out', path.resolve(HERE, '../../docs/visual-evidence/2026-11-12/theme-drawer-footer'));
fs.mkdirSync(OUT, { recursive: true });
const COURSE = `/course/view.php?id=${opt('--course', '448')}`;
const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';
const results = [];
const record = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}  ${JSON.stringify(detail)}`);
};

// Where the footer sits, in a fixed 1280 x 665 window (the size of Nitin's Chrome on 11-11), before and after scrolling
// the content scroller to its end.
async function footerFacts(page) {
  await page.setViewportSize({ width: 1280, height: 665 });
  await page.waitForTimeout(800);
  const at = () => page.evaluate(() => {
    const f = document.querySelector('#page-footer');
    const sc = document.querySelector('#ap-shell-content');
    if (!f) return { footer: false };
    const r = f.getBoundingClientRect();
    return {
      footer: true,
      parent: f.parentElement ? (f.parentElement.id || f.parentElement.tagName.toLowerCase()) : null,
      insideScroller: !!(sc && sc.contains(f)),
      top: Math.round(r.top), height: Math.round(r.height), viewport: innerHeight,
      inView: r.top < innerHeight && r.bottom > 0,
      scrollerScrollable: sc ? sc.scrollHeight > sc.clientHeight + 5 : null,
    };
  });
  const before = await at();
  await page.evaluate(() => {
    const sc = document.querySelector('#ap-shell-content');
    if (sc) sc.scrollTop = sc.scrollHeight;
    window.scrollTo(0, document.documentElement.scrollHeight);
  });
  await page.waitForTimeout(600);
  const after = await at();
  return { before, after };
}

async function setEditing(page, on) {
  const cb = page.locator(SWITCH).first();
  if (!(await cb.count())) return false;
  if ((await cb.isChecked().catch(() => false)) !== on) {
    await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), cb.click()]);
    await page.waitForLoadState('domcontentloaded').catch(() => {});
    await page.waitForTimeout(2500);
  }
  return true;
}

const drawerFacts = page => page.evaluate(() => {
  const vis = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
  const sidebar = document.querySelector('#ap-course-sidebar');
  return {
    editing: document.body.classList.contains('editing'),
    drawer: !!document.querySelector('#theme_sentientia-drawers-courseindex'),
    leftToggle: vis(document.querySelector('.drawer-left-toggle')),
    drawerOpenIndex: document.body.classList.contains('drawer-open-index'),
    inPageSidebar: vis(sidebar),
    sidebarSections: sidebar ? sidebar.querySelectorAll('.ap-course-sidebar__section').length : 0,
  };
});

const browser = await launch();
try {
  if (want('footer')) {
    for (const [persona, url, name, label] of [
      ['vp_admin1', '/local/sentientia_users/index.php', '01-manage-users-footer', 'standard layout (Manage Users)'],
      ['vp_learner1', COURSE, '02-course-page-footer', 'course page (view mode)'],
    ]) {
      const { context, page } = await session(browser, persona);
      await go(page, url);
      const f = await footerFacts(page);
      const ok = f.before.footer && f.before.insideScroller && f.after.inView
        && (f.before.scrollerScrollable ? !f.before.inView : true);
      record(`footer: ${label}`, ok, f);
      // Window-sized shot at the end of the page: the footer closes the content, the sidebar and topbar stay put.
      await page.screenshot({ path: path.join(OUT, `${name}-end-1280x665.png`) });
      await shots(page, OUT, name, { maxHeight: 2400 });
      if (page.errs.length) record(`console: ${label}`, false, page.errs);
      await context.close();
    }
  }
  if (want('drawer')) {
    for (const [persona, expectDrawer, name] of [
      ['vp_courseauthor1', false, '03-courseauthor-edit-mode-no-empty-drawer'],
      ['vp_siteadmin', true, '04-siteadmin-edit-mode-keeps-drawer'],
    ]) {
      const { context, page } = await session(browser, persona);
      await go(page, COURSE);
      const wasOn = await page.locator(SWITCH).first().isChecked().catch(() => false);
      const can = await setEditing(page, true);
      const d = await drawerFacts(page);
      const ok = can && d.editing && (expectDrawer
        ? d.drawer
        : (!d.drawer && !d.leftToggle && !d.drawerOpenIndex && d.inPageSidebar));
      record(`edit-mode drawer: ${persona}`, ok, d);
      await shots(page, OUT, name, { maxHeight: 1600 });
      if (page.errs.length) record(`console: ${persona} edit mode`, false, page.errs);
      if (!wasOn) {
        await go(page, COURSE);
        await setEditing(page, false);
      }
      await context.close();
    }
  }
} finally {
  await browser.close();
}
const failed = results.filter(r => !r.ok).length;
console.log(`${results.length - failed} passed, ${failed} failed; shots in ${OUT}`);
process.exit(failed ? 1 : 0);
