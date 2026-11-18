// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY, READ-ONLY surface walk for one persona (2026-11-18, for the C-suite demo pass).
//
//   node surface_walk_1118.mjs --user=<username> [--out="D:/Claude Local/kit-scratch/1118/walk/<username>"]
//        [--urls=<file>] [--role=learner|public|manager|author|compliance|tenantadmin|siteadmin]
//        [--mode=all|walk|certificate] [--max=60] [--courses=3] [--no-defaults] [--no-nav] [--no-shots]
//        [--cert-preview]
//   node surface_walk_1118.mjs --list-defaults [--role=<role>]      (prints the built-in surface lists; no browser)
//
// It logs the persona in through evidence_lib session() (passwords come from the gitignored
// .personas.local.json and are never printed), then visits a role-aware list of surfaces, in this order:
//   1. the persona's own dashboard (/my/);
//   2. every link of its own navigation: the shell sidebar, the topbar, the user menu (opened with its toggle when
//      the links are not in the DOM), the mobile bottom nav and the footer;
//   3. the extra URLs of --urls: a JSON array of strings or {url, name}; or one JSON file for every persona,
//      {"common": [...], "<role>": [...], "<username>": [...]}; or a text file with one URL per line ('#' comments).
//      Relative paths or http://localhost:8080 URLs only;
//   4. the first --courses links of My Courses and of the catalogue, when those pages are visited;
//   5. the built-in list for the persona's role (DEFAULT_SURFACES below; --no-defaults skips it).
//   --max cuts the list from the end; what it cut is listed in summary.json "skipped".
// For each page it records the final URL, the HTTP status, the page title, a Moodle error box / exception text,
// debugging notices and PHP messages, browser console errors, failed requests (4xx/5xx, and requests that failed
// outright), untranslated '[[...]]' strings, horizontal overflow at 1440 and 390 px, OVERLAPS (text whose glyph boxes
// run under a button or icon, or under other text, checked with a hit test so only overlaps that render count; a
// label wholly inside a graphic, like the % in a progress ring, and fixed bars over scrolled content do not count)
// and CLIPPED text (text cut by a container with overflow hidden, with the element that sticks out; ellipsis and
// line-clamp truncation is listed apart as "truncated"). Repeats (one per card) are folded into a count.
// Screenshots: shots() at 1440 and 390 px, <out>/NN-<slug>-{1440px,390px}.png.
// Output: <out>/results.json (array of page records, rewritten after every page) and <out>/summary.json, plus a
// one-line summary on stdout.
//
// Certificate mode (--mode=certificate, or the tail of --mode=all): open the persona's Certificates page
// (/local/sentientia_pages/certificates.php; the tool's own /admin/tool/certificate/my.php as a fallback). When it
// lists an issued certificate, the issued PDF (/admin/tool/certificate/view.php?code=..., the same URL as the course
// header's download icon) is fetched with the persona's cookies and saved as <out>/certificate.pdf. Download only.
// view.php builds and stores the issue PDF the first time anyone opens it, exactly as a learner's click does.
// --cert-preview: for a persona that manages certificate templates, also save the template preview PDFs
// (view.php?preview=1, rendered for the viewer, nothing stored) as certificate-preview-<templateid>.pdf (first 3).
//
// READ ONLY. Only GET navigations: nothing is clicked except (when the user menu has no links in the DOM) the
// user-menu toggle. Links that could change state are skipped (SKIP below: logout, delete, action=, sesskey=,
// checkout, payment, enrol/, quiz attempt pages, process.php, role/language switches, editing mode, downloads and
// exports). The base URL is fixed to http://localhost:8080 and every other host is blocked in the browser
// (evidence_lib newLocalContext). The local database is a copy of production data: results never carry text from
// the page body apart from the title, error/alert messages (emails and long numbers masked) and string identifiers;
// overlap and clipping findings are reported as CSS selector paths, never as the text itself.
//
// Exit code: 0 when the walk completed (findings are data, see results.json), 2 bad arguments, 3 login failed or
// the site stopped answering for more than 3 minutes (results.json keeps what was walked).

import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright-core';
import { BASE, HERE, creds, go, launch, newLocalContext, scan, session, shots, sleep } from './evidence_lib.mjs';

// ---------------------------------------------------------------- arguments
const ARGS = {};
for (let i = 2; i < process.argv.length; i++) {
  const a = process.argv[i];
  if (!a.startsWith('--')) continue;
  const eq = a.indexOf('=');
  if (eq > 0) {
    ARGS[a.slice(2, eq)] = a.slice(eq + 1);
  } else {
    const nx = process.argv[i + 1];
    if (nx !== undefined && !nx.startsWith('--')) {
      ARGS[a.slice(2)] = nx;
      i++;
    } else {
      ARGS[a.slice(2)] = true;
    }
  }
}
const usage = 'usage: node surface_walk_1118.mjs --user=<username> [--out=<dir>] [--urls=<file>] [--role=<role>] '
  + '[--mode=all|walk|certificate] [--max=60] [--courses=3] [--no-defaults] [--no-nav] [--no-shots] [--cert-preview]';
if ('base' in ARGS || 'url' in ARGS) {
  console.error('The base URL is fixed to http://localhost:8080 - this walk never targets another host.');
  process.exit(2);
}
// --list-defaults: print the built-in surface lists (all roles, or --role / --user's role) and exit; no browser.
const LIST_DEFAULTS = !!ARGS['list-defaults'];
const USER = typeof ARGS.user === 'string' ? ARGS.user : '';
if (!USER && !LIST_DEFAULTS) {
  console.error(usage + '\n       node surface_walk_1118.mjs --list-defaults [--role=<role>]');
  process.exit(2);
}
if (USER && !creds[USER]) {
  console.error(`unknown persona ${USER}; known: ${Object.keys(creds).join(', ')}`);
  process.exit(2);
}
const ME = creds[USER] || {};
const MODE = String(ARGS.mode || 'all');
if (!['all', 'walk', 'certificate'].includes(MODE)) {
  console.error(`--mode must be all, walk or certificate\n${usage}`);
  process.exit(2);
}
const MAX = Math.max(1, parseInt(ARGS.max || '60', 10) || 60);
const NCOURSES = Math.max(0, parseInt(ARGS.courses ?? '3', 10) || 0);
const NOSHOTS = !!ARGS['no-shots'];
const CERT_PREVIEW = !!ARGS['cert-preview'];

const REPO = path.resolve(HERE, '../../..');
const OUT = path.resolve(typeof ARGS.out === 'string' ? ARGS.out : `D:/Claude Local/kit-scratch/1118/walk/${USER}`);
const inside = (child, parent) => {
  const rel = path.relative(parent.toLowerCase(), child.toLowerCase());
  return rel === '' || (!rel.startsWith('..') && !path.isAbsolute(rel));
};
if (inside(OUT, REPO)) {
  console.error(`--out must be outside the repository (${REPO}): the screenshots show production data.`);
  process.exit(2);
}
if (!LIST_DEFAULTS) fs.mkdirSync(OUT, { recursive: true });

// ---------------------------------------------------------------- role surfaces
const ROLE_OF = {
  vp_learner1: 'learner', vp_learner177: 'learner', vp_report1b: 'learner', vp_eval77: 'learner',
  vp_public77: 'public', vp_manager1: 'manager', vp_author1: 'author', vp_courseauthor1: 'author',
  vp_compliance1: 'compliance', vp_admin1: 'tenantadmin', vp_admin177: 'tenantadmin', vp_siteadmin: 'siteadmin',
};
function roleFromLabel(label) {
  const l = String(label || '').toLowerCase();
  if (/site admin/.test(l)) return 'siteadmin';
  if (/tenant admin|l&d admin/.test(l)) return 'tenantadmin';
  if (/manager/.test(l)) return 'manager';
  if (/author|trainer/.test(l)) return 'author';
  if (/compliance/.test(l)) return 'compliance';
  if (/public/.test(l)) return 'public';
  return 'learner';
}
const ROLE = typeof ARGS.role === 'string' ? ARGS.role : (ROLE_OF[USER] || roleFromLabel(ME.label));

// Every role: the persona's own pages.
const COMMON = [
  '/my/',
  '/local/sentientia_users/profile.php',
  '/local/sentientia_pages/certificates.php',
  '/message/output/popup/notifications.php',
  '/user/preferences.php',
  '/calendar/view.php?view=month',
];
const TENANT_ADMIN = [
  '/local/sentientia_users/index.php',
  '/local/sentientia_courses/index.php',
  '/local/sentientia_courses/manage_requests.php',
  '/local/sentientia_org/admin.php',
  '/local/sentientia_reports/index.php',
  '/local/sentientia_analytics/index.php',
  '/local/sentientia_compliance_report/index.php',
  '/local/sentientia_roles/index.php',
  '/local/sentientia_exams/index.php',
  '/local/sentientia_classroom/index.php',
  '/local/sentientia_learningpath/index.php',
  '/local/sentientia_programs/index.php',
  '/local/sentientia_skills/admin.php',
  '/local/sentientia_notifications/index.php',
  '/local/sentientia_evaluation/index.php',
  '/local/sentientia_pages/certificate_templates.php',
  '/local/sentientia_request/all.php',
  '/local/sentientia_emails/manage.php',
];
const DEFAULT_SURFACES = {
  learner: [
    '/local/sentientia_catalog/index.php',
    '/local/sentientia_catalog/mycourses.php',
    '/local/sentientia_learningpath/index.php',
    '/local/sentientia_programs/index.php',
    '/local/sentientia_classroom/my.php',
    '/local/sentientia_skills/index.php',
    '/local/sentientia_leaderboard/index.php',
    '/local/sentientia_challenge/index.php',
    '/local/sentientia_courses/my_requests.php',
    '/badges/mybadges.php',
    '/grade/report/overview/index.php',
    '/local/sentientia_privacy/index.php',
    '/local/sentientia_users/privacypolicy.php',
  ],
  public: [
    '/local/sentientia_catalog/public.php',
    '/local/sentientia_catalog/index.php',
    '/local/sentientia_catalog/mycourses.php',
    '/local/sentientia_catalog/cart.php',
    '/local/sentientia_cart/history.php',
    '/badges/mybadges.php',
    '/local/sentientia_privacy/index.php',
    '/local/sentientia_users/privacypolicy.php',
  ],
  manager: [
    '/local/sentientia_manager/index.php',
    '/local/sentientia_manager/performance.php',
    '/local/sentientia_manager/requests.php',
    '/local/sentientia_compliance_report/index.php',
    '/local/sentientia_reports/index.php',
    '/local/sentientia_catalog/mycourses.php',
    '/local/sentientia_catalog/index.php',
  ],
  author: [
    '/local/sentientia_catalog/mycourses.php',
    '/local/sentientia_classroom/index.php',
    '/local/sentientia_live/index.php',
    '/local/sentientia_live/trainer/index.php',
    '/local/sentientia_authoring/index.php',
    '/local/sentientia_authoring/studio.php',
    '/local/sentientia_authoring/templates.php',
    '/local/sentientia_aiquiz/review.php',
    '/local/sentientia_skillsai/index.php',
  ],
  compliance: [
    '/local/sentientia_compliance_report/index.php',
    '/local/sentientia_reports/index.php',
    '/local/sentientia_catalog/mycourses.php',
  ],
  tenantadmin: TENANT_ADMIN,
  siteadmin: [
    ...TENANT_ADMIN,
    '/local/sentientia_platform/admin/switchboard.php',
    '/admin/tool/certificate/manage_templates.php',
    '/admin/tool/dataprivacy/datarequests.php',
    '/local/sentientia_privacy/index.php',
    '/admin/search.php',
  ],
};
if (LIST_DEFAULTS) {
  const roles = (typeof ARGS.role === 'string' || USER) ? [ROLE] : Object.keys(DEFAULT_SURFACES);
  const out = { common: COMMON, personas: ROLE_OF };
  for (const r of roles) out[r] = DEFAULT_SURFACES[r] || `unknown role; known: ${Object.keys(DEFAULT_SURFACES).join(', ')}`;
  console.log(JSON.stringify(out, null, 2));
  process.exit(0);
}
if (!DEFAULT_SURFACES[ROLE]) {
  console.error(`unknown role ${ROLE}; known: ${Object.keys(DEFAULT_SURFACES).join(', ')}`);
  process.exit(2);
}

// ---------------------------------------------------------------- link policy
const LOCAL = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?(\/|$)/i;
// Never follow these: anything that could save, switch, pay, enrol, attempt, download or end the session.
const SKIP = [
  /logout/i, /delete/i, /[?&]action=/i, /sesskey=/i, /checkout/i, /payment/i, /\/enrol\//i,
  /attempt\.php/i, /startattempt\.php/i, /process\.php/i,
  /switchrole/i, /loginas/i, /[?&]lang=/i, /[?&]edit=/i, /editingon|editmode/i, /[?&]reset=/i, /unenrol/i,
  /[?&](approve|reject|confirm|cancel|remove|dismiss|markasread|markallread|toggle|complete|subscribe|unsubscribe|accept|decline)=/i,
  /togglecompletion/i, /(approve|reject|accept|decline)\.php/i, /mark_?all_?read|markasread/i,
  /[?&]download=/i, /export/i, /pluginfile\.php/i, /\/webservice\//i, /\/lib\/ajax\//i,
  /\/admin\/cron\.php/i, /schedule_task|run_task|purge_?caches/i,
  /\/admin\/tool\/certificate\/view\.php/i,
  /\.(pdf|zip|csv|xlsx?|docx?|pptx?|mp4|mp3|png|jpe?g|gif|svg|ico|webp|json|xml)(\?|$)/i,
];
const ALIAS = { '/my/index.php': '/my/', '/my/dashboard.php': '/my/', '/my': '/my/' };

// Path + sorted query, no hash, no host; '' for something we will not visit.
function normalise(href) {
  let u;
  try {
    u = new URL(href, BASE);
  } catch (e) {
    return '';
  }
  if (!/^https?:$/.test(u.protocol) || !LOCAL.test(u.href)) return '';
  let p = u.pathname.replace(/\/{2,}/g, '/');
  p = ALIAS[p] || p;
  const params = [...u.searchParams.entries()].sort((a, b) => (a[0] + a[1]).localeCompare(b[0] + b[1]));
  const q = params.length ? '?' + new URLSearchParams(params).toString() : '';
  return p + q;
}
const skipReason = url => {
  const hit = SKIP.find(re => re.test(url));
  return hit ? String(hit) : '';
};

function readUrlsFile(file) {
  const raw = fs.readFileSync(file, 'utf8');
  let list;
  try {
    const j = JSON.parse(raw);
    // An array, {urls: [...]}, or one file for every persona: {common: [...], <role>: [...], <username>: [...]}.
    list = Array.isArray(j) ? j : (Array.isArray(j.urls) ? j.urls : [...(j.common || []), ...(j[ROLE] || []), ...(j[USER] || [])]);
  } catch (e) {
    list = raw.split(/\r?\n/).map(s => s.trim()).filter(s => s && !s.startsWith('#'));
  }
  return list.map(x => (typeof x === 'string' ? { url: x, name: '' } : { url: x.url, name: x.name || '' })).filter(x => x.url);
}

// ---------------------------------------------------------------- privacy masks
const mask = s => String(s || '')
  .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[email]')
  .replace(/\b[A-Za-z]{0,4}\d{5,}\b/g, '[id]')
  .replace(/\s+/g, ' ')
  .trim();

// ---------------------------------------------------------------- in-page probes (self-contained)
// Page-level horizontal overflow and its culprits (persona_journeys.mjs measureOverflow, trimmed).
function measureOverflow() {
  const de = document.documentElement;
  const vw = de.clientWidth;
  const label = el => {
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).join('.');
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
  };
  const doc = de.scrollWidth - de.clientWidth;
  const culprits = [];
  if (doc > 1) {
    const fixedOrClipped = el => {
      for (let p = el; p && p !== de; p = p.parentElement) {
        const cs = getComputedStyle(p);
        if (cs.position === 'fixed') return true;
        if (p !== el && p !== document.body && /(auto|scroll|hidden|clip)/.test(cs.overflowX)) return true;
      }
      return false;
    };
    const cands = [];
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1 || r.right <= vw + 1) continue;
      if (fixedOrClipped(el)) continue;
      cands.push({ el, right: Math.round(r.right), width: Math.round(r.width) });
      if (cands.length >= 400) break;
    }
    const set = new Set(cands.map(c => c.el));
    const tops = cands.filter(c => !set.has(c.el.parentElement)).sort((a, b) => b.right - a.right).slice(0, 3);
    for (const c of tops) culprits.push(`${label(c.el)} (right ${c.right} > ${vw}px, width ${c.width})`);
  }
  return { doc, sw: de.scrollWidth, cw: de.clientWidth, culprits };
}

// Overlaps (text under a button/icon, or text over other text) and clipped text. Findings carry selector paths and
// sizes only, never the text. Every overlap is confirmed with a hit test at the centre of the intersection: both
// sides must be hit there (so clipped, covered or off-screen content does not count).
function measureLayout() {
  const MAXTEXT = 3000;
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const HIDDEN = '.sr-only, .visually-hidden, .accesshide, .visually-hidden-focusable, [hidden], template, noscript, script, style';
  const CLICK = 'a[href], button, [role="button"], label, summary, input, select, textarea';
  const CTRL = 'button, [role="button"], a.btn, .btn, input[type="button"], input[type="submit"], input[type="reset"], '
    + 'i.fa, i[class*="fa-"], i.icon, .icon, svg, img.icon, [class*="__icon"]';
  const label = el => {
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(c => c && !/^(fa|icon)$/.test(c)).slice(0, 2).join('.');
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
  };
  const pathOf = el => {
    const parts = [];
    for (let p = el, i = 0; p && p !== document.body && i < 3; p = p.parentElement, i++) parts.unshift(label(p));
    return parts.join(' > ');
  };
  const shown = new Map();
  const isShown = el => {
    if (shown.has(el)) return shown.get(el);
    let ok = true;
    if (el.closest(HIDDEN)) ok = false;
    else {
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility !== 'visible') ok = false;
      for (let p = el; ok && p && p !== document.documentElement; p = p.parentElement) {
        if (parseFloat(getComputedStyle(p).opacity) < 0.05) ok = false;
      }
    }
    shown.set(el, ok);
    return ok;
  };
  const inter = (a, b) => {
    const ix = Math.min(a.right, b.right) - Math.max(a.left, b.left);
    const iy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    return ix > 0 && iy > 0 ? { ix, iy, cx: Math.max(a.left, b.left) + ix / 2, cy: Math.max(a.top, b.top) + iy / 2 } : null;
  };
  const area = r => r.width * r.height;
  const stackAt = (x, y) => (x >= 0 && y >= 0 && x < vw && y < vh ? document.elementsFromPoint(x, y) : null);
  // The fixed/sticky layer an element lives in (null = the page flow). The probes run in a viewport as tall as the
  // page, so a fixed bar (the mobile bottom nav, a sticky topbar) sits over whatever content is at that spot;
  // that is scrolling behaviour, not an overlap, so pairs from different layers are not compared.
  const layers = new Map();
  const layerOf = el => {
    if (layers.has(el)) return layers.get(el);
    let found = null;
    for (let p = el; p && p !== document.documentElement; p = p.parentElement) {
      const pos = getComputedStyle(p).position;
      if (pos === 'fixed' || pos === 'sticky') {
        found = p;
        break;
      }
    }
    layers.set(el, found);
    return found;
  };

  // Text line boxes (one per line of every visible text node).
  const texts = [];
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const range = document.createRange();
  let n;
  let count = 0;
  while ((n = walker.nextNode()) && count < MAXTEXT) {
    if (!n.nodeValue || !n.nodeValue.trim()) continue;
    const p = n.parentElement;
    if (!p || !isShown(p)) continue;
    count++;
    range.selectNodeContents(n);
    for (const r of range.getClientRects()) {
      if (r.width < 2 || r.height < 4) continue;
      texts.push({ node: n, p, r, len: n.nodeValue.trim().length });
    }
  }

  // Buttons and icons.
  const ctrls = [];
  for (const c of document.querySelectorAll(CTRL)) {
    if (!isShown(c)) continue;
    const r = c.getBoundingClientRect();
    if (r.width < 4 || r.height < 4 || r.width > 480 || r.height > 160) continue;
    // The clickable the control belongs to, when that clickable is control-sized (an icon button with a badge);
    // a whole card wrapped in one link does not count, so a title under an icon inside that card is still found.
    const ck = c.closest(CLICK);
    const ckr = ck ? ck.getBoundingClientRect() : null;
    ctrls.push({ c, r, click: ck && ckr.width <= 480 && ckr.height <= 160 ? ck : c,
      graphic: !c.matches('button, [role="button"], a.btn, .btn, input') });
    if (ctrls.length >= 600) break;
  }

  const overlaps = [];
  const seenPair = new Map();
  const addOverlap = (kind, a, b, i, onTop) => {
    const key = kind + '|' + pathOf(a) + '|' + pathOf(b);
    if (seenPair.has(key)) {
      seenPair.get(key).count++;
      return;
    }
    const o = { kind, text: pathOf(a), other: pathOf(b), px: [Math.round(i.ix), Math.round(i.iy)],
      at: [Math.round(i.cx), Math.round(i.cy + window.scrollY)], onTop, count: 1 };
    seenPair.set(key, o);
    overlaps.push(o);
  };

  // Text running under (or over) a button / icon it does not belong to.
  for (const t of texts) {
    const tclick = t.p.closest(CLICK);
    for (const k of ctrls) {
      if (k.c.contains(t.node)) continue;
      if (tclick && (tclick === k.click || k.click.contains(tclick))) continue;
      if (layerOf(t.p) !== layerOf(k.c)) continue;
      const i = inter(t.r, k.r);
      if (!i || i.ix < 4 || i.iy < 4 || i.ix * i.iy < 0.1 * Math.min(area(t.r), area(k.r))) continue;
      // A label drawn wholly inside a graphic (the % in a progress ring, initials on an avatar, a count on an
      // icon) is an overlay by design; a partial overlap, or text under a button, is not.
      if (k.graphic && t.r.left >= k.r.left - 1 && t.r.right <= k.r.right + 1 && t.r.top >= k.r.top - 1
        && t.r.bottom <= k.r.bottom + 1) continue;
      const stack = stackAt(i.cx, i.cy);
      if (!stack) continue;
      const ti = stack.findIndex(e => e === t.p || t.p.contains(e));
      const ci = stack.findIndex(e => e === k.c || k.c.contains(e) || e === k.click);
      if (ti < 0 || ci < 0) continue;
      addOverlap('text-under-control', t.p, k.c, i, ci < ti ? 'control' : 'text');
      if (overlaps.length >= 40) break;
    }
    if (overlaps.length >= 40) break;
  }

  // Text over other text (different elements, neither inside the other).
  const sorted = texts.slice(0, 2500).sort((a, b) => a.r.top - b.r.top);
  for (let x = 0; x < sorted.length && overlaps.length < 60; x++) {
    const a = sorted[x];
    for (let y = x + 1; y < sorted.length && sorted[y].r.top < a.r.bottom; y++) {
      const b = sorted[y];
      if (a.p === b.p || a.p.contains(b.p) || b.p.contains(a.p)) continue;
      if (layerOf(a.p) !== layerOf(b.p)) continue;
      const i = inter(a.r, b.r);
      if (!i || i.ix < 6 || i.iy < 6 || i.ix * i.iy < 0.15 * Math.min(area(a.r), area(b.r))) continue;
      const stack = stackAt(i.cx, i.cy);
      if (!stack) continue;
      const ai = stack.findIndex(e => e === a.p || a.p.contains(e));
      const bi = stack.findIndex(e => e === b.p || b.p.contains(e));
      if (ai < 0 || bi < 0) continue;
      addOverlap('text-over-text', a.p, b.p, i, ai < bi ? 'first' : 'second');
    }
  }

  // Clipped text: a line box that is partly inside and partly outside a box with overflow hidden/clip.
  const clipped = [];
  const truncated = [];
  const SLIDER = '[class*="carousel"], [class*="slider"], [class*="swiper"], [class*="slick"], [class*="marquee"], [class*="ticker"]';
  for (const el of document.querySelectorAll('body *')) {
    if (clipped.length >= 60 && truncated.length >= 60) break;
    if (!(el.scrollWidth > el.clientWidth + 2 || el.scrollHeight > el.clientHeight + 2)) continue;
    const cs = getComputedStyle(el);
    const hx = /(hidden|clip)/.test(cs.overflowX);
    const hy = /(hidden|clip)/.test(cs.overflowY);
    if (!hx && !hy) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 8 || r.height < 8 || !isShown(el) || el.closest(SLIDER)) continue;
    const L = r.left + el.clientLeft;
    const T = r.top + el.clientTop;
    const R = L + el.clientWidth;
    const B = T + el.clientHeight;
    let cutX = false;
    let cutY = false;
    let hiddenLines = false;
    let tl = 0;
    const cutBy = new Set();
    // The element that sticks out: the highest ancestor of the cut text (below the clipping box) that is itself
    // wider/taller than the box, e.g. the second column of a grid cut at 390 px.
    const culprit = t => {
      let top = t.p;
      for (let p = t.p; p && p !== el; p = p.parentElement) {
        const pr = p.getBoundingClientRect();
        if (pr.right > R + 1 || pr.left < L - 1 || pr.bottom > B + 1) top = p;
      }
      return top;
    };
    for (const t of texts) {
      if (!el.contains(t.node)) continue;
      const q = t.r;
      tl += t.len;
      let cut = false;
      if (hx && ((q.right > R + 1 && q.left < R - 1) || (q.left < L - 1 && q.right > L + 1)) && q.bottom > T && q.top < B) cutX = cut = true;
      if (hy && q.bottom > B + 1 && q.top < B - 1 && q.right > L && q.left < R) cutY = cut = true;
      if (hy && q.top >= B - 1 && q.left < R && q.right > L) hiddenLines = true;
      if (cut && cutBy.size < 2 && t.p !== el) cutBy.add(culprit(t));
    }
    if (!cutX && !cutY && !hiddenLines) continue;
    const ellipsis = cs.textOverflow === 'ellipsis';
    const clamp = (cs.webkitLineClamp && cs.webkitLineClamp !== 'none') || /-webkit-box/.test(cs.display);
    const entry = { el, sel: pathOf(el), box: [Math.round(r.width), Math.round(r.height)], textLen: tl,
      dir: cutX && (cutY || hiddenLines) ? 'xy' : cutX ? 'x' : 'y' };
    if (cutBy.size) entry.sticksOut = [...cutBy].map(c => pathOf(c));
    if ((cutX && !ellipsis) || (cutY && !clamp)) {
      if (clipped.length < 60) clipped.push(entry);
    } else if (truncated.length < 60) {
      truncated.push({ ...entry, how: ellipsis ? 'ellipsis' : clamp ? 'line-clamp' : 'hidden-lines' });
    }
  }
  // Keep the innermost box when an ancestor clips the same text, then fold repeats (one per card) into a count.
  const innermost = list => list.filter(a => !list.some(b => b !== a && a.el.contains(b.el))).map(({ el, ...rest }) => rest);
  const fold = list => {
    const m = new Map();
    for (const e of list) {
      const k = `${e.sel}|${e.how || ''}|${e.dir}`;
      if (m.has(k)) m.get(k).count++;
      else m.set(k, { ...e, count: 1 });
    }
    return [...m.values()];
  };
  return {
    texts: texts.length,
    overlaps: overlaps.sort((a, b) => b.px[0] * b.px[1] - a.px[0] * a.px[1]).slice(0, 12),
    clipped: fold(innermost(clipped)).slice(0, 12),
    truncated: fold(innermost(truncated)).slice(0, 12),
  };
}

// Error boxes, debugging, PHP messages, alerts, untranslated strings in attributes.
function gatherPage() {
  const text = document.body ? document.body.innerText : '';
  const html = document.documentElement.outerHTML;
  const box = document.querySelector('[data-rel="fatalerror"], .errorbox, #notice.errorbox, .errorcode');
  const docLink = html.match(/docs\.moodle\.org\/[^"'\s]*?\/error\/([a-z0-9_]+)\/([a-z0-9_]+)/i);
  const codeText = text.match(/Error code: ([a-z0-9_]+)/i);
  const MISS = /\[\[(?=[^\]]*[A-Za-z])[A-Za-z0-9_:/.,-]+\]\]/g;
  const attrs = [];
  for (const el of document.querySelectorAll('[title], [placeholder], [aria-label], [alt], input[type="submit"][value], input[type="button"][value]')) {
    for (const a of ['title', 'placeholder', 'aria-label', 'alt', 'value']) {
      const v = el.getAttribute(a);
      if (v && MISS.test(v)) attrs.push(...v.match(MISS));
      MISS.lastIndex = 0;
    }
  }
  return {
    title: document.title,
    errorBox: box ? box.innerText.trim().slice(0, 300) : '',
    errorCode: docLink ? `${docLink[1]}/${docLink[2]}` : (codeText ? codeText[1] : ''),
    exception: /Exception - |Coding error detected|error\/moodle/.test(text) || /\/error\/moodle\//.test(html),
    debugging: [...document.querySelectorAll('.debuggingmessage, [data-rel="debugging"]')].map(e => e.innerText.trim().slice(0, 300)).slice(0, 4),
    php: (text.match(/(?:Warning|Notice|Deprecated|Fatal error|Parse error): .{0,220}? on line \d+/g) || []).slice(0, 4),
    alerts: [...document.querySelectorAll('.alert-danger, .notifyproblem')].map(e => e.innerText.trim().slice(0, 200)).filter(Boolean).slice(0, 3),
    missingText: [...new Set(text.match(MISS) || [])].slice(0, 10),
    missingAttr: [...new Set(attrs)].slice(0, 10),
    onboarding: /\/local\/sentientia_pages\/onboarding\.php/.test(location.pathname),
  };
}

async function fullHeight(page) {
  return page.evaluate(() => {
    let h = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (/(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 20 && el.clientHeight > 300) {
        h = Math.max(h, window.innerHeight - el.clientHeight + el.scrollHeight);
      }
    }
    return h;
  });
}

// ---------------------------------------------------------------- browser + session
let browser;
let context;
let page;
let cur = null; // the record being filled; the page listeners write into it
const certLinksSeen = new Set();

function attach(p) {
  p.on('dialog', d => d.dismiss().catch(() => {}));
  p.on('response', r => {
    if (!cur) return;
    try {
      const req = r.request();
      const u = new URL(r.url());
      if (req.isNavigationRequest() && r.frame() === p.mainFrame()) return;
      if (r.status() >= 400 && cur.failedRequests.length < 12) cur.failedRequests.push(`${r.status()} ${req.method()} ${u.pathname}`);
    } catch (e) { /* worker response */ }
  });
  p.on('requestfailed', req => {
    if (!cur) return;
    const why = (req.failure() && req.failure().errorText) || '';
    let u;
    try {
      u = new URL(req.url());
    } catch (e) {
      return;
    }
    if (/BLOCKED_BY_CLIENT/i.test(why)) {
      if (!cur.blockedExternal.includes(u.host) && cur.blockedExternal.length < 8) cur.blockedExternal.push(u.host);
      return;
    }
    if (/ERR_ABORTED/i.test(why)) return; // a navigation that moved on
    if (cur.failedRequests.length < 12) cur.failedRequests.push(`failed ${req.method()} ${u.pathname} (${why})`);
  });
}

async function login() {
  if (context) await context.close().catch(() => {});
  let s;
  try {
    s = await session(browser, USER);
  } catch (e) {
    // session() gives up after one slow login page (e.g. the box is busy with other agents). Try once more if the
    // site still answers; stop if it has not answered for 3 minutes.
    context = await newLocalContext(browser);
    const up = await alive();
    await context.close().catch(() => {});
    context = null;
    if (!up) throw new Error('the local site stopped answering for more than 3 minutes');
    s = await session(browser, USER);
  }
  context = s.context;
  page = s.page;
  page.setDefaultTimeout(90000);
  attach(page);
}

async function alive(maxMs = 180000) {
  const t0 = Date.now();
  while (Date.now() - t0 < maxMs) {
    try {
      const r = await context.request.get(BASE + '/login/index.php', { timeout: 60000, maxRedirects: 0, failOnStatusCode: false });
      if (r.status() > 0 && r.status() < 500) return true;
    } catch (e) { /* not answering */ }
    await sleep(10000);
  }
  return false;
}

// ---------------------------------------------------------------- one page
const records = [];
const skipped = [];
const visitedFinal = new Map(); // normalised final URL -> record number
let aborted = '';
let relogins = 0;

const slug = u => (u.replace(/^\//, '').replace(/\.php/g, '').replace(/[?&=]/g, '-').replace(/[^A-Za-z0-9_-]+/g, '-')
  .replace(/-+/g, '-').replace(/^-|-$/g, '') || 'home').slice(0, 70);

function newRecord(entry, n) {
  return {
    n, name: entry.name || '', source: entry.src, url: entry.url, final: '', status: 0, redirected: false, title: '',
    errorBox: '', errorCode: '', exception: false, debugging: [], php: [], alerts: [], console: [], failedRequests: [],
    blockedExternal: [], missingStrings: [], unrendered: false, overflow: {}, overlaps: {}, clipped: {}, truncated: {},
    onboarding: false, shots: [], issues: [], ms: 0, at: new Date().toISOString(),
  };
}

function writeResults() {
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(records, null, 2));
}

async function visit(entry, n) {
  const rec = newRecord(entry, n);
  cur = rec;
  page.errs.length = 0;
  const t0 = Date.now();
  let res = null;
  try {
    res = await go(page, entry.url);
  } catch (e) {
    const msg = String(e.message).split('\n')[0].slice(0, 200);
    rec.issues.push('navigation failed: ' + msg);
    rec.ms = Date.now() - t0;
    rec.console = [...page.errs].slice(0, 8);
    cur = null;
    if (!(await alive())) aborted = 'the local site stopped answering for more than 3 minutes';
    return rec;
  }
  rec.status = res ? res.status() : 0;
  rec.final = page.url().replace(/^https?:\/\/[^/]+/, '');
  rec.redirected = normalise(rec.final) !== normalise(entry.url);

  // Session lost (bounced to the login page): log in again once per page, at most twice per walk.
  if (/^\/login\//.test(new URL(page.url()).pathname) && !/^\/login\//.test(entry.url) && relogins < 2) {
    relogins++;
    cur = null;
    await login();
    return visit(entry, n);
  }

  // A link that redirected to a page this walk already measured (e.g. / -> /my/): note it and move on.
  const fin = normalise(rec.final);
  if (rec.redirected && visitedFinal.has(fin)) {
    rec.duplicateOf = visitedFinal.get(fin);
    rec.ms = Date.now() - t0;
    cur = null;
    return rec;
  }
  visitedFinal.set(fin, n);

  const g = await page.evaluate(gatherPage).catch(() => null);
  if (g) {
    rec.title = mask(g.title).slice(0, 160);
    rec.errorBox = mask(g.errorBox);
    rec.errorCode = g.errorCode;
    rec.exception = g.exception;
    rec.debugging = g.debugging.map(mask);
    rec.php = g.php.map(mask);
    rec.alerts = g.alerts.map(mask);
    rec.missingStrings = [...new Set([...g.missingText, ...g.missingAttr])];
    rec.onboarding = g.onboarding;
  }
  const s = await scan(page).catch(() => ({ problems: [] }));
  rec.unrendered = s.problems.includes('unrendered mustache tag');
  if (s.problems.includes('Moodle debug dump on page') && !rec.debugging.length) rec.debugging.push('Moodle debug dump on page (Debug info / Stack trace)');
  if (s.problems.includes('PHP message on page') && !rec.php.length && !rec.exception) rec.php.push('PHP message on page');

  // Certificate links seen anywhere (course header download icon, Certificates page cards).
  const cl = await page.evaluate(() => [...document.querySelectorAll('a[href*="/admin/tool/certificate/"][href*="code="]')]
    .map(a => a.href)).catch(() => []);
  for (const h of cl) certLinksSeen.add(h);

  // Layout probes at 1440 and 390, each at the full page height so the hit test sees the whole page.
  for (const w of [1440, 390]) {
    const h0 = w > 768 ? 900 : 1000;
    await page.setViewportSize({ width: w, height: h0 });
    await page.waitForTimeout(600);
    const need = Math.min(Math.max(await fullHeight(page).catch(() => h0), h0), 4000);
    if (need > h0) {
      await page.setViewportSize({ width: w, height: need });
      await page.waitForTimeout(600);
    }
    const m = await page.evaluate(measureOverflow).catch(() => null);
    if (m) rec.overflow[w] = m;
    const lay = await page.evaluate(measureLayout).catch(err => ({ error: String(err.message).slice(0, 120) }));
    if (lay.error) {
      rec.overlaps[w] = [];
      rec.issues.push(`layout probe failed at ${w}px: ${lay.error}`);
    } else {
      rec.overlaps[w] = lay.overlaps;
      rec.clipped[w] = lay.clipped;
      rec.truncated[w] = lay.truncated;
    }
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
  if (!NOSHOTS) {
    const base = `${String(n).padStart(2, '0')}-${slug(normalise(rec.final) || entry.url)}`;
    try {
      rec.shots = [...(await shots(page, OUT, base, { widths: [1440, 390], maxHeight: 4000 }))];
    } catch (e) {
      rec.issues.push('screenshot failed: ' + String(e.message).split('\n')[0].slice(0, 120));
    }
  }
  // A refusal or missing page is itself the 4xx document; Chrome also logs it as a console error. Drop that one.
  const docFail = rec.status >= 400 && !rec.failedRequests.some(f => f.startsWith(`${rec.status} `));
  rec.console = [...page.errs]
    .filter(t => !(docFail && new RegExp(`Failed to load resource:.*status of ${rec.status}`).test(t)))
    .slice(0, 8).map(mask);

  // Issue list (short, one line each). A refusal page (4xx + error box) prints the local debug stack trace, which
  // production never shows (debugdisplay is off there), so its debug dump and the overflow it causes are marked.
  rec.refusal = rec.status >= 400 && !!(rec.errorBox || rec.errorCode);
  const localDebug = rec.refusal ? ' [refusal page, local debug output]' : '';
  if (rec.status >= 400) rec.issues.push(`HTTP ${rec.status}`);
  if (rec.errorBox || rec.exception || rec.errorCode) rec.issues.push(`error box${rec.errorCode ? ' (' + rec.errorCode + ')' : ''}${rec.errorBox ? ': ' + rec.errorBox.slice(0, 120) : ''}`);
  if (rec.debugging.length) rec.issues.push(`${rec.debugging.length} debugging notice(s): ${rec.debugging[0].slice(0, 120)}${localDebug}`);
  if (rec.php.length) rec.issues.push(`PHP message: ${rec.php[0].slice(0, 140)}`);
  if (rec.console.length) rec.issues.push(`${rec.console.length} console error(s): ${rec.console[0].slice(0, 120)}`);
  if (rec.failedRequests.length) rec.issues.push(`${rec.failedRequests.length} failed request(s): ${rec.failedRequests.slice(0, 2).join(', ')}`);
  if (rec.missingStrings.length) rec.issues.push(`untranslated: ${rec.missingStrings.slice(0, 4).join(' ')}`);
  if (rec.unrendered) rec.issues.push('unrendered mustache tag');
  for (const w of [1440, 390]) {
    const m = rec.overflow[w];
    if (m && m.doc > 1) rec.issues.push(`horizontal overflow at ${w}px: ${m.doc}px${m.culprits[0] ? ' (' + m.culprits[0] + ')' : ''}${localDebug}`);
    const o = rec.overlaps[w] || [];
    if (o.length) {
      rec.issues.push(`${o.length} overlap(s) at ${w}px: ${o[0].text} x ${o[0].other} (${o[0].px.join('x')}px`
        + `${o[0].count > 1 ? ', ' + o[0].count + ' times' : ''})`);
    }
    const c = rec.clipped[w] || [];
    if (c.length) {
      rec.issues.push(`${c.length} clipped text box(es) at ${w}px: ${c[0].sel}`
        + (c[0].sticksOut ? ` (cut: ${c[0].sticksOut[0]})` : ''));
    }
  }
  if (rec.onboarding) rec.issues.push('landed on the onboarding wizard (not completed for this persona)');
  rec.ms = Date.now() - t0;
  cur = null;
  return rec;
}

// ---------------------------------------------------------------- link harvesting
async function harvestNav(allowToggle) {
  const userMenuToggle = '.usermenu #user-menu-toggle, .ap-topbar__usermenu .dropdown-toggle';
  const collect = () => page.evaluate(() => {
    const groups = [
      ['nav:sidebar', '.ap-sidebar__nav a[href], a.ap-sidebar__user-link[href], a.ap-sidebar__brand[href]'],
      ['nav:topbar', '.ap-topbar a[href]'],
      ['nav:usermenu', '.usermenu a[href], #user-action-menu a[href]'],
      ['nav:bottom', '.airpay-mobile-nav a[href]'],
      ['nav:primary', '.primary-navigation a[href]'],
      ['nav:footer', '#page-footer a[href], footer.airpay-footer a[href], .airpay-footer__product-attribution a[href]'],
    ];
    const out = [];
    for (const [src, sel] of groups) {
      for (const a of document.querySelectorAll(sel)) {
        if (a.matches('[data-roleswitch-link], [data-langswitch-link]')) continue;
        if (src === 'nav:footer' && a.closest('.ap-sidebar, .ap-topbar, .airpay-mobile-nav')) continue;
        const own = a.matches('.ap-sidebar__user-link');
        const name = own ? 'Profile (sidebar user link)'
          : (a.innerText.trim() || a.getAttribute('aria-label') || a.title || a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 60);
        out.push({ src, href: a.href, name, inUserMenu: !!a.closest('.usermenu, #user-action-menu') });
      }
    }
    return out;
  }).catch(() => []);
  let links = await collect();
  if (allowToggle && !links.some(l => l.inUserMenu)) {
    // Toggling the user menu only opens a dropdown; nothing is saved.
    const t = page.locator(userMenuToggle).first();
    if (await t.count().catch(() => 0)) {
      await t.click({ timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(800);
      links = await collect();
      await page.keyboard.press('Escape').catch(() => {});
    }
  }
  return links;
}

async function harvestCourses(kind) {
  const sel = kind === 'mycourses'
    ? 'a[href*="/course/view.php?id="]'
    : 'a[href*="/local/sentientia_catalog/course.php?id="], a[href*="/course/view.php?id="]';
  const hrefs = await page.evaluate(s => [...document.querySelectorAll(s)]
    .filter(a => !a.closest('.ap-sidebar, .ap-topbar, .usermenu, .airpay-mobile-nav, nav.navbar'))
    .map(a => a.href), sel).catch(() => []);
  const out = [];
  const ids = new Set();
  for (const h of hrefs) {
    const u = new URL(h);
    const id = u.searchParams.get('id');
    if (!id || id === '1' || ids.has(id)) continue;
    ids.add(id);
    out.push(h);
    if (out.length >= NCOURSES) break;
  }
  return out;
}

// ---------------------------------------------------------------- certificate mode
async function fetchPdf(url) {
  let u = BASE + url;
  const chain = [];
  for (let hop = 0; hop < 6; hop++) {
    if (!LOCAL.test(u)) return { ok: false, chain, note: 'redirect to a non-local host refused' };
    let r;
    try {
      r = await context.request.get(u, { maxRedirects: 0, timeout: 120000, failOnStatusCode: false });
    } catch (e) {
      return { ok: false, chain, note: 'request failed: ' + String(e.message).split('\n')[0].slice(0, 120) };
    }
    const p = new URL(u).pathname.replace(/[^/]+\.pdf$/i, '<code>.pdf');
    chain.push(`${r.status()} ${p}`);
    if (r.status() >= 300 && r.status() < 400) {
      const loc = r.headers().location;
      if (!loc) break;
      u = new URL(loc, u).href;
      continue;
    }
    const ct = r.headers()['content-type'] || '';
    const body = await r.body();
    if (body.subarray(0, 5).toString('latin1') === '%PDF-') return { ok: true, status: r.status(), contentType: ct, body, chain };
    if (/text\/html/i.test(ct)) {
      // Moodle prints a "continue" page instead of a 303 when debugging output came first.
      const html = body.toString('utf8');
      const m = html.match(/http-equiv=["']refresh["'][^>]*url=([^"'>]+)/i) || html.match(/href=["']([^"']*pluginfile\.php[^"']*)["']/i);
      if (m) {
        u = new URL(m[1].replace(/&amp;/g, '&'), u).href;
        continue;
      }
      const err = html.match(/class="[^"]*errorbox[^"]*"[^>]*>([\s\S]{0,400}?)<\/div>/i);
      return { ok: false, status: r.status(), contentType: ct, chain,
        note: 'HTML instead of a PDF' + (err ? ': ' + mask(err[1].replace(/<[^>]+>/g, ' ')).slice(0, 160) : '') };
    }
    return { ok: false, status: r.status(), contentType: ct, chain, note: 'not a PDF' };
  }
  return { ok: false, chain, note: 'too many redirects' };
}

function pdfFacts(buf) {
  const s = buf.toString('latin1');
  return {
    bytes: buf.length,
    pages: (s.match(/\/Type\s*\/Page(?![s\w])/g) || []).length,
    producer: ((s.match(/\/Producer\s*\(((?:\\.|[^\\)]){0,120})\)/) || [])[1] || '').replace(/\\(.)/g, '$1')
      .replace(/[^\x20-\x7e]/g, ''),
  };
}

async function certificateStep(n) {
  const CERTPAGE = '/local/sentientia_pages/certificates.php';
  const result = { page: CERTPAGE, cards: 0, emptyState: false, countLabel: '', certificateLinks: 0,
    downloadLinkTarget: '', toolListLinks: 0, saved: null, previews: [] };
  let rec = records.find(r => normalise(r.url) === CERTPAGE);
  if (rec) {
    await go(page, CERTPAGE).catch(() => {});
  } else {
    rec = await visit({ url: CERTPAGE, src: 'certificate', name: 'Certificates' }, n);
    records.push(rec);
  }
  const info = await page.evaluate(() => {
    const links = [...document.querySelectorAll('a[href*="/admin/tool/certificate/"][href*="code="]')].map(a => a.href);
    const count = document.querySelector('.airpay-cert-gallery__count');
    const grid = document.querySelector('.airpay-cert-gallery__grid');
    return { links, cards: grid ? grid.children.length : 0, empty: !!document.querySelector('.ap-empty-state'),
      count: count ? count.innerText.trim() : '' };
  }).catch(() => ({ links: [], cards: 0, empty: false, count: '' }));
  result.cards = info.cards;
  result.emptyState = info.empty;
  result.countLabel = mask(info.count);
  result.certificateLinks = info.links.length;
  if (info.links.length) {
    const p = new URL(info.links[0]).pathname;
    result.downloadLinkTarget = /\/index\.php$/.test(p)
      ? 'verify page /admin/tool/certificate/index.php (HTML form), not the PDF'
      : p;
  }
  let codes = info.links.map(h => new URL(h).searchParams.get('code')).filter(Boolean);
  if (!codes.length) {
    // The tool's own list, and any certificate link seen during the walk (course header download icon).
    for (const h of certLinksSeen) {
      const c = new URL(h).searchParams.get('code');
      if (c && c !== 'previewing') codes.push(c);
    }
    const res = await go(page, '/admin/tool/certificate/my.php').catch(() => null);
    if (res && res.status() < 400) {
      const more = await page.evaluate(() => [...document.querySelectorAll('a[href*="/admin/tool/certificate/view.php"][href*="code="]')]
        .map(a => a.href)).catch(() => []);
      result.toolListLinks = more.length;
      for (const h of more) codes.push(new URL(h).searchParams.get('code'));
    } else {
      result.toolListLinks = res ? `HTTP ${res.status()}` : 'not reachable';
    }
  }
  codes = [...new Set(codes.filter(Boolean))];
  result.issuedCodesFound = codes.length;
  if (codes.length) {
    const f = await fetchPdf(`/admin/tool/certificate/view.php?code=${encodeURIComponent(codes[0])}`);
    result.chain = f.chain;
    if (f.ok) {
      fs.writeFileSync(path.join(OUT, 'certificate.pdf'), f.body);
      result.saved = 'certificate.pdf';
      Object.assign(result, pdfFacts(f.body), { contentType: f.contentType });
    } else {
      result.note = f.note || 'no PDF';
    }
  } else {
    result.note = 'no issued certificate for this persona';
  }

  if (CERT_PREVIEW) {
    const res = await go(page, '/admin/tool/certificate/manage_templates.php').catch(() => null);
    if (res && res.status() < 400) {
      const ids = await page.evaluate(() => [...document.querySelectorAll('a[href*="/admin/tool/certificate/view.php"][href*="preview=1"]')]
        .map(a => new URL(a.href).searchParams.get('templateid')).filter(Boolean)).catch(() => []);
      for (const id of [...new Set(ids)].slice(0, 3)) {
        const f = await fetchPdf(`/admin/tool/certificate/view.php?templateid=${encodeURIComponent(id)}&preview=1&code=previewing`);
        const item = { templateid: id, saved: null, chain: f.chain };
        if (f.ok) {
          const file = `certificate-preview-${id}.pdf`;
          fs.writeFileSync(path.join(OUT, file), f.body);
          Object.assign(item, { saved: file }, pdfFacts(f.body));
        } else {
          item.note = f.note || 'no PDF';
        }
        result.previews.push(item);
      }
      if (!ids.length) result.previews.push({ note: 'no template preview links on manage_templates.php' });
    } else {
      result.previews.push({ note: `manage_templates.php not available to this persona (${res ? 'HTTP ' + res.status() : 'no response'})` });
    }
  }
  rec.certificate = result;
  writeResults();
  return result;
}

// ---------------------------------------------------------------- main
const started = Date.now();
let certificate = null;
let exitCode = 0;
try {
  try {
    browser = await launch();
  } catch (e) {
    browser = await chromium.launch({ headless: true });
  }
  try {
    await login();
  } catch (e) {
    aborted = String(e.message).split('\n')[0].slice(0, 200);
    exitCode = 3;
    throw e;
  }

  if (MODE !== 'certificate') {
    // Visit order by tier, then discovery order: the dashboard, its navigation, the --urls list, course links
    // harvested from My Courses / the catalogue, then the built-in role list. --max cuts from the end.
    const TIER = { dashboard: 0, nav: 1, urls: 2, mycourses: 3, catalog: 3, default: 4 };
    const queue = [];
    const seen = new Set();
    let seq = 0;
    const enqueue = (href, src, name = '') => {
      const u = normalise(href);
      if (!u) return false;
      const why = skipReason(u);
      if (why) {
        if (skipped.length < 80 && !skipped.some(s => s.url === u)) skipped.push({ url: u, source: src, rule: why });
        return false;
      }
      if (seen.has(u)) return false;
      seen.add(u);
      queue.push({ url: u, src, name, tier: TIER[src.split(':')[0]] ?? 5, seq: seq++ });
      queue.sort((a, b) => a.tier - b.tier || a.seq - b.seq);
      return true;
    };
    enqueue('/my/', 'dashboard', 'Dashboard');
    let n = 0;
    while (queue.length && n < MAX && !aborted) {
      const entry = queue.shift();
      n++;
      const rec = await visit(entry, n);
      records.push(rec);
      writeResults();
      console.log(`${String(n).padStart(2, '0')} ${rec.issues.length ? 'ISSUES' : 'ok    '} ${String(rec.status).padEnd(3)} ${entry.url}`
        + (rec.redirected ? ` -> ${rec.final}` : '') + (rec.duplicateOf ? ` (same page as ${rec.duplicateOf})` : '')
        + (rec.issues.length ? `  [${rec.issues.length}] ${rec.issues[0].slice(0, 140)}` : '')
        + ` (${Math.round(rec.ms / 1000)}s)`);
      // The shell navigation of every page (some layouts carry links others do not, e.g. the footer).
      if (!ARGS['no-nav'] && !rec.duplicateOf && !rec.issues.some(i => i.startsWith('navigation failed'))) {
        for (const l of await harvestNav(n === 1)) enqueue(l.href, l.src, l.name);
      }
      if (n === 1) {
        if (typeof ARGS.urls === 'string') {
          for (const x of readUrlsFile(ARGS.urls)) {
            if (/^https?:/i.test(x.url) && !LOCAL.test(x.url)) {
              skipped.push({ url: x.url, source: 'urls', rule: 'non-local host' });
              continue;
            }
            enqueue(x.url, 'urls', x.name);
          }
        }
        if (!ARGS['no-defaults']) {
          for (const u of [...COMMON, ...DEFAULT_SURFACES[ROLE]]) enqueue(u, `default:${ROLE}`);
        }
      }
      const fp = new URL(page.url()).pathname;
      if (NCOURSES && /\/local\/sentientia_catalog\/mycourses\.php$/.test(fp)) {
        for (const h of await harvestCourses('mycourses')) enqueue(h, 'mycourses');
      } else if (NCOURSES && /\/local\/sentientia_catalog\/(index|public)\.php$/.test(fp)) {
        for (const h of await harvestCourses('catalog')) enqueue(h, 'catalog');
      }
    }
    if (queue.length && !aborted) {
      for (const q of queue) skipped.push({ url: q.url, source: q.src, rule: `--max=${MAX} reached` });
    }
  }

  if (MODE !== 'walk' && !aborted) {
    certificate = await certificateStep(records.length + 1);
  }
} catch (e) {
  if (!exitCode) {
    exitCode = 3;
    aborted = aborted || 'walk error: ' + String(e.message).split('\n')[0].slice(0, 200);
  }
} finally {
  if (context) await context.close().catch(() => {});
  if (browser) await browser.close().catch(() => {});
}
if (aborted && !exitCode) exitCode = 3;
writeResults();

// ---------------------------------------------------------------- summary
const tally = { http: 0, errorbox: 0, debug: 0, console: 0, failedreq: 0, strings: 0, overflow390: 0, overflow1440: 0,
  overlap: 0, clipped: 0, navfail: 0 };
for (const r of records) {
  if (r.status >= 400) tally.http++;
  if (r.errorBox || r.exception || r.errorCode) tally.errorbox++;
  if (r.debugging.length || r.php.length) tally.debug++;
  if (r.console.length) tally.console++;
  if (r.failedRequests.length) tally.failedreq++;
  if (r.missingStrings.length) tally.strings++;
  if (r.overflow[390] && r.overflow[390].doc > 1) tally.overflow390++;
  if (r.overflow[1440] && r.overflow[1440].doc > 1) tally.overflow1440++;
  if ((r.overlaps[1440] || []).length || (r.overlaps[390] || []).length) tally.overlap++;
  if ((r.clipped[1440] || []).length || (r.clipped[390] || []).length) tally.clipped++;
  if (r.issues.some(i => i.startsWith('navigation failed'))) tally.navfail++;
}
const withIssues = records.filter(r => r.issues.length).length;
const previewsSaved = certificate ? certificate.previews.filter(p => p.saved).length : 0;
const certText = (!certificate ? (MODE === 'walk' ? 'not run' : 'not reached')
  : certificate.saved ? `saved ${certificate.saved} (${certificate.bytes} bytes, ${certificate.pages} page(s))`
    : `none (${certificate.note})`) + (previewsSaved ? `; ${previewsSaved} template preview PDF(s) saved` : '');
const summary = {
  user: USER, role: ROLE, mode: MODE, pages: records.length, clean: records.length - withIssues, withIssues, tally,
  certificate, skipped, aborted, out: OUT, minutes: Math.round((Date.now() - started) / 6000) / 10,
  at: new Date().toISOString(),
};
fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
const tallyText = Object.entries(tally).filter(([, v]) => v).map(([k, v]) => `${k} ${v}`).join(', ') || 'none';
console.log(`surface_walk ${USER} (${ROLE}, ${MODE}): ${records.length} page(s), ${records.length - withIssues} clean, `
  + `${withIssues} with issues [${tallyText}]; certificate: ${certText}; ${skipped.length} link(s) skipped; `
  + `${summary.minutes} min${aborted ? '; ABORTED: ' + aborted : ''}; out ${OUT}`);
process.exit(exitCode);
