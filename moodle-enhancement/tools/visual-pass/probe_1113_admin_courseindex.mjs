// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY, read-only probe (2026-11-13, decision A2 follow-up): the 11-12 evidence run showed the site admin's
// course-index drawer in edit mode as an empty dark panel. Site admins get no in-page sidebar (layout/course.php), so
// core's drawer is their only course index. Is it (a) still loading, (b) never filled, or (c) filled but unreadable?
// vp_siteadmin turns edit mode on, the drawer is polled for up to --wait seconds, colours are measured, a screenshot is
// taken, and edit mode is turned off again if it was off. Nothing else changes.
//   node probe_1113_admin_courseindex.mjs [--course 448] [--wait 120] [--persona vp_siteadmin]
import path from 'node:path';
import { HERE, go, launch, opt, session } from './evidence_lib.mjs';

const COURSE = `/course/view.php?id=${opt('--course', '448')}`;
const WAIT = Number(opt('--wait', '120'));
const PERSONA = opt('--persona', 'vp_siteadmin');
const OUT = opt('--out', path.resolve(HERE, '../../docs/visual-evidence/2026-11-12/theme-drawer-footer'));
const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';

const facts = page => page.evaluate(() => {
  const drawer = document.querySelector('#theme_sentientia-drawers-courseindex');
  const index = drawer ? drawer.querySelector('#courseindex') : null;
  const items = index ? [...index.querySelectorAll('.courseindex-section, .courseindex-item, [data-for="section"], [data-for="cm"]')] : [];
  const first = items.find(e => e.offsetWidth || e.offsetHeight);
  const colour = el => {
    if (!el) return null;
    const s = getComputedStyle(el);
    let bgEl = el, bg = 'rgba(0, 0, 0, 0)';
    while (bgEl && (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent')) { bg = getComputedStyle(bgEl).backgroundColor; bgEl = bgEl.parentElement; }
    return { color: s.color, background: bg, opacity: s.opacity, visibility: s.visibility, display: s.display };
  };
  const r = drawer ? drawer.getBoundingClientRect() : null;
  return {
    editing: document.body.classList.contains('editing'),
    drawer: !!drawer,
    drawerBox: r ? { x: Math.round(r.left), w: Math.round(r.width), h: Math.round(r.height) } : null,
    drawerBg: drawer ? getComputedStyle(drawer).backgroundColor : null,
    index: !!index,
    placeholder: index ? !!index.querySelector('[data-region="loading-placeholder-content"], .placeholders') : null,
    items: items.length,
    visibleItems: items.filter(e => e.offsetWidth || e.offsetHeight).length,
    firstItemText: first ? (first.innerText || '').trim().slice(0, 40) : null,
    firstItemColour: colour(first ? (first.querySelector('a, span') || first) : null),
    indexHtmlLength: index ? index.innerHTML.length : 0,
  };
});

const browser = await launch();
let exit = 0;
try {
  const { context, page } = await session(browser, PERSONA);
  await go(page, COURSE);
  const cb = page.locator(SWITCH).first();
  const wasOn = await cb.isChecked().catch(() => false);
  if (!wasOn) {
    await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), cb.click()]);
    await page.waitForLoadState('domcontentloaded').catch(() => {});
  }
  const t0 = Date.now();
  let f = await facts(page);
  while (f.items === 0 && (Date.now() - t0) / 1000 < WAIT) {
    await page.waitForTimeout(3000);
    f = await facts(page);
  }
  f.secondsWaited = Math.round((Date.now() - t0) / 1000);
  f.consoleErrors = page.errs || [];
  console.log(JSON.stringify(f, null, 2));
  await page.screenshot({ path: path.join(OUT, '05-siteadmin-edit-mode-drawer-after-wait-desktop.png') });
  if (!f.items) exit = 1;
  if (!wasOn) {
    await go(page, COURSE);
    const cb2 = page.locator(SWITCH).first();
    if (await cb2.isChecked().catch(() => false)) {
      await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), cb2.click()]);
    }
  }
  await context.close();
} finally {
  await browser.close();
}
process.exit(exit);
