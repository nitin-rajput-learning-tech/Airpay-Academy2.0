// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY evidence for theme 2026110200 (2026-11-02), the two cosmetic notes of the UAT /my/ check (10-30):
//   trail     one visible breadcrumb trail on notification preferences (vp_admin1) and on a course page
//             (vp_siteadmin): the shell strip prints, the page header's #page-navbar does not
//   calendar  the block calendar's day names on one line in the dashboard blocks drawer (vp_admin1, tenant 1)
//
//   php blocksdrawer_flag.php save && php blocksdrawer_flag.php apply     (cwd = moodle5/public) before "calendar"
//   node theme_1102_checks.mjs [--out <dir>] [--course <id>] [--path <url>] [--only dashboard|course]
//   php blocksdrawer_flag.php restore                                    afterwards, always
//
// Exit code 1 when a check fails. Passwords are never printed (evidence_lib reads .personas.local.json).
import path from 'node:path';
import { HERE, go, launch, opt, session, shots } from './evidence_lib.mjs';

// --only dashboard|course runs one of the two sessions (default both).
const ONLY = opt('--only', '');
const want = s => !ONLY || ONLY === s;

const OUT = opt('--out', path.resolve(HERE, '../../docs/visual-evidence/2026-11-02/theme-trail-calendar'));
const COURSE = opt('--course', '2');
// --path overrides the course-page URL, e.g. an activity inside the course.
const CPATH = opt('--path', `/course/view.php?id=${COURSE}`);
const results = [];
const record = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}  ${JSON.stringify(detail)}`);
};

// Visible breadcrumb trails, and whether the header copy is hidden.
const trails = page => page.evaluate(() => {
  const seen = el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const lists = [...document.querySelectorAll('ol.breadcrumb, ul.breadcrumb')].filter(seen);
  const nav = document.querySelector('#page-navbar');
  return {
    visible: lists.length,
    strip: !!document.querySelector('.ap-shell__breadcrumbs'),
    pagenavbar: nav ? getComputedStyle(nav).display : 'absent',
    texts: lists.map(l => l.innerText.replace(/\s+/g, ' ').trim().slice(0, 80)),
  };
});

const openDrawer = async page => {
  const drawer = page.locator('#theme_sentientia-drawers-blocks');
  if (!(await drawer.count())) return false;
  if (!(await drawer.evaluate(d => d.classList.contains('show')))) {
    await page.locator('.ap-dashboard-blocks-toggle button').click();
    await page.waitForFunction(() => document.querySelector('#theme_sentientia-drawers-blocks')?.classList.contains('show'));
    await page.waitForTimeout(600);
  }
  return true;
};

// Each day-name header against the height of one line of its own text.
const dayNames = page => page.evaluate(() => {
  const ths = [...document.querySelectorAll('#theme_sentientia-drawers-blocks table.calendarmonth thead th')];
  return ths.map(th => {
    const label = th.querySelector('[aria-hidden="true"]') || th;
    const lh = parseFloat(getComputedStyle(label).lineHeight) || parseFloat(getComputedStyle(label).fontSize) * 1.5;
    return { text: label.textContent.trim(), lines: Math.round(label.getBoundingClientRect().height / lh),
      fontSize: getComputedStyle(th).fontSize, width: Math.round(th.getBoundingClientRect().width) };
  });
});

const browser = await launch();
try {
  if (want('dashboard')) {
    const { context, page } = await session(browser, 'vp_admin1');
    await go(page, '/message/notificationpreferences.php');
    const t = await trails(page);
    record('trail: notification preferences', t.visible === 1 && t.strip && t.pagenavbar === 'none', t);
    await shots(page, OUT, '01-notification-preferences', { maxHeight: 1400 });

    await go(page, '/my/');
    const has = await openDrawer(page);
    const days = has ? await dayNames(page) : [];
    record('calendar: day names on one line in the drawer',
      has && days.length === 7 && days.every(d => d.lines === 1), { drawer: has, days });
    await shots(page, OUT, '03-dashboard-drawer-calendar', { maxHeight: 1400, prepare: () => openDrawer(page) });
    if (page.errs.length) record('console: notification preferences + dashboard', false, page.errs);
    await context.close();
  }
  if (want('course')) {
    const { context, page } = await session(browser, 'vp_siteadmin');
    await go(page, CPATH);
    const t = await trails(page);
    // At most one visible trail; where the shell strip prints, the header copy is hidden.
    record(`trail: ${CPATH}`, t.visible <= 1 && (t.strip ? t.pagenavbar === 'none' : true), t);
    await shots(page, OUT, '02-course-page', { maxHeight: 1400 });
    if (page.errs.length) record('console: course page', false, page.errs);
    await context.close();
  }
} finally {
  await browser.close();
}
const failed = results.filter(r => !r.ok).length;
console.log(`${results.length - failed} passed, ${failed} failed; shots in ${OUT}`);
process.exit(failed ? 1 : 0);
