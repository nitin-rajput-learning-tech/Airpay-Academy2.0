// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY evidence for theme 2026111600 (demo quick wins, 2026-11-16). Observations 1 and 2 of
// docs/visual-evidence/2026-11-12/theme-drawer-footer/README.md:
//   footer   the floating assistant button (local_sentientia_assistant, fixed bottom-right) never sits over footer text.
//            At each window size the scroller is scrolled to its end and every footer text box is measured. A text box
//            whose horizontal range meets the button's would pass under it while the page scrolls, so the check is
//            horizontal (stricter than "no overlap at the end"). Personas: vp_admin1 on Manage Users, vp_learner1 on the
//            course page.
//   sidebar  the in-page "Course Content" sidebar starts collapsed at 992 px and below (where it used to float over the
//            content), a real <button> with aria-expanded/aria-controls opens it on click and closes it from the
//            keyboard, the opened list sits above the content instead of over it, the main column still wraps its
//            content (the floated #region-main) in both states; at 1440 px the sidebar is beside the
//            content as before (compared with the BEFORE run's facts when they exist). Personas: vp_learner1 (view
//            mode) and vp_courseauthor1 (edit mode). The B shortcut pressed on a list link never drops focus to <body>:
//            where it is wired it closes the list and focuses the toggle (after phase only).
//   footer   (after phase only, too) with the button taken out of the DOM for a moment, the footer keeps its default
//            padding: the space is reserved only on pages that print the button.
//   dark     (after phase only) the toggle's label contrast at 390 px, opened, light hover, dark rest and dark hover.
//
//   node theme_1116_checks.mjs [--phase before|after] [--out <dir>] [--course 448] [--only footer|sidebar|dark]
//
// --phase before captures the state before the change (its checks are expected to fail) and writes facts-before.json;
// --phase after (default) runs the same checks against the change. Edit mode is turned on for vp_courseauthor1 and
// switched back off if it was off; nothing else changes. Exit 1 when a check fails.
import fs from 'node:fs';
import path from 'node:path';
import { HERE, go, launch, opt, session } from './evidence_lib.mjs';

const PHASE = opt('--phase', 'after');
if (!['before', 'after'].includes(PHASE)) {
  console.error('--phase must be before or after');
  process.exit(2);
}
const ONLY = opt('--only', '');
const want = s => !ONLY || ONLY === s;
const OUT = opt('--out', path.resolve(HERE, '../../docs/visual-evidence/2026-11-16/demo-quick-wins'));
fs.mkdirSync(OUT, { recursive: true });
const COURSE = `/course/view.php?id=${opt('--course', '448')}`;
const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';
const BREAKPOINT = 992; // _course-player.scss: at this width and below the sidebar used to be a fixed overlay
const FOOTER_SIZES = [[1280, 665], [1366, 768], [1440, 900], [1920, 1080], [768, 1024], [590, 1000], [390, 844]];
const SHOT_SIZES = ['1280x665', '1366x768', '768x1024', '590x1000', '390x844'];
// The footer's default padding (_surface-footer.scss, the rules above the 2026-11-16 block), which must hold whenever the
// assistant button is not in the page: the row's inline end, and the band's two sides. The band is 16 px at every width:
// its 590 px rule (8px 12px) comes before the base rule (8px 16px) in the file, at the same specificity, so the base wins.
const footerDefault = w => ({ compactEnd: w <= 768 ? 20 : 40, band: 16 });
const results = [];
const facts = {};
const record = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}  ${JSON.stringify(detail)}`);
};
const file = name => path.join(OUT, `${PHASE}-${name}.png`);

// ── footer ───────────────────────────────────────────────────────────────────────────────────────────────────────────
// Scroll the content scroller (and the window) to the end, then measure the button and every rendered footer text box.
async function footerClearance(page) {
  await page.evaluate(() => {
    const sc = document.querySelector('#ap-shell-content');
    if (sc) sc.scrollTop = sc.scrollHeight;
    window.scrollTo(0, document.documentElement.scrollHeight);
  });
  await page.waitForTimeout(600);
  return page.evaluate(() => {
    const btn = document.querySelector('.airpay-assistant__toggle');
    const footer = document.querySelector('#page-footer');
    if (!footer) return { footer: false };
    const b = btn ? btn.getBoundingClientRect() : null;
    const button = b && b.width ? { left: Math.round(b.left), right: Math.round(b.right), top: Math.round(b.top), bottom: Math.round(b.bottom) } : null;
    const texts = [];
    const walker = document.createTreeWalker(footer, NodeFilter.SHOW_TEXT);
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      if (!n.nodeValue.trim()) continue;
      const host = n.parentElement;
      if (!host || host.closest('script, style, template, noscript')) continue;
      const cs = getComputedStyle(host);
      if (cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) continue;
      const range = document.createRange();
      range.selectNodeContents(n);
      for (const r of range.getClientRects()) {
        if (r.width < 1 || r.height < 1) continue;
        texts.push({ text: n.nodeValue.trim().slice(0, 40), left: r.left, right: r.right, top: r.top, bottom: r.bottom });
      }
    }
    const clash = button ? texts.filter(t => t.right > button.left && t.left < button.right) : [];
    const overlapNow = button ? clash.filter(t => t.bottom > button.top && t.top < button.bottom) : [];
    const rightmost = texts.reduce((m, t) => Math.max(m, t.right), 0);
    return {
      footer: true,
      button,
      textBoxes: texts.length,
      rightmostText: Math.round(rightmost),
      gap: button ? Math.round(button.left - rightmost) : null,
      columnClash: clash.slice(0, 4).map(t => `${t.text} [${Math.round(t.left)}-${Math.round(t.right)}]`),
      overlapAtEnd: overlapNow.length,
    };
  });
}

// The footer's padding with the button in the page, and again with it taken out of the DOM for a moment (as on a page
// where the plugin does not print it: flag off, logged out, maintenance). Nothing is changed on the server.
const footerPadding = page => page.evaluate(() => {
  const px = (sel, prop) => {
    const el = document.querySelector(sel);
    return el ? Math.round(parseFloat(getComputedStyle(el)[prop])) : null;
  };
  const read = () => ({
    compactEnd: px('.airpay-footer__compact', 'paddingRight'),
    bandStart: px('.airpay-footer__product-attribution', 'paddingLeft'),
    bandEnd: px('.airpay-footer__product-attribution', 'paddingRight'),
  });
  const a = document.querySelector('#airpay-assistant');
  if (!a) return { withButton: null, withoutButton: read() };
  const withButton = read();
  const parent = a.parentNode;
  const next = a.nextSibling;
  a.remove();
  const withoutButton = read();
  parent.insertBefore(a, next);
  return { withButton, withoutButton };
});

// ── sidebar ──────────────────────────────────────────────────────────────────────────────────────────────────────────
const sidebarFacts = page => page.evaluate(() => {
  const vis = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
  const box = el => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)];
  };
  const sb = document.querySelector('#ap-course-sidebar');
  const t = document.querySelector('#ap-course-sidebar-toggle');
  const main = document.querySelector('.ap-course-player__main');
  const scs = sb ? getComputedStyle(sb) : null;
  const shown = !!(sb && vis(sb) && scs.visibility !== 'hidden' && parseFloat(scs.opacity) > 0.05
    && sb.getBoundingClientRect().right > 0 && sb.getBoundingClientRect().width > 0);
  let cover = 0;
  let overMain = false;
  if (shown) {
    const r = sb.getBoundingClientRect();
    const w = Math.max(0, Math.min(r.right, innerWidth) - Math.max(r.left, 0));
    const h = Math.max(0, Math.min(r.bottom, innerHeight) - Math.max(r.top, 0));
    if (['fixed', 'absolute'].includes(scs.position)) cover = (w * h) / (innerWidth * innerHeight);
    if (main) {
      const m = main.getBoundingClientRect();
      overMain = r.left < m.right && r.right > m.left && r.top < m.bottom && r.bottom > m.top;
    }
  }
  return {
    width: innerWidth,
    editing: document.body.classList.contains('editing'),
    sidebar: !!sb,
    sidebarShown: shown,
    sidebarPosition: scs ? scs.position : null,
    sidebarBox: box(sb),
    mainBox: box(main),
    coversWindowPct: Math.round(cover * 100),
    overMain,
    toggle: !!t,
    toggleShown: vis(t),
    toggleTag: t ? t.tagName.toLowerCase() : null,
    toggleType: t ? t.getAttribute('type') : null,
    expanded: t ? t.getAttribute('aria-expanded') : null,
    controls: t ? t.getAttribute('aria-controls') : null,
    toggleName: t ? t.textContent.trim().replace(/\s+/g, ' ') : null,
  };
});

// The keyboard focus ring on the toggle: focus it from the keyboard (Shift+Tab, Tab) so :focus-visible applies.
const focusRing = async page => {
  await page.focus('#ap-course-sidebar-toggle');
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(400);
  return page.evaluate(() => {
    const t = document.querySelector('#ap-course-sidebar-toggle');
    const cs = getComputedStyle(t);
    return {
      focused: document.activeElement === t,
      focusVisible: t.matches(':focus-visible'),
      ring: (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || cs.boxShadow !== 'none',
    };
  });
};

// WCAG contrast of the toggle's label against the toggle's own background, as rendered.
const toggleContrast = page => page.evaluate(() => {
  const t = document.querySelector('#ap-course-sidebar-toggle');
  const label = t && t.querySelector('span');
  if (!label) return null;
  const lum = s => {
    const [r, g, b] = s.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const fg = getComputedStyle(label).color;
  const bg = getComputedStyle(t).backgroundColor;
  const a = lum(fg);
  const b = lum(bg);
  return {
    theme: document.documentElement.getAttribute('data-theme'),
    hovered: t.matches(':hover'),
    fg,
    bg,
    ratio: Math.round(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)) * 100) / 100,
  };
});

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

// Load the course page fresh at a given window size, so what is checked is the state the page starts in.
async function freshAt(page, w, h) {
  await page.setViewportSize({ width: w, height: h });
  await go(page, COURSE);
  await page.waitForTimeout(1200);
}

async function sidebarRun(page, persona, label, num) {
  facts[persona] = {};
  // Phone and tablet-portrait widths, where the sidebar used to float over the content.
  for (const [w, h, shoot] of [[590, 1000, true], [390, 844, true], [768, 1024, false]]) {
    await freshAt(page, w, h);
    const onload = await sidebarFacts(page);
    facts[persona][`${w}-onload`] = onload;
    if (shoot) await page.screenshot({ path: file(`${num}-${label}-${w}-onload`) });
    // The main column must still wrap its content (the floated #region-main): a 0-high column lets later boxes overlap.
    const mainHolds = f => !!(f.mainBox && f.mainBox[3] > 0);
    const collapsed = onload.sidebar && onload.toggleShown && onload.toggleTag === 'button' && onload.toggleType === 'button'
      && onload.expanded === 'false' && onload.controls === 'ap-course-sidebar' && !onload.sidebarShown && mainHolds(onload);
    record(`sidebar ${persona} ${w}px: collapsed on load, toggle present`, collapsed, onload);
    if (!onload.toggleShown) continue;

    // Open with a click.
    await page.click('#ap-course-sidebar-toggle');
    await page.waitForTimeout(800);
    const opened = await sidebarFacts(page);
    facts[persona][`${w}-opened`] = opened;
    if (shoot) await page.screenshot({ path: file(`${num}-${label}-${w}-opened`) });
    record(`sidebar ${persona} ${w}px: opens on click, aria-expanded true, not over the content`,
      opened.expanded === 'true' && opened.sidebarShown && !opened.overMain && opened.coversWindowPct === 0 && mainHolds(opened),
      opened);

    // Close and reopen from the keyboard, with a visible focus ring.
    const ring = await focusRing(page);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(600);
    const closedByKey = await sidebarFacts(page);
    await page.keyboard.press('Space');
    await page.waitForTimeout(600);
    const reopenedByKey = await sidebarFacts(page);
    record(`sidebar ${persona} ${w}px: keyboard (Enter closes, Space opens), focus ring visible`,
      ring.focused && ring.focusVisible && ring.ring
        && closedByKey.expanded === 'false' && !closedByKey.sidebarShown
        && reopenedByKey.expanded === 'true' && reopenedByKey.sidebarShown,
      { ring, afterEnter: closedByKey.expanded, afterSpace: reopenedByKey.expanded });

    // The B shortcut, pressed while a list link has focus, never loses focus to <body>. The page script registers the
    // B handler only when an #ap-sidebar-toggle exists (unchanged): view mode always has one (the shell sidebar's
    // button); edit mode has one only with the progress bar (courses with completion tracking). Where B is wired it
    // closes the list and focus moves to the toggle; where it is not, nothing happens and focus stays on the link.
    const bName = `sidebar ${persona} ${w}px: B from a list link keeps focus (closes and focuses the toggle where wired)`;
    const link = page.locator('#ap-course-sidebar a[href]').first();
    if (PHASE === 'after' && reopenedByKey.sidebarShown && await link.count()) {
      await link.focus();
      const onLink = await page.evaluate(() => !!document.activeElement.closest('#ap-course-sidebar'));
      const wired = await page.evaluate(() => !!document.getElementById('ap-sidebar-toggle'));
      await page.keyboard.press('b');
      await page.waitForTimeout(600);
      const afterB = await sidebarFacts(page);
      const focus = await page.evaluate(() => {
        const a = document.activeElement;
        return {
          onToggle: !!a && a.id === 'ap-course-sidebar-toggle',
          inList: !!a && !!a.closest('#ap-course-sidebar'),
          onBody: !a || a === document.body,
        };
      });
      const ok = onLink && !focus.onBody && (wired
        ? afterB.expanded === 'false' && !afterB.sidebarShown && focus.onToggle
        : afterB.expanded === 'true' && afterB.sidebarShown && focus.inList);
      record(bName, ok, { onLink, wired, afterB: afterB.expanded, focus });
    } else if (PHASE === 'after') {
      record(bName, false, { reason: 'list not open or no link in it' });
    }
  }

  // Desktop: beside the content, no toggle, same boxes as before the change.
  await freshAt(page, 1440, 900);
  const desk = await sidebarFacts(page);
  facts[persona]['1440'] = desk;
  await page.screenshot({ path: file(`${num}-${label}-1440`) });
  let same = true;
  let compared = 'no facts-before.json';
  const beforeFile = path.join(OUT, 'facts-before.json');
  if (PHASE === 'after' && fs.existsSync(beforeFile)) {
    const before = JSON.parse(fs.readFileSync(beforeFile, 'utf8'));
    const b = before.sidebar && before.sidebar[persona] && before.sidebar[persona]['1440'];
    if (b) {
      const near = (x, y) => !!(x && y) && x.every((v, i) => Math.abs(v - y[i]) <= 1);
      same = near(desk.sidebarBox, b.sidebarBox) && near(desk.mainBox, b.mainBox) && desk.sidebarPosition === b.sidebarPosition;
      compared = { before: { sidebarBox: b.sidebarBox, mainBox: b.mainBox, position: b.sidebarPosition } };
    }
  }
  record(`sidebar ${persona} 1440px: beside the content as before, no toggle shown`,
    desk.sidebarShown && desk.sidebarPosition === 'sticky' && !desk.toggleShown && !desk.overMain && same,
    { ...desk, compared });
}

const browser = await launch();
try {
  if (want('footer')) {
    facts.footer = {};
    // The local site holds a test import of the production backup, so the Manage Users list shows real people: its
    // shots mask everything in the content scroller above the footer (the footer is what they are for).
    for (const [persona, url, name, mask] of [
      ['vp_admin1', '/local/sentientia_users/index.php', '01-admin-manage-users-footer-end', '#ap-shell-content > :not(#page-footer)'],
      ['vp_learner1', COURSE, '02-learner-course-footer-end', null],
    ]) {
      const { context, page } = await session(browser, persona);
      await go(page, url);
      facts.footer[persona] = {};
      for (const [w, h] of FOOTER_SIZES) {
        await page.setViewportSize({ width: w, height: h });
        await page.waitForTimeout(900);
        const f = await footerClearance(page);
        facts.footer[persona][`${w}x${h}`] = f;
        record(`footer ${persona} ${w}x${h}: no footer text under the help button`,
          f.footer && !!f.button && f.columnClash.length === 0 && f.overlapAtEnd === 0, f);
        if (PHASE === 'after') {
          const pad = await footerPadding(page);
          const def = footerDefault(w);
          const wo = pad.withoutButton;
          f.padding = pad;
          record(`footer ${persona} ${w}x${h}: without the button the footer keeps its default padding`,
            !!pad.withButton && wo.compactEnd === def.compactEnd && wo.bandStart === def.band && wo.bandEnd === def.band,
            { expected: def, ...pad });
        }
        if (SHOT_SIZES.includes(`${w}x${h}`)) {
          await page.screenshot({ path: file(`${name}-${w}x${h}`), ...(mask ? { mask: [page.locator(mask)], maskColor: '#CBD5E1' } : {}) });
        }
      }
      if (page.errs.length) record(`console: ${persona} ${url}`, false, page.errs);
      await context.close();
    }
  }
  if (want('sidebar')) {
    facts.sidebar = {};
    {
      const { context, page } = await session(browser, 'vp_learner1');
      await sidebarRun(page, 'vp_learner1', 'learner-course-view', '03');
      if (page.errs.length) record('console: vp_learner1 course view', false, page.errs);
      await context.close();
    }
    {
      const { context, page } = await session(browser, 'vp_courseauthor1');
      await go(page, COURSE);
      const wasOn = await page.locator(SWITCH).first().isChecked().catch(() => false);
      const can = await setEditing(page, true);
      record('edit mode on for vp_courseauthor1', can, { wasOn });
      await sidebarRun(page, 'vp_courseauthor1', 'courseauthor-course-edit', '04');
      const editing = facts.vp_courseauthor1;
      if (page.errs.length) record('console: vp_courseauthor1 course edit mode', false, page.errs);
      if (!wasOn) {
        await go(page, COURSE);
        await setEditing(page, false);
      }
      facts.sidebar.vp_courseauthor1 = editing;
      await context.close();
    }
    facts.sidebar.vp_learner1 = facts.vp_learner1;
    delete facts.vp_learner1;
    delete facts.vp_courseauthor1;
  }
  if (want('dark') && PHASE === 'after') {
    // The toggle's label contrast at 390 px, opened, in light and dark mode, hovered (the worst case: on a touch screen a
    // tapped button can keep :hover) and at rest. Dark mode is opt-in per browser (localStorage) and the ux.darkMode
    // flag's kill switch may force light on this site; flags are not changed here, so the opted-in state is applied in
    // the page the way head.mustache applies it (data-theme="dark" on <html>, .dark-mode on <html> and <body>).
    facts.dark = {};
    const { context, page } = await session(browser, 'vp_learner1');
    await freshAt(page, 390, 844);
    if (await page.locator('#ap-course-sidebar-toggle').isVisible()) {
      await page.click('#ap-course-sidebar-toggle');
      await page.waitForTimeout(800);
      await page.hover('#ap-course-sidebar-toggle');
      await page.waitForTimeout(500);
      const lightHover = await toggleContrast(page);
      const killswitch = await page.evaluate(() => !!document.getElementById('airpay-darkmode-killswitch-js'));
      await page.evaluate(() => {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
      });
      await page.mouse.move(2, 842);
      await page.waitForTimeout(500);
      const darkRest = await toggleContrast(page);
      await page.hover('#ap-course-sidebar-toggle');
      await page.waitForTimeout(500);
      const darkHover = await toggleContrast(page);
      await page.screenshot({ path: file('05-learner-course-view-390-dark-opened-hover') });
      facts.dark = { darkModeKillSwitchInPage: killswitch, lightHover, darkRest, darkHover };
      const ok = c => !!c && c.ratio >= 4.5;
      record('dark vp_learner1 390px: toggle label contrast >= 4.5:1 (light hover, dark rest, dark hover)',
        ok(lightHover) && lightHover.hovered && ok(darkRest) && darkRest.theme === 'dark' && !darkRest.hovered
          && ok(darkHover) && darkHover.hovered,
        facts.dark);
    } else {
      record('dark vp_learner1 390px: toggle label contrast >= 4.5:1 (light hover, dark rest, dark hover)', false,
        { reason: 'toggle not visible' });
    }
    if (page.errs.length) record('console: vp_learner1 course dark', false, page.errs);
    await context.close();
  }
} finally {
  await browser.close();
}
// With --only, keep the other part's facts from an earlier run of the same phase.
const factsFile = path.join(OUT, `facts-${PHASE}.json`);
const merged = ONLY && fs.existsSync(factsFile) ? { ...JSON.parse(fs.readFileSync(factsFile, 'utf8')), ...facts } : facts;
fs.writeFileSync(factsFile, JSON.stringify(merged, null, 1));
const failed = results.filter(r => !r.ok).length;
console.log(`${PHASE}: ${results.length - failed} passed, ${failed} failed; shots and facts-${PHASE}.json in ${OUT}`);
process.exit(failed ? 1 : 0);
