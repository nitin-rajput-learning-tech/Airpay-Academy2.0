// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY, read-only probe (2026-11-12, decision A2): where do the course-index drawer and the course index sit on a
// course page in EDIT mode? The 10-16 pass saw an empty dark drawer beside an in-page index.
// vp_courseauthor1 (editingteacher of vp_journey_free, course 448) turns edit mode on, the DOM facts are printed, and
// edit mode is turned off again. Nothing else changes.
//   node probe_1112_courseindex.mjs [--course 448]
import { go, launch, opt, session } from './evidence_lib.mjs';

const COURSE = `/course/view.php?id=${opt('--course', '448')}`;
const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';

const facts = page => page.evaluate(() => {
  const box = el => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const s = getComputedStyle(el);
    return { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height),
      display: s.display, position: s.position, visibility: s.visibility, bg: s.backgroundColor,
      cls: (el.className && typeof el.className === 'string') ? el.className.slice(0, 120) : '' };
  };
  const path = el => { const out = []; while (el && el !== document.body && out.length < 8) {
    out.push(el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    el = el.parentElement; } return out; };
  const drawer = document.querySelector('#theme_sentientia-drawers-courseindex');
  const index = document.querySelector('#courseindex');
  const leftish = [...document.querySelectorAll('body *')].filter(e => {
    const r = e.getBoundingClientRect(); const s = getComputedStyle(e);
    return r.left < 5 && r.width > 200 && r.width < 400 && r.height > 300 && s.display !== 'none' && s.visibility !== 'hidden';
  }).slice(0, 6).map(e => ({ path: path(e).slice(0, 3), ...box(e), text: e.innerText.replace(/\s+/g, ' ').trim().slice(0, 60) }));
  return {
    bodyEditing: document.body.classList.contains('editing'),
    bodyClasses: document.body.className.split(' ').filter(c => /drawer|editing|uses-drawers|ap-course|pagelayout/.test(c)),
    drawer: drawer ? { ...box(drawer), childCount: drawer.children.length, containsIndex: !!(index && drawer.contains(index)),
      bodyText: drawer.innerText.replace(/\s+/g, ' ').trim().slice(0, 100) } : null,
    index: index ? { ...box(index), path: path(index), items: index.querySelectorAll('.courseindex-item, li').length } : null,
    leftToggle: box(document.querySelector('.drawer-left-toggle')),
    navDrawer: box(document.querySelector('#nav-drawer')),
    leftish,
  };
});

const browser = await launch();
try {
  const { page } = await session(browser, 'vp_courseauthor1');
  await go(page, COURSE);
  const wasOn = await page.locator(SWITCH).first().isChecked().catch(() => false);
  if (!wasOn) {
    await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), page.locator(SWITCH).first().click()]);
    await page.waitForLoadState('domcontentloaded').catch(() => {});
  }
  await page.waitForTimeout(3000);
  console.log(JSON.stringify({ editModeWasOn: wasOn, ...(await facts(page)) }, null, 1));
  // Leave edit mode as found (off unless it was already on).
  if (!wasOn && await page.locator(SWITCH).first().isChecked().catch(() => false)) {
    await Promise.all([page.waitForNavigation({ timeout: 240000 }).catch(() => {}), page.locator(SWITCH).first().click()]);
  }
} finally {
  await browser.close();
}
