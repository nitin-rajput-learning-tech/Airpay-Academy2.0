// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY evidence for theme 2026111601 (2026-11-16, "fix Seen but not fixed now"): four course-page defects seen in
// the morning's demo-quick-wins evidence (docs/visual-evidence/2026-11-16/demo-quick-wins/). Course 448; personas
// vp_courseauthor1 (edit mode, switched back off at the end if it was off) and vp_learner1 (view mode).
//   A  dark banner     in dark mode the course title on the banner's title card reads >= 4.5:1 against the card at 390
//                      and 1440 px, view and edit mode. A translucent card is composited over every stop of the banner
//                      gradient beneath it and the worst ratio counts. In light mode the card's computed colours equal
//                      the BEFORE run's.
//   B  group-mode      at 390 and 590 px in edit mode the "No groups" badge shows its icon and text on one line inside
//                      the button, which no longer runs into the activity's actions menu; at 1440 px the icon-only
//                      button's box equals BEFORE within 1 px.
//   C  header icon     at 390, 590 and 768 px the course header's icon toolbar sits inside the banner, and in edit mode
//                      it does not touch the Bulk actions row (>= 8 px between the boxes); at 1440 px the banner, icon
//                      and row boxes equal BEFORE within 1 px. View mode (no Bulk actions) checks the icon inside the
//                      banner with >= 8 px to its bottom edge.
//   D  blocks tab      at 390 and 590 px in edit mode the blocks-drawer tab does not intersect an activity card, a
//                      course section, any other control, the title card or the assistant button, is at least 44x44
//                      and still opens (and closes) the blocks drawer; at 1440 px its box equals BEFORE within 1 px. An
//                      activity page in edit mode at 390 px is checked for the same overlap (the tab prints there too).
//                      A long course title (set in the page only, then put back) keeps its card clear of the tab and
//                      of its focus ring.
//                      Review fixes (same day): the tab's keyboard focus ring shows on the banner (an outline, >= 3:1
//                      on every banner stop, light and dark) and its scroll-margin keeps the navbar's height clear;
//                      Shift+Tab back onto the tab after scrolling leaves it below the navbar. /course/section.php
//                      (body.path-course-view too, but no banner) keeps the fixed edge tab, >= 44x44, over no control
//                      (the moved tab covered the section's Actions menu there).
//                      Learner view prints no tab; the dashboard prints one only behind a flag (not changed here).
//   console           no console errors on any page.
//
//   node theme_1116b_checks.mjs [--phase before|after] [--out <dir>] [--course 448] [--only A|B|C|D]
//
// --phase before captures the state before the change (A-D are expected to fail) and writes facts-before.json;
// --phase after (default) runs the same checks and compares the 1440 px boxes with facts-before.json. The blocks
// drawer is opened only below 991 px, where drawers.js stores no user preference. Exit 1 when a check fails.
import fs from 'node:fs';
import path from 'node:path';
import { HERE, go, launch, opt, session } from './evidence_lib.mjs';

const PHASE = opt('--phase', 'after');
if (!['before', 'after'].includes(PHASE)) {
  console.error('--phase must be before or after');
  process.exit(2);
}
const ONLY = opt('--only', '');
if (ONLY && !['A', 'B', 'C', 'D'].includes(ONLY)) {
  console.error('--only must be A, B, C or D');
  process.exit(2);
}
const want = s => !ONLY || ONLY === s;
const OUT = opt('--out', path.resolve(HERE, '../../docs/visual-evidence/2026-11-16/edit-mode-phone-dark-banner'));
fs.mkdirSync(OUT, { recursive: true });
const COURSE = `/course/view.php?id=${opt('--course', '448')}`;
const SWITCH = 'form.editmode-switch-form input[type="checkbox"]';
const SIZES = [[390, 844], [590, 1000], [768, 1024], [1440, 900]];
const TAB = '.drawer-toggles .drawer-right-toggle .btn';
const DRAWER = '#theme_sentientia-drawers-blocks';
const results = [];
const facts = { edit: {}, view: {}, incourse: {}, section: {}, dashboard: {} };
const record = (issue, name, ok, detail) => {
  if (!want(issue)) return;
  results.push({ issue, name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${issue}  ${name}  ${JSON.stringify(detail)}`);
};
const recordAny = (name, ok, detail) => {
  results.push({ issue: '-', name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  -  ${name}  ${JSON.stringify(detail)}`);
};
const file = name => path.join(OUT, `${PHASE}-${name}.png`);
const beforeFile = path.join(OUT, 'facts-before.json');
const BEFORE = PHASE === 'after' && fs.existsSync(beforeFile) ? JSON.parse(fs.readFileSync(beforeFile, 'utf8')) : null;
const near = (a, b, tol = 1) => !!(a && b) && a.length === b.length && a.every((v, i) => Math.abs(v - b[i]) <= tol);
// Boxes are [left, top, width, height].
const intersects = (a, b) => !!(a && b) && a[0] < b[0] + b[2] && b[0] < a[0] + a[2] && a[1] < b[1] + b[3] && b[1] < a[1] + a[3];
const gap = (a, b) => Math.max(0, b[0] - (a[0] + a[2]), a[0] - (b[0] + b[2]), b[1] - (a[1] + a[3]), a[1] - (b[1] + b[3]));

// Everything the four checks read, from one load of the page. Runs in the page.
const pageFacts = (page, tab) => page.evaluate(TABSEL => {
  const box = el => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)];
  };
  const shown = el => {
    if (!el) return false;
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05 && r.width > 0 && r.height > 0;
  };
  // WCAG 2.x contrast. A translucent background is composited over every stop of the banner gradient beneath the card
  // (the worst ratio counts), so a light card over a dark banner cannot pass by luck.
  const rgba = s => {
    const n = (String(s).match(/[\d.]+/g) || []).map(Number);
    return n.length >= 3 ? [n[0], n[1], n[2], n.length >= 4 ? n[3] : 1] : null;
  };
  const lum = c => {
    const [r, g, b] = c.slice(0, 3).map(v => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const ratio = (a, b) => {
    const x = lum(a);
    const y = lum(b);
    return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100;
  };
  const over = (top, under) => [0, 1, 2].map(i => top[i] * top[3] + under[i] * (1 - top[3])).concat(1);

  const ch = document.querySelector('#page-header .courseheader');
  const hdg = document.querySelector('#page-header .page-header-headings');
  const h1 = hdg && hdg.querySelector('h1');
  let A = null;
  if (hdg && h1) {
    const bg = getComputedStyle(hdg).backgroundColor;
    const fg = getComputedStyle(h1).color;
    const stops = ch ? (getComputedStyle(ch, '::before').backgroundImage.match(/rgba?\([^)]+\)/g) || []) : [];
    const card = rgba(bg);
    const text = rgba(fg);
    const unders = stops.map(rgba).filter(Boolean);
    const composites = card[3] >= 0.999 || !unders.length ? [card] : unders.map(u => over(card, [u[0], u[1], u[2], 1]));
    A = {
      theme: document.documentElement.getAttribute('data-theme'),
      darkClasses: document.documentElement.classList.contains('dark-mode') && document.body.classList.contains('dark-mode'),
      title: h1.textContent.trim().slice(0, 60),
      bg,
      fg,
      cardAlpha: card[3],
      stops,
      ratio: Math.min(...composites.map(c => ratio(text, c))),
      box: box(hdg),
    };
  }

  // B: every interactive group-mode button on the page (core groupmode.mustache: a <button> in .activity-groupmode-info).
  const B = [...document.querySelectorAll('.activity-groupmode-info button.groupmode-information')].map(btn => {
    const icon = btn.querySelector('.icon');
    const info = btn.querySelector('.groupmode-icon-info');
    const item = btn.closest('.activity-item');
    const trigger = item && item.querySelector('.activity-actions [data-toggle="dropdown"], .activity-actions [data-bs-toggle="dropdown"], .activity-actions .dropdown-toggle');
    let lines = 0;
    let textBox = null;
    if (info && shown(info)) {
      const range = document.createRange();
      range.selectNodeContents(info);
      const rects = [...range.getClientRects()].filter(r => r.width > 0 && r.height > 0);
      lines = new Set(rects.map(r => Math.round(r.top / 4))).size;
      const r = range.getBoundingClientRect();
      textBox = [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)];
    }
    const cs = getComputedStyle(btn);
    return {
      text: info ? info.textContent.trim() : null,
      textShown: !!(info && shown(info)),
      lines,
      textBox,
      iconBox: box(icon),
      btnBox: box(btn),
      wrapBox: box(btn.closest('.activity-groupmode-info')),
      triggerBox: box(trigger),
      overflowY: btn.scrollHeight - btn.clientHeight,
      overflowX: btn.scrollWidth - btn.clientWidth,
      borderRadius: cs.borderRadius,
      display: cs.display,
    };
  });

  // C: the icon toolbar inside the banner, the Bulk actions row after it.
  const icons = ch ? [...ch.querySelectorAll('.course_extended_menu_itemlink')].filter(shown) : [];
  const ha = document.querySelector('#page-header .courseheader + .header-actions-container');
  const C = {
    bannerBox: box(ch),
    bannerHeight: ch ? getComputedStyle(ch).height : null,
    iconBoxes: icons.map(box),
    iconTitles: icons.map(a => a.getAttribute('title') || a.textContent.trim()),
    rowBox: box(ha),
    bulkBox: shown(document.querySelector('.bulkEnable')) ? box(document.querySelector('.bulkEnable')) : null,
    menuFloat: ch && ch.querySelector('.context-header-settings-menu') ? getComputedStyle(ch.querySelector('.context-header-settings-menu')).float : null,
  };

  // D: the blocks-drawer tab, what it could cover, and the assistant button.
  const tabBtn = document.querySelector(TABSEL);
  const tabWrap = tabBtn && tabBtn.closest('.drawer-right-toggle');
  const tabShown = shown(tabBtn);
  const skip = el => el.closest('.drawer-toggles, .navbar, .fixed-top, nav.ap-mobile-nav, .airpay-mobile-nav, .drawer, .airpay-assistant, #page-footer');
  const obstacles = [];
  const controls = [];
  // Controls a 36 px tab at the same place covers too: Boost's fixed edge tab before 2026-11-16 (pre-existing).
  const controls36 = [];
  const CONTROL = 'a[href], button, input, select, textarea, [role="button"], .dropdown-toggle';
  if (tabShown) {
    const t = tabBtn.getBoundingClientRect();
    const hit = r => r.left < t.right && t.left < r.right && r.top < t.bottom && t.top < r.bottom;
    const hit36 = r => r.left < t.right && t.right - 36 < r.right && r.top < t.bottom && t.top < r.bottom;
    const cands = document.querySelectorAll(`.activity-item, li.section, .course-section, .page-header-headings, ${CONTROL}, h1, h2`);
    for (const el of cands) {
      if (skip(el) || !shown(el)) continue;
      const r = el.getBoundingClientRect();
      if (hit(r)) {
        const label = `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}.${String(el.className).trim().split(/\s+/).slice(0, 2).join('.')} [${Math.round(r.left)},${Math.round(r.top)},${Math.round(r.width)},${Math.round(r.height)}]`;
        obstacles.push(label);
        if (el.matches(CONTROL)) controls.push(label);
        if (el.matches(CONTROL) && hit36(r)) controls36.push(label);
      }
    }
  }
  const tcs = tabWrap ? getComputedStyle(tabWrap) : null;
  // The tab is the topmost element at its own centre (nothing paints over it).
  let onTop = null;
  if (tabShown) {
    const t = tabBtn.getBoundingClientRect();
    const hitEl = document.elementFromPoint(t.left + t.width / 2, t.top + t.height / 2);
    onTop = !!hitEl && (hitEl === tabBtn || tabBtn.contains(hitEl));
  }
  const D = {
    onTop,
    printed: !!tabBtn,
    shown: tabShown,
    tabBox: box(tabBtn),
    position: tcs ? tcs.position : null,
    zIndex: tcs ? tcs.zIndex : null,
    assistantBox: shown(document.querySelector('.airpay-assistant__toggle')) ? box(document.querySelector('.airpay-assistant__toggle')) : null,
    obstacles: obstacles.slice(0, 8),
    obstacleCount: obstacles.length,
    controls: controls.slice(0, 8),
    controlCount: controls.length,
    controls36: controls36.slice(0, 8),
    cards: [...document.querySelectorAll('.activity-item')].slice(0, 3).map(box),
    sections: [...document.querySelectorAll('li.section')].slice(0, 2).map(box),
    scrollY: Math.round(window.scrollY),
  };
  return {
    width: innerWidth,
    height: innerHeight,
    editing: document.body.classList.contains('editing'),
    pathClasses: [...document.body.classList].filter(c => c.startsWith('path-')),
    banner: !!ch,
    A,
    B,
    C,
    D,
  };
}, tab || TAB);

const applyDark = page => page.evaluate(() => {
  // Dark mode is opt-in per browser (localStorage) and the ux.darkMode flag is not changed here, so the opted-in state
  // is applied in the page the way head.mustache applies it: data-theme="dark" and .dark-mode on <html> and <body>.
  document.documentElement.setAttribute('data-theme', 'dark');
  document.documentElement.classList.add('dark-mode');
  document.body.classList.add('dark-mode');
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

async function freshAt(page, w, h, url = COURSE) {
  await page.setViewportSize({ width: w, height: h });
  await go(page, url);
  await page.waitForTimeout(1200);
}

// A clip of the viewport around a box (kept inside the viewport).
async function clipShot(page, name, b, pad = 12) {
  if (!b) return;
  const vp = page.viewportSize();
  const x = Math.max(0, b[0] - pad);
  const y = Math.max(0, b[1] - pad);
  const w = Math.min(vp.width - x, b[2] + pad * 2);
  const h = Math.min(vp.height - y, b[3] + pad * 2);
  if (w > 10 && h > 10) await page.screenshot({ path: file(name), clip: { x, y, width: w, height: h } });
}

const before = (mode, w) => BEFORE && BEFORE[mode] && BEFORE[mode][w];

// ── A ────────────────────────────────────────────────────────────────────────────────────────────────────────────────
function checkALight(mode, persona, w, f) {
  const b = before(mode, w);
  const same = !b || !b.A || (f.A && f.A.bg === b.A.bg && f.A.fg === b.A.fg);
  record('A', `${persona} ${mode} ${w}px light: title card colours as before`, !!f.A && same,
    { now: f.A && { bg: f.A.bg, fg: f.A.fg, ratio: f.A.ratio }, before: b && b.A ? { bg: b.A.bg, fg: b.A.fg } : 'no facts-before.json' });
}
function checkADark(mode, persona, w, a) {
  record('A', `${persona} ${mode} ${w}px dark: title >= 4.5:1 on its card`, !!a && a.theme === 'dark' && a.ratio >= 4.5, a);
}

// ── B ────────────────────────────────────────────────────────────────────────────────────────────────────────────────
function checkB(persona, w, f) {
  if (w === 1440) {
    const b = before('edit', w);
    const bb = b && b.B;
    const ok = f.B.length > 0 && f.B.every((g, i) => !g.textShown && g.btnBox[2] === 36 && g.btnBox[3] === 36
      && (!bb || (bb[i] && near(g.btnBox, bb[i].btnBox) && near(g.wrapBox, bb[i].wrapBox))));
    record('B', `${persona} edit ${w}px: icon-only group-mode button unchanged`, ok,
      { now: f.B.map(g => ({ btn: g.btnBox, wrap: g.wrapBox, textShown: g.textShown })), before: bb ? bb.map(g => ({ btn: g.btnBox, wrap: g.wrapBox })) : 'no facts-before.json' });
    return;
  }
  const bad = f.B.filter(g => {
    if (!g.textShown || !g.textBox || !g.iconBox) return true;
    const sameLine = g.lines === 1 && g.textBox[1] >= g.iconBox[1] - 2 && g.textBox[1] <= g.iconBox[1] + g.iconBox[3];
    const inside = g.textBox[0] + g.textBox[2] <= g.btnBox[0] + g.btnBox[2] + 1 && g.textBox[1] + g.textBox[3] <= g.btnBox[1] + g.btnBox[3] + 1;
    const oneLineHigh = g.btnBox[3] <= 40 && g.overflowY <= 1;
    const clearOfMenu = !g.triggerBox || !intersects(g.btnBox, g.triggerBox);
    return !(sameLine && inside && oneLineHigh && clearOfMenu);
  });
  record('B', `${persona} edit ${w}px: "No groups" icon and text on one line, inside the button, clear of the actions menu`,
    f.B.length > 0 && bad.length === 0, { badges: f.B.length, failing: bad.slice(0, 2), first: f.B[0] });
}

// ── C ────────────────────────────────────────────────────────────────────────────────────────────────────────────────
function checkC(mode, persona, w, f) {
  const c = f.C;
  if (w === 1440) {
    const b = before(mode, w);
    const bc = b && b.C;
    const ok = !!c.bannerBox && (!bc || (near(c.bannerBox, bc.bannerBox) && near(c.iconBoxes[0], bc.iconBoxes[0])
      && near(c.rowBox, bc.rowBox) && (mode !== 'edit' || near(c.bulkBox, bc.bulkBox))));
    record('C', `${persona} ${mode} ${w}px: banner, icon and Bulk actions boxes as before`, ok,
      { now: c, before: bc || 'no facts-before.json' });
    return;
  }
  const ban = c.bannerBox;
  const issues = [];
  if (!ban || !c.iconBoxes.length) issues.push('no banner or no icon');
  for (const ic of c.iconBoxes) {
    const inside = ic[0] >= ban[0] && ic[1] >= ban[1] && ic[0] + ic[2] <= ban[0] + ban[2] && ic[1] + ic[3] <= ban[1] + ban[3];
    if (!inside) issues.push(`icon ${JSON.stringify(ic)} not inside the banner ${JSON.stringify(ban)}`);
    if (mode === 'edit') {
      for (const [label, other] of [['Bulk actions row', c.rowBox], ['Bulk actions button', c.bulkBox]]) {
        if (!other || other[3] === 0) {
          issues.push(`no ${label}`);
          continue;
        }
        if (intersects(ic, other)) issues.push(`icon intersects the ${label}`);
        else if (gap(ic, other) < 8) issues.push(`icon ${gap(ic, other)} px from the ${label}`);
      }
    } else if (ban[1] + ban[3] - (ic[1] + ic[3]) < 8) {
      issues.push(`icon ${ban[1] + ban[3] - (ic[1] + ic[3])} px from the banner's bottom edge`);
    }
  }
  record('C', `${persona} ${mode} ${w}px: header icon inside the banner${mode === 'edit' ? ', >= 8 px from Bulk actions' : ''}`,
    issues.length === 0, { issues, ...c });
}

// ── D ────────────────────────────────────────────────────────────────────────────────────────────────────────────────
function checkD(where, persona, w, f) {
  const d = f.D;
  if (w === 1440) {
    const b = before(where, w);
    const bd = b && b.D;
    record('D', `${persona} ${where} ${w}px: blocks tab box as before`, d.shown && (!bd || near(d.tabBox, bd.tabBox)),
      { now: d.tabBox, position: d.position, before: bd ? bd.tabBox : 'no facts-before.json' });
    return;
  }
  const t = d.tabBox;
  const issues = [];
  if (!d.shown) issues.push('tab not shown');
  else {
    if (t[2] < 44 || t[3] < 44) issues.push(`tap target ${t[2]}x${t[3]}`);
    if (d.obstacleCount) issues.push(`over ${d.obstacleCount} content box(es)`);
    if (d.assistantBox && intersects(t, d.assistantBox)) issues.push('over the assistant button');
    if (d.onTop === false) issues.push('another element paints over the tab');
    if (t[0] < 0 || t[1] < 0 || t[0] + t[2] > f.width || t[1] + t[3] > f.height) issues.push('not fully on screen at load');
  }
  record('D', `${persona} ${where} ${w}px: blocks tab clear of the content and the assistant, >= 44x44`, issues.length === 0,
    { issues, tab: t, position: d.position, assistant: d.assistantBox, obstacles: d.obstacles });
}

// A long course title (set in the page only, nothing is saved, the text is put back) must not run under the tab: real
// course names are often two lines long at phone width, and course 448's is short.
async function longTitle(page, persona, w) {
  const LONG = 'Anti-Money Laundering and Know Your Customer Compliance Refresher 2026';
  const r = await page.evaluate(([T, text]) => {
    const h1 = document.querySelector('#page-header .page-header-headings h1');
    const tab = document.querySelector(T);
    if (!h1 || !tab) return null;
    const keep = h1.textContent;
    h1.textContent = text;
    const box = el => { const b = el.getBoundingClientRect(); return [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)]; };
    const root = getComputedStyle(document.documentElement);
    const ring = parseFloat(root.getPropertyValue('--ap-focus-ring-width')) + parseFloat(root.getPropertyValue('--ap-focus-ring-offset'));
    const out = { card: box(h1.closest('.page-header-headings')), tab: box(tab), ring };
    h1.textContent = keep;
    return out;
  }, [TAB, LONG]);
  // The focus ring reaches `ring` px past the tab: the card's inline end must stay at or before that.
  const ok = !!r && !intersects(r.card, r.tab) && r.card[0] + r.card[2] <= r.tab[0] - r.ring + 1;
  record('D', `${persona} edit ${w}px: a long course title's card stays clear of the tab and its focus ring`,
    ok, r || { reason: 'no title or no tab' });
  return r;
}

// Review fix: the tab's keyboard focus ring must show on the banner (WCAG 2.4.7). Bootstrap's .btn:focus { outline: 0 }
// removed the theme ring and the blue box-shadow left cannot be seen on the blue banner: a real outline is required,
// reading >= 3:1 against every stop of the banner gradient behind the tab. The scroll-margin must keep the navbar clear.
async function tabFocus(page, persona, w, mode) {
  const name = `${persona} edit ${w}px ${mode}: tab keyboard focus ring visible on the banner (>= 3:1), scroll-margin clears the navbar`;
  if (!(await page.locator(TAB).first().isVisible().catch(() => false))) {
    record('D', name, false, { reason: 'tab not visible' });
    return null;
  }
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await page.keyboard.press('Tab');  // keyboard modality, so the focus() below matches :focus-visible as a Tab would
  const r = await page.evaluate(([T]) => {
    const rgba = s => { const n = (String(s).match(/[\d.]+/g) || []).map(Number); return n.length >= 3 ? n.slice(0, 3) : null; };
    const lum = c => {
      const [r, g, b] = c.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const ratio = (a, b) => { const x = lum(a); const y = lum(b); return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100; };
    const tab = document.querySelector(T);
    tab.focus();
    const cs = getComputedStyle(tab);
    const ch = document.querySelector('#page-header .courseheader');
    const stops = ch ? (getComputedStyle(ch, '::before').backgroundImage.match(/rgba?\([^)]+\)/g) || []) : [];
    const ring = rgba(cs.outlineColor);
    const nav = document.querySelector('nav.navbar.fixed-top');
    const b = tab.getBoundingClientRect();
    const cb = ch ? ch.getBoundingClientRect() : null;
    return {
      focused: document.activeElement === tab,
      focusVisible: tab.matches(':focus-visible'),
      outline: `${cs.outlineStyle} ${cs.outlineWidth} ${cs.outlineColor} offset ${cs.outlineOffset}`,
      outlineStyle: cs.outlineStyle,
      outlineWidth: parseFloat(cs.outlineWidth),
      stops,
      minRatio: ring && stops.length ? Math.min(...stops.map(s => ratio(ring, rgba(s)))) : 0,
      tabOnBanner: !!cb && b.top >= cb.top && b.bottom <= cb.bottom,
      tab: [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)],
      scrollMargin: parseFloat(cs.scrollMarginBlockStart || cs.scrollMarginTop),
      navbarBottom: nav ? Math.round(nav.getBoundingClientRect().bottom) : null,
    };
  }, [TAB]);
  const t = r.tab;
  await page.screenshot({ path: file(`10-courseauthor-edit-${w}-${mode}-tab-focus`), clip: { x: Math.max(0, t[0] - 60), y: Math.max(0, t[1] - 40), width: Math.min(w - Math.max(0, t[0] - 60), t[2] + 80), height: t[3] + 80 } });
  await page.evaluate(() => document.activeElement && document.activeElement.blur());
  const ok = r.focused && r.focusVisible && r.outlineStyle !== 'none' && r.outlineWidth >= 2 && r.minRatio >= 3 && r.tabOnBanner
    && r.navbarBottom !== null && r.scrollMargin >= r.navbarBottom;
  record('D', name, ok, r);
  return r;
}

// Review fix: after the page was scrolled, Shift+Tab back onto the tab (from the next focusable element, the banner's
// Reports link) must leave the tab fully below the fixed navbar. Chrome centres a focused element, so this guards the
// behaviour rather than proving the scroll-margin (engines that align the top edge rely on it).
async function shiftTabBack(page, persona, w) {
  const settle = async () => {
    let last = -1;
    for (let i = 0; i < 20; i++) {
      const y = await page.evaluate(() => Math.round(scrollY));
      if (y === last) return y;
      last = y;
      await page.waitForTimeout(250);
    }
    return last;
  };
  const prep = await page.evaluate(([T]) => {
    window.scrollTo({ top: 1200, behavior: 'instant' });
    const tab = document.querySelector(T);
    const all = [...document.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])')]
      .filter(el => { const cs = getComputedStyle(el); return cs.visibility !== 'hidden' && cs.display !== 'none' && el.getClientRects().length; });
    const next = all[all.indexOf(tab) + 1];
    if (!tab || !next) return null;
    next.focus({ preventScroll: true });
    return { next: `${next.tagName.toLowerCase()}.${String(next.className).split(/\s+/)[0]}` };
  }, [TAB]);
  const from = await settle();
  await page.keyboard.press('Shift+Tab');
  const to = await settle();
  const r = await page.evaluate(([T]) => {
    const tab = document.querySelector(T);
    const b = tab.getBoundingClientRect();
    const nav = document.querySelector('nav.navbar.fixed-top');
    return {
      focused: document.activeElement === tab,
      tab: [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)],
      navbarBottom: nav ? Math.round(nav.getBoundingClientRect().bottom) : null,
    };
  }, [TAB]);
  await page.evaluate(() => { if (document.activeElement) document.activeElement.blur(); window.scrollTo({ top: 0, behavior: 'instant' }); });
  await settle();
  const ok = !!prep && from > 0 && r.focused && r.navbarBottom !== null && r.tab[1] >= r.navbarBottom && r.tab[1] + r.tab[3] <= (page.viewportSize().height);
  record('D', `${persona} edit ${w}px: Shift+Tab back onto the tab after scrolling leaves it below the navbar`, ok, { ...prep, scrolledFrom: from, scrollAfter: to, ...r });
}

// Review fix: /course/section.php has body.path-course-view (pagetype course-view-section-*) but no banner. The tab
// stays the fixed edge tab there, at least 44x44 (the moved tab covered the section's Actions menu and the Course
// Content toggle). Fails when: the tab is not the fixed edge tab; it covers the Actions menu or the Course Content
// toggle; or it covers a control that a 36 px tab at the same place (Boost's fixed edge tab, before 2026-11-16) does
// not, so the 44 px minimum width added the overlap. A control that the 36 px tab covered as well is the pre-existing
// fixed-tab placement (open issue, as on activity pages): printed as a NOTE, not failed. So are activity cards under
// the tab's edge.
function checkSection(persona, w, f) {
  const d = f.D;
  const t = d.tabBox;
  const issues = [];
  const added = (d.controls || []).filter(c => !(d.controls36 || []).includes(c));
  const moved = (d.controls || []).filter(c => /#action-menu-toggle-|#ap-course-sidebar-toggle/.test(c));
  if (f.banner) issues.push('a banner is printed here (not expected on the section page)');
  if (!f.pathClasses.includes('path-course-view-section')) issues.push(`not the section page: ${f.pathClasses.join(' ')}`);
  if (!d.shown) issues.push('tab not shown');
  else {
    if (t[2] < 44 || t[3] < 44) issues.push(`tap target ${t[2]}x${t[3]}`);
    if (d.position !== 'fixed') issues.push(`position ${d.position} (expected the fixed edge tab)`);
    if (moved.length) issues.push(`over the Actions menu or the Course Content toggle: ${moved.join(', ')}`);
    if (added.length) issues.push(`over control(s) a 36 px tab would leave clear: ${added.join(', ')}`);
    if (d.assistantBox && intersects(t, d.assistantBox)) issues.push('over the assistant button');
    if (d.onTop === false) issues.push('another element paints over the tab');
    if (t[0] < 0 || t[1] < 0 || t[0] + t[2] > f.width || t[1] + t[3] > f.height) issues.push('not fully on screen at load');
  }
  const preexisting = (d.controls36 || []).filter(c => !moved.includes(c));
  record('D', `${persona} section page edit ${w}px: fixed edge tab, >= 44x44, not over the Actions menu or Course Content, no control added by the width`,
    issues.length === 0,
    { issues, tab: t, position: d.position, controls: d.controls, preexistingControls: preexisting, otherOverlap: d.obstacles.filter(o => !d.controls.includes(o)) });
  if (preexisting.length) {
    console.log(`NOTE  D  ${persona} section page edit ${w}px: the fixed edge tab covers ${preexisting.join(', ')} at load, as the 36 px tab did before 2026-11-16 (pre-existing placement, open issue)`);
  }
}

// Open the blocks drawer from the tab and close it again (below 991 px drawers.js stores no preference).
async function drawerClick(page, persona, w) {
  const out = {};
  const tab = page.locator(TAB).first();
  if (!(await tab.isVisible().catch(() => false))) {
    record('D', `${persona} edit ${w}px: tab opens and closes the blocks drawer`, false, { reason: 'tab not visible' });
    return out;
  }
  try {
    await tab.click({ timeout: 20000 });
  } catch (err) {
    record('D', `${persona} edit ${w}px: tab opens and closes the blocks drawer`, false,
      { reason: `click failed: ${String(err.message).split('\n')[0].slice(0, 160)}` });
    return out;
  }
  await page.waitForTimeout(1500);
  out.opened = await page.evaluate(([D]) => {
    const dr = document.querySelector(D);
    const r = dr ? dr.getBoundingClientRect() : null;
    return {
      drawerShow: !!(dr && dr.classList.contains('show')),
      pageState: document.querySelector('#page').classList.contains('show-drawer-right'),
      drawerOnScreen: !!r && r.left < innerWidth && r.right > 0 && r.width > 0,
    };
  }, [DRAWER]);
  await page.screenshot({ path: file(`07-courseauthor-edit-${w}-drawer-open`), mask: [page.locator(`${DRAWER} .drawercontent`)], maskColor: '#CBD5E1' });
  const close = page.locator(`${DRAWER} [data-toggler="drawers"][data-action="closedrawer"]`).first();
  if (await close.isVisible().catch(() => false)) await close.click({ timeout: 20000 }).catch(() => page.keyboard.press('Escape'));
  else await page.keyboard.press('Escape');
  await page.waitForTimeout(1500);
  out.closed = await page.evaluate(([D, T]) => {
    const dr = document.querySelector(D);
    const t = document.querySelector(T);
    return { drawerShow: !!(dr && dr.classList.contains('show')), focusOnTab: document.activeElement === t };
  }, [DRAWER, TAB]);
  record('D', `${persona} edit ${w}px: tab opens and closes the blocks drawer`,
    out.opened.drawerShow && out.opened.drawerOnScreen && !out.closed.drawerShow, out);
  return out;
}

const browser = await launch();
try {
  // ── Edit mode ──────────────────────────────────────────────────────────────────────────────────────────────────────
  {
    const persona = 'vp_courseauthor1';
    const { context, page } = await session(browser, persona);
    await go(page, COURSE);
    const wasOn = await page.locator(SWITCH).first().isChecked().catch(() => false);
    const can = await setEditing(page, true);
    recordAny(`edit mode on for ${persona}`, can, { wasOn });
    for (const [w, h] of SIZES) {
      await freshAt(page, w, h);
      const f = await pageFacts(page);
      facts.edit[w] = f;
      await page.screenshot({ path: file(`01-courseauthor-edit-${w}`) });
      if (w <= 590) {
        const g = f.B[0];
        if (g) await clipShot(page, `02-courseauthor-edit-${w}-groupmode`, f.D.cards[0] || g.wrapBox);
        const c = f.C;
        if (c.bannerBox) {
          const top = Math.max(0, c.bannerBox[1] + c.bannerBox[3] - 110);
          const bottom = (c.rowBox ? c.rowBox[1] + c.rowBox[3] : c.bannerBox[1] + c.bannerBox[3]) + 30;
          await clipShot(page, `03-courseauthor-edit-${w}-header-icon`, [0, top, w, bottom - top], 0);
        }
      }
      if (w === 1440 || w <= 590) checkALight('edit', persona, w, f);
      if (w <= 590 || w === 1440) checkB(persona, w, f);
      checkC('edit', persona, w, f);
      if (w !== 768) checkD('edit', persona, w, f);
      if (w <= 590) facts.edit[`${w}-longtitle`] = await longTitle(page, persona, w);
      if (w <= 590) facts.edit[`${w}-focus`] = await tabFocus(page, persona, w, 'light');
      if (w === 390) await shiftTabBack(page, persona, w);
      // Light mode, before the dark pass below changes the page.
      if (w <= 590) facts.edit[`${w}-drawer`] = await drawerClick(page, persona, w);
      if (w === 390 || w === 1440) {
        await applyDark(page);
        await page.mouse.move(2, h - 2);
        await page.waitForTimeout(600);
        const dark = await pageFacts(page);
        facts.edit[`${w}-dark`] = { A: dark.A };
        await page.screenshot({ path: file(`04-courseauthor-edit-${w}-dark`) });
        checkADark('edit', persona, w, dark.A);
        if (w === 390) facts.edit[`${w}-dark-focus`] = await tabFocus(page, persona, w, 'dark');
      }
    }
    // The tab prints on activity pages in edit mode too (course_editing.mustache): check one at 390 px.
    await freshAt(page, 390, 844);
    const href = await page.evaluate(() => {
      const a = document.querySelector('.activity-item a.aalink[href*="/mod/page/"]') || document.querySelector('.activity-item a.aalink[href*="/mod/"]');
      return a ? a.getAttribute('href') : null;
    });
    if (href) {
      await freshAt(page, 390, 844, href);
      const f = await pageFacts(page);
      facts.incourse[390] = { url: href.replace(/^https?:\/\/[^/]+/, ''), D: f.D, editing: f.editing };
      await page.screenshot({ path: file('08-courseauthor-activity-edit-390') });
      checkD('incourse', persona, 390, f);
    } else {
      record('D', `${persona} activity page 390px: found an activity to open`, false, { reason: 'no activity link' });
    }
    // The single-section page (review finding): body.path-course-view, no banner.
    await freshAt(page, 390, 844);
    const shref = await page.evaluate(() => {
      const a = document.querySelector('a[href*="/course/section.php?id="]');
      return a ? a.getAttribute('href') : null;
    });
    if (shref) {
      for (const [w, h] of [[390, 844], [590, 1000]]) {
        await freshAt(page, w, h, shref);
        const f = await pageFacts(page);
        facts.section[w] = { url: shref.replace(/^https?:\/\/[^/]+/, ''), pathClasses: f.pathClasses, banner: f.banner, D: f.D, editing: f.editing };
        await page.screenshot({ path: file(`09-courseauthor-section-edit-${w}`) });
        checkSection(persona, w, f);
      }
    } else {
      record('D', `${persona} section page: found a section link`, false, { reason: 'no /course/section.php link on the course page' });
    }
    if (page.errs.length) recordAny(`console: ${persona} course edit mode`, false, page.errs);
    else recordAny(`console: ${persona} course edit mode`, true, { errors: 0 });
    if (!wasOn) {
      await go(page, COURSE);
      await setEditing(page, false);
      const off = !(await page.locator(SWITCH).first().isChecked().catch(() => true));
      recordAny(`edit mode back off for ${persona}`, off, {});
    }
    await context.close();
  }

  // ── View mode ──────────────────────────────────────────────────────────────────────────────────────────────────────
  {
    const persona = 'vp_learner1';
    const { context, page } = await session(browser, persona);
    for (const [w, h] of SIZES) {
      await freshAt(page, w, h);
      const f = await pageFacts(page);
      facts.view[w] = f;
      await page.screenshot({ path: file(`05-learner-view-${w}`) });
      if (w === 1440 || w <= 590) checkALight('view', persona, w, f);
      checkC('view', persona, w, f);
      if (f.D.printed) checkD('view', persona, w, f);
      else if (w <= 590) record('D', `${persona} view ${w}px: no blocks tab printed in learner view`, true, { printed: false });
      if (w === 390 || w === 1440) {
        await applyDark(page);
        await page.mouse.move(2, h - 2);
        await page.waitForTimeout(600);
        const dark = await pageFacts(page);
        facts.view[`${w}-dark`] = { A: dark.A };
        await page.screenshot({ path: file(`06-learner-view-${w}-dark`) });
        checkADark('view', persona, w, dark.A);
      }
    }
    // The dashboard prints .drawer-toggles only with sentientia.dashboard.blocksdrawer.enabled (not changed here).
    for (const [w, h] of [[390, 844], [1440, 900]]) {
      await freshAt(page, w, h, '/my/');
      const d = await page.evaluate(() => ({ toggles: !!document.querySelector('.drawer-toggles'), dashToggle: !!document.querySelector('.ap-dashboard-blocks-toggle') }));
      facts.dashboard[w] = d;
      if (d.toggles) {
        const f = await pageFacts(page, '.drawer-toggles .drawer-toggler .btn');
        facts.dashboard[w].D = f.D;
        checkD('dashboard', persona, w, f);
      } else {
        console.log(`NOTE  D  ${persona} /my/ ${w}px: no .drawer-toggles printed (flag sentientia.dashboard.blocksdrawer.enabled off); nothing to check`);
      }
    }
    if (page.errs.length) recordAny(`console: ${persona} course view and dashboard`, false, page.errs);
    else recordAny(`console: ${persona} course view and dashboard`, true, { errors: 0 });
    await context.close();
  }
} finally {
  await browser.close();
}
const factsFile = path.join(OUT, `facts-${PHASE}.json`);
fs.writeFileSync(factsFile, JSON.stringify(facts, null, 1));
const failed = results.filter(r => !r.ok).length;
const byIssue = {};
for (const r of results) {
  byIssue[r.issue] = byIssue[r.issue] || { pass: 0, fail: 0 };
  byIssue[r.issue][r.ok ? 'pass' : 'fail']++;
}
console.log(`${PHASE}: ${results.length - failed} passed, ${failed} failed ${JSON.stringify(byIssue)}; shots and facts-${PHASE}.json in ${OUT}`);
process.exit(failed ? 1 : 0);
