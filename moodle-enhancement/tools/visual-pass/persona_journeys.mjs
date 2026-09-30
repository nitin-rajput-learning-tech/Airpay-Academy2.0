// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY persona-journey harness for Sentientia LMS. It replaces the manual
// UAT persona testing of docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md (Phase 1
// table): for every persona it logs in, walks the journey in journeys.json one
// page at a time, and records PASS / CHECK / FAIL / SKIP per step.
//
//   node persona_journeys.mjs [--persona learner,manager] [--from [persona:]step]
//                             [--steps a,b] [--limit N] [--retry-failed] [--fresh]
//                             [--no-shots] [--out <dir>]
//   node persona_journeys.mjs --list [--check-files]      (no browser, no logins)
//
// Prerequisites (both LOCAL, run with cwd = C:/xampp/htdocs/moodle5/public):
//   php <this dir>/provision_local_personas.php     -> .personas.local.json (passwords, gitignored)
//   php <this dir>/seed_journey_content.php         -> .journey-context.local.json
//
// Safety: the base URL is fixed to http://localhost:8080 and every request that
// is not localhost is aborted in the browser (except the two static Google Fonts
// hosts, so screenshots use the real typeface), so this can never reach UAT or
// production. A dropped connection (local Apache crash/restart) waits for port
// 8080 to accept again and re-runs the step once. Passwords are read from the
// gitignored file and never printed or written to the results. Write steps are
// idempotent (enrol / cart add+remove / language session); nothing is paid,
// erased or suspended. One Chrome, one page at a time, headless.
//
// Per step, on top of the journey's own expectations, the same cross-cutting
// checks run on every page: HTTP status < 400; no Moodle error box, "Debug info",
// "Stack trace", "Coding error" or PHP warning text; no "[[missing string]]"
// markers; JS console errors captured; no horizontal scroll at 590 and 390 px;
// screenshots at 1440 (desktop) and 590 (mobile) saved as
// <out>/NN-<persona>-<step>-{desktop,mobile}.png (NN = the step's position in
// the full journey, stable when a run is filtered).
//
// Status: FAIL = a hard assertion failed | CHECK = hard assertions passed but a
// soft expectation missed / console errors / a redirect worth a look |
// SKIP = flag off, context missing or login failed | PASS.
// results.json + results.md are rewritten after every step and merged across
// runs (a resumed or filtered run only replaces the steps it re-ran).

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import net from 'node:net';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const BASE = 'http://localhost:8080';
const LOCAL = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?(\/|$)/i;
if (!LOCAL.test(BASE)) {
  throw new Error('persona journeys are local-only');
}
// The only non-local hosts a page may load from: the two Google Fonts hosts (static
// CSS/font files, no cookies or credentials) so screenshots render in the real
// typeface. Everything else off-host is aborted.
const ALLOWED_EXTERNAL = /^https?:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com)\//i;
const MOODLE_PUBLIC = process.env.MOODLE_PUBLIC || 'C:/xampp/htdocs/moodle5/public';

// ---------------------------------------------------------------- arguments
const argv = process.argv.slice(2);
const has = n => argv.includes(n);
const opt = (n, d = '') => {
  const i = argv.indexOf(n);
  return i >= 0 && argv[i + 1] !== undefined && !String(argv[i + 1]).startsWith('--') ? argv[i + 1] : d;
};
if (has('--base') || has('--url')) {
  console.error('The base URL is fixed to http://localhost:8080 - this harness never targets another host.');
  process.exit(2);
}
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-09-30/personas')));
const FILTER_PERSONA = opt('--persona').split(',').map(s => s.trim().replace(/^vp_/, '')).filter(Boolean);
const FROM = opt('--from');
const ONLY_STEPS = opt('--steps').split(',').map(s => s.trim()).filter(Boolean);
const LIMIT = parseInt(opt('--limit', '0'), 10) || 0;
const NOSHOTS = has('--no-shots');
const FRESH = has('--fresh');
const RETRY_FAILED = has('--retry-failed');

// ---------------------------------------------------------------- journeys
const J = JSON.parse(fs.readFileSync(path.join(HERE, 'journeys.json'), 'utf8'));
const LANGNAME = { hi: 'Hindi', sw: 'Swahili', en: 'English', mr: 'Marathi', kn: 'Kannada' };

function expandSteps(steps) {
  const out = [];
  for (const s of steps) {
    if (s.macro === 'onboard') {
      // The dashboard sends every non-admin, non-supervisor account to the
      // 4-step onboarding wizard until it is completed (theme layout/dashboard.php).
      out.push({
        id: 'onboarding-complete', name: 'Complete the onboarding wizard (first login only)',
        handler: 'onboardingComplete', expect: { finalUrl: '^/my/' },
      });
      continue;
    }
    if (s.macro !== 'langPair') {
      out.push(s);
      continue;
    }
    const langs = s.langs || ['hi'];
    for (const to of langs) {
      out.push({
        id: `lang-${to}`, name: `Switch to ${LANGNAME[to] || to}`, handler: 'langToggle',
        args: { to, url: s.url },
        expect: to === 'hi' ? { lang: 'hi', devanagari: true } : { lang: to },
      });
    }
    if (s.back !== false) {
      out.push({ id: 'lang-en', name: 'Back to English', handler: 'langToggle', args: { to: 'en', url: s.url }, expect: { lang: 'en' } });
    }
  }
  return out;
}

// The full plan, in file order; index is stable and used in screenshot names.
const FULL = [];
const personaMeta = {};
for (const [pkey, p] of Object.entries(J.personas)) {
  personaMeta[pkey] = p;
  for (const step of expandSteps(p.steps)) {
    FULL.push({ pkey, persona: p, step, index: FULL.length + 1 });
  }
}
const PAD = Math.max(2, String(FULL.length).length);
const firstIndexOf = pkey => FULL.find(e => e.pkey === pkey).index;

// ---------------------------------------------------------------- --list
if (has('--list')) {
  const checkFiles = has('--check-files');
  let bad = 0;
  let last = '';
  for (const e of FULL) {
    if (e.pkey !== last) {
      console.log(`\n${e.pkey}  (${e.persona.username || 'no login'}, tenant ${e.persona.tenant}) - ${e.persona.label}`);
      last = e.pkey;
    }
    let extra = '';
    if (checkFiles && e.step.url) {
      const p = e.step.url.split('?')[0];
      if (!p.includes('{{')) {
        const ok = fs.existsSync(path.join(MOODLE_PUBLIC, p));
        extra = ok ? '' : '   <-- FILE NOT FOUND under ' + MOODLE_PUBLIC;
        if (!ok) bad += 1;
      }
    }
    console.log(`  ${String(e.index).padStart(PAD, '0')} ${e.step.id.padEnd(24)} ${(e.step.handler || 'goto').padEnd(14)} ${e.step.url || ''}${extra}`);
  }
  console.log(`\n${FULL.length} steps, ${Object.keys(J.personas).length} personas${checkFiles ? `, ${bad} missing file(s)` : ''}`);
  process.exit(bad ? 1 : 0);
}

// ---------------------------------------------------------------- context
const readJson = f => (fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')) : null);
const creds = readJson(path.join(HERE, '.personas.local.json'));
if (!creds) {
  console.error('.personas.local.json missing - run provision_local_personas.php first (cwd = moodle5/public).');
  process.exit(2);
}
const CTX = readJson(path.join(HERE, '.journey-context.local.json')) || {};
CTX.users = Object.fromEntries(Object.entries(creds).map(([k, v]) => [k, v.id]));
if (!CTX.courses) {
  console.error('WARNING: .journey-context.local.json missing - steps that need seeded content will be SKIPped. Run seed_journey_content.php.');
}

// {{key}} -> value; {{re:key}} -> regex-escaped value. Collects unresolved keys.
function resolveDeep(node, me, missing) {
  if (typeof node === 'string') {
    return node.replace(/\{\{(re:)?([A-Za-z0-9_.]+)\}\}/g, (m, re, key) => {
      let v = key === 'me' ? me : key.split('.').reduce((o, k) => (o === undefined || o === null ? undefined : o[k]), CTX);
      if (v === undefined || v === null || v === '') {
        missing.add(key);
        return '';
      }
      v = String(v);
      return re ? v.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&') : v;
    });
  }
  if (Array.isArray(node)) return node.map(n => resolveDeep(n, me, missing));
  if (node && typeof node === 'object') {
    return Object.fromEntries(Object.entries(node).map(([k, v]) => [k, resolveDeep(v, me, missing)]));
  }
  return node;
}

// ---------------------------------------------------------------- results
fs.mkdirSync(OUT, { recursive: true });
const RES_JSON = path.join(OUT, 'results.json');
const RES_MD = path.join(OUT, 'results.md');
let results = [];
if (!FRESH) {
  const prev = readJson(RES_JSON);
  if (prev && Array.isArray(prev.results)) results = prev.results;
}
const orderKey = r => {
  if (r.step === 'login') return firstIndexOf(r.persona) - 0.5;
  const e = FULL.find(x => x.pkey === r.persona && x.step.id === r.step);
  return e ? e.index : 9999;
};
function upsert(rec) {
  const i = results.findIndex(r => r.persona === rec.persona && r.step === rec.step);
  if (i >= 0) results[i] = rec;
  else results.push(rec);
}
const cell = s => String(s || '').replace(/\s+/g, ' ').replace(/\|/g, '\\|').slice(0, 320);
function writeResults() {
  results.sort((a, b) => orderKey(a) - orderKey(b));
  const tally = results.reduce((a, r) => { a[r.status] = (a[r.status] || 0) + 1; return a; }, {});
  fs.writeFileSync(RES_JSON, JSON.stringify({
    generated: new Date().toISOString(), base: BASE, tally, flags: CTX.flags || null, results,
  }, null, 1));
  const md = [
    '# Persona journeys - results', '',
    `Generated ${new Date().toISOString()} against ${BASE} (local XAMPP). Tally: ` +
      ['PASS', 'CHECK', 'FAIL', 'SKIP'].map(k => `${k} ${tally[k] || 0}`).join(' | '), '',
    'PASS = all assertions held. CHECK = hard assertions held but a soft expectation missed, console errors were logged, or a redirect is worth a look. FAIL = a hard assertion failed. SKIP = flag off, seeded context missing, or login failed.', '',
  ];
  if (CTX.flags) {
    md.push('Feature flags at seed time (tenant /1, /77, /177): ' + Object.entries(CTX.flags)
      .map(([k, v]) => `${k}=${[1, 77, 177].map(t => (v[t] === true ? 'ON' : v[t] === false ? 'off' : '?')).join('/')}`).join('; '), '');
  }
  for (const pkey of Object.keys(J.personas)) {
    const rows = results.filter(r => r.persona === pkey);
    if (!rows.length) continue;
    const p = J.personas[pkey];
    md.push(`## ${pkey} - ${p.username || 'no login'} - ${p.label}`, '', `Validates: ${p.validates}`, '',
      '| # | Step | Result | Reason / notes | Console errors | Screens |', '|---|---|---|---|---|---|');
    for (const r of rows) {
      const why = r.status === 'FAIL' ? r.fails.join('; ') : r.status === 'CHECK' ? r.softs.join('; ') : r.status === 'SKIP' ? r.skipReason : '';
      const notes = r.notes && r.notes.length ? ` (${r.notes.join('; ')})` : '';
      const shots = (r.shots || []).map(s => `[${s.endsWith('desktop.png') ? 'd' : 'm'}](${s})`).join(' ');
      md.push(`| ${r.index || '-'} | ${cell(r.name || r.step)} | ${r.status} | ${cell(why + notes)} | ${(r.consoleErrors || []).length ? cell((r.consoleErrors || []).slice(0, 2).join(' / ')) : ''} | ${shots} |`);
    }
    md.push('');
  }
  fs.writeFileSync(RES_MD, md.join('\n') + '\n');
}

// ---------------------------------------------------------------- checks
const REFUSAL = /do not currently have permissions|you do not have (access|permission)|not available to you|do not have access to this tenant|Only a cross-tenant|cannot be accessed|Access denied|requires a cross-tenant|Sorry, but you do not|not enabled for your tenant|nopermissions/i;
// A real defect on any page: a coding error, an uncaught exception, a PHP message.
const PHPERR = /Coding error detected|Exception - |(?:Warning|Notice|Deprecated|Fatal error): .{0,200} on line \d+|Call to undefined|Uncaught /;
// Developer-debug rendering of ANY Moodle exception. On a page that expected a
// permission refusal this is just how core prints the refusal when debugdisplay
// is on locally, so it only fails a page that is not a refusal.
const DEBUGTXT = /Debug info:|Error code: |Stack trace:/;
const MISSING = /\[\[[A-Za-z0-9_:/.,-]+\]\]/g;
const UNRENDERED = /\{\{[#^/>]?[A-Za-z_.]+\}\}/;
const DEVANAGARI = /[\u0900-\u097F]{2,}/;
// Self-inflicted (we abort every non-local request) or harmless browser noise.
const IGNORE_CONSOLE = [/ERR_FAILED/i, /ERR_BLOCKED_BY_CLIENT/i, /ERR_NETWORK_CHANGED/i, /favicon/i, /Download the React DevTools/i];

function measureOverflow() {
  const de = document.documentElement;
  const vh = window.innerHeight;
  const offenders = [];
  for (const el of document.querySelectorAll('body *')) {
    if (el.scrollWidth <= el.clientWidth + 1) continue;
    const cs = getComputedStyle(el);
    if (!/(auto|scroll)/.test(cs.overflowX)) continue;
    if (el.clientHeight < vh * 0.5) continue;
    offenders.push((el.id ? '#' + el.id : el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0]) + ` (${el.scrollWidth}>${el.clientWidth})`);
    if (offenders.length >= 3) break;
  }
  return { doc: de.scrollWidth - de.clientWidth, sw: de.scrollWidth, cw: de.clientWidth, offenders };
}

function gatherPage() {
  const box = document.querySelector('[data-rel="fatalerror"], .errorbox');
  const alerts = [...document.querySelectorAll('.alert-danger, .alert-warning, .notifyproblem, [role="alert"]')]
    .map(e => e.innerText.trim()).filter(Boolean).join(' | ').slice(0, 400);
  return {
    url: location.pathname + location.search,
    path: location.pathname,
    title: document.title,
    text: document.body ? document.body.innerText : '',
    headings: [...document.querySelectorAll('h1,h2')].slice(0, 8).map(e => e.innerText.trim()).filter(Boolean),
    errBox: box ? box.innerText.trim().slice(0, 240) : '',
    alerts,
    htmlLang: document.documentElement.getAttribute('lang') || '',
  };
}

const norm = s => String(s).toLowerCase().replace(/\s+/g, ' ');
function leaks(text, listKey) {
  const names = CTX[listKey] || [];
  const hay = norm(text);
  return names.filter(n => hay.includes(norm(n)));
}

// ------------------------------------------------ slow / crashing local Apache
// The XAMPP box sometimes drops connections (Apache worker crash, watchdog
// restart). A connection-level failure waits for port 8080 to accept again.
const CONN_ERR = /ERR_CONNECTION_(RESET|REFUSED|CLOSED|ABORTED)|ERR_EMPTY_RESPONSE|ECONNRESET|ECONNREFUSED|socket hang up|ERR_NETWORK_CHANGED/i;
async function waitForServer(maxMs = 240000) {
  const t0 = Date.now();
  while (Date.now() - t0 < maxMs) {
    const up = await new Promise(resolve => {
      const sock = net.createConnection({ host: '127.0.0.1', port: 8080 });
      sock.setTimeout(3000);
      sock.on('connect', () => { sock.destroy(); resolve(true); });
      sock.on('timeout', () => { sock.destroy(); resolve(false); });
      sock.on('error', () => resolve(false));
    });
    if (up) {
      await new Promise(r => setTimeout(r, 6000));
      return true;
    }
    await new Promise(r => setTimeout(r, 3000));
  }
  return false;
}

// ---------------------------------------------------------------- step env
function makeEnv(context, e, me, S) {
  const env = {
    context, e, me, S, pkey: e.pkey, persona: e.persona, ctx: CTX, page: null, blocked: new Set(),
    rec: {
      persona: e.pkey, username: e.persona.username, step: e.step.id, name: e.step.name, index: e.index,
      url: '', status: 'PASS', fails: [], softs: [], notes: [], http: 0, finalUrl: '', title: '',
      consoleErrors: [], badResources: [], overflow: {}, shots: [], blocked: [], ms: 0, at: new Date().toISOString(),
    },
    fail(msg) { this.rec.fails.push(String(msg).slice(0, 400)); },
    soft(msg) { this.rec.softs.push(String(msg).slice(0, 400)); },
    note(msg) { this.rec.notes.push(String(msg).slice(0, 200)); },
  };
  env.nav = url => gotoRetry(env, url);
  env.settle = () => settle(env);
  return env;
}

async function gotoRetry(env, url) {
  const abs = /^https?:/i.test(url) ? url : BASE + url;
  if (!LOCAL.test(abs)) throw new Error('refusing a non-local URL');
  if (!env.rec.url) env.rec.url = url.replace(BASE, '');
  for (let attempt = 1; attempt <= 2; attempt++) {
    try {
      await env.page.goto(abs, { waitUntil: 'domcontentloaded', timeout: 240000 });
      if (env.rec.http >= 500 && attempt === 1) {
        env.note(`HTTP ${env.rec.http} on first try, retried once`);
        await env.page.waitForTimeout(10000);
        continue;
      }
      return;
    } catch (err) {
      const msg = String(err.message).split('\n')[0];
      if (/Download is starting/i.test(msg)) {
        env.rec.download = true;
        return;
      }
      if (attempt === 2) throw err;
      if (CONN_ERR.test(msg)) {
        env.note('connection dropped (Apache restart?) - waited for port 8080, retried once');
        await waitForServer();
      } else {
        env.note(`navigation error (${msg.slice(0, 90)}), retried once`);
        await env.page.waitForTimeout(10000);
      }
    }
  }
}

async function settle(env) {
  const { page, S } = env;
  await page.waitForLoadState('load', { timeout: 90000 }).catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  // An AJAX table that timed out on this slow box: reload (twice at most).
  for (let i = 0; i < 2; i++) {
    const t = await page.locator('body').innerText().catch(() => '');
    if (!/Failed to load data/i.test(t)) break;
    env.note('a table said "Failed to load data" - reloaded');
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 240000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  }
  if (S.waitFor && !env.waited) {
    const ok = await page.locator(S.waitFor).first().waitFor({ timeout: 120000 }).then(() => true).catch(() => false);
    env.waited = ok;
    if (!ok) env.fail(`expected element never appeared: ${S.waitFor}`);
  }
  await page.waitForTimeout(500);
}

// ---------------------------------------------------------------- handlers
const HANDLERS = {
  // Walk the 4-step onboarding wizard and submit it (idempotent: once completed
  // the wizard URL redirects away and this only records that).
  async onboardingComplete(env) {
    await env.nav('/local/sentientia_pages/onboarding.php');
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    if (!(await env.page.locator('#ap-onboard-form').count())) {
      env.note('onboarding already completed (the wizard redirected away)');
      return;
    }
    for (const n of [1, 2, 3]) {
      await env.page.locator(`[data-step="${n}"] .ap-onboard__btn--primary`).first().click();
    }
    await env.page.locator('#ap-onboard-form button[type="submit"]').first().click({ noWaitAfter: true });
    await env.page.waitForURL(u => !/onboarding\.php/.test(String(u)), { timeout: 240000 });
    env.note('walked the 4-step onboarding wizard and finished it');
  },

  // Catalog one-click enrol (flag-gated), idempotent: an already-enrolled learner
  // just follows "Continue Learning" to the same course page.
  async enrolFree(env) {
    const c = env.ctx.courses[env.S.args.course];
    await env.nav(`/local/sentientia_catalog/course.php?id=${c.id}`);
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    const enrol = env.page.locator('form:has(input[name="action"][value="enrolnow"]) button[type="submit"]');
    const cont = env.page.locator('a:has-text("Continue Learning")');
    if (await enrol.count()) {
      await enrol.first().click({ noWaitAfter: true });
      await env.page.waitForURL(u => /\/course\/view\.php/.test(String(u)), { timeout: 240000 });
      env.note('enrolled via the one-click "Enrol now" button');
    } else if (await cont.count()) {
      await cont.first().click({ noWaitAfter: true });
      await env.page.waitForURL(u => /\/course\/view\.php/.test(String(u)), { timeout: 240000 });
      env.note('already enrolled (idempotent re-run): followed "Continue Learning"');
    } else {
      const cta = await env.page.locator('body').innerText().catch(() => '');
      env.fail('no one-click "Enrol now" and not enrolled - flag off for this tenant, or the course is not free/visible: ' + norm(cta).slice(0, 160));
    }
  },

  // Language switch: follow the theme's own language-menu link when it is in the
  // DOM, else ?lang=xx (same effect: Moodle stores it in the session only).
  async langToggle(env) {
    const { to, url } = env.S.args;
    await env.nav(url);
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    const link = env.page.locator(`a[href*="lang=${to}"]`);
    let target;
    if (await link.count()) {
      target = new URL(await link.first().getAttribute('href'), BASE).toString();
      env.note('followed the language menu link');
    } else {
      const u = new URL(BASE + url);
      u.searchParams.set('lang', to);
      target = u.toString();
      env.note(`no language link in the page - used ?lang=${to}`);
    }
    if (!LOCAL.test(target)) {
      env.fail('language link points off-host: ' + target);
      return;
    }
    await env.nav(target);
  },

  // A datatable page: load it, type into the table search box, wait for the AJAX reply.
  async tableSearch(env) {
    await env.nav(env.S.url);
    await env.settle();
    const input = env.page.locator('[data-airpay-table-search]').first();
    if (!(await input.count())) {
      env.fail('no table search box on the page');
      return;
    }
    const reply = env.page.waitForResponse(r => /lib\/ajax\/service\.php/.test(r.url()), { timeout: 120000 }).catch(() => null);
    await input.fill(env.S.args.search);
    await reply;
    await env.page.waitForTimeout(1500);
  },

  // A CSV endpoint fetched with the browser session (no page, no download dialog).
  async csvExport(env) {
    const url = BASE + env.S.url;
    env.rec.url = env.S.url;
    const resp = await env.context.request.get(url, { maxRedirects: 0, timeout: 240000, failOnStatusCode: false });
    const status = resp.status();
    const ctype = resp.headers()['content-type'] || '';
    const body = await resp.text();
    env.rec.http = status;
    env.rec.finalUrl = env.S.url;
    const denied = env.S.args && env.S.args.expect === 'denied';
    if (PHPERR.test(body)) env.fail('PHP/Moodle error text in the response: ' + (body.match(PHPERR) || [''])[0]);
    if (!denied && DEBUGTXT.test(body)) env.fail('debug output in the response: ' + (body.match(DEBUGTXT) || [''])[0]);
    if (denied) {
      if (/text\/csv/i.test(ctype)) env.fail('export was allowed (text/csv returned) - it must be refused for this persona');
      else if (status === 403 || REFUSAL.test(body)) env.note(`refused as expected (HTTP ${status})`);
      else if (status >= 300 && status < 400) env.note(`refused by redirect (HTTP ${status})`);
      else env.fail(`expected a refusal, got HTTP ${status} ${ctype}`);
      return;
    }
    if (status !== 200) env.fail(`HTTP ${status}, expected 200`);
    if (!/text\/csv/i.test(ctype)) env.fail(`content-type "${ctype}", expected text/csv`);
    const lines = body.split(/\r?\n/).filter(Boolean);
    if (lines.length < 1) env.fail('empty CSV');
    else env.note(`${lines.length} line(s); header: ${lines[0].replace(/^\uFEFF/, '').slice(0, 90)}`);
    if (env.S.isolation) {
      const l = leaks(body, env.S.isolation);
      if (l.length) env.fail(`LEAK: ${l.length} /1-only course name(s) in the export: ${l.slice(0, 4).join('; ')}`);
    }
  },

  // Public storefront session cart: the "Add to Cart" button on a priced course.
  async cartAddSession(env) {
    const id = env.ctx.courses.publicPriced.id;
    await env.nav(`/local/sentientia_catalog/course.php?id=${id}`);
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    const btn = env.page.locator('form:has(input[name="action"][value="addtocart"]) button[type="submit"]');
    if (!(await btn.count())) {
      env.fail('no "Add to Cart" button on the priced course page');
      return;
    }
    await btn.first().click({ noWaitAfter: true });
    await env.page.waitForURL(u => /added=1/.test(String(u)), { timeout: 240000 });
  },

  // The order cart (local_sentientia_cart) is filled through its AJAX service, as
  // its own add_to_cart.js does; then the cart page is reloaded to show the item.
  async cartAddDb(env) {
    const id = env.ctx.courses.publicPriced.id;
    await env.nav('/local/sentientia_cart/index.php');
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    const r = await callService(env.page, 'local_sentientia_cart_add_item', { courseid: id });
    if (r.error) env.fail(`add_item refused: ${r.error}`);
    else env.note('added through local_sentientia_cart_add_item');
    await env.nav('/local/sentientia_cart/index.php');
  },

  // Undo the cart adds so a re-run starts clean; nothing is ever paid.
  async cartCleanup(env) {
    const id = env.ctx.courses.publicPriced.id;
    await env.nav('/local/sentientia_cart/index.php');
    await env.page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    const r = await callService(env.page, 'local_sentientia_cart_remove_item', { courseid: id });
    if (r.error) env.soft(`remove_item: ${r.error}`);
    const sesskey = await env.page.evaluate(() => (window.M && M.cfg ? M.cfg.sesskey : ''));
    if (sesskey) await env.nav(`/local/sentientia_catalog/cart.php?action=clear&sesskey=${sesskey}`);
    await env.nav('/local/sentientia_cart/index.php');
  },
};

// One Moodle AJAX web-service call made from the page (its session + sesskey).
async function callService(page, methodname, args) {
  return page.evaluate(async ({ methodname, args }) => {
    const url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey + '&info=' + methodname;
    try {
      const resp = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify([{ index: 0, methodname, args }]),
      });
      const body = JSON.parse(await resp.text());
      const first = Array.isArray(body) ? body[0] : body;
      if (first && first.error) {
        const ex = first.exception || {};
        return { error: String(ex.errorcode || ex.message || 'service error').slice(0, 200) };
      }
      return { error: '' };
    } catch (err) {
      return { error: 'fetch failed: ' + String(err.message).slice(0, 120) };
    }
  }, { methodname, args });
}

// ---------------------------------------------------------------- assertions
function evalExpect(env, info) {
  const S = env.S;
  const x = S.expect || {};
  const fails = [];
  const softs = [];
  const re = s => new RegExp(s, 'i');
  const headtext = [info.title, ...info.headings].join(' | ');
  if (x.heading && !re(x.heading).test(headtext)) fails.push(`heading/title did not match /${x.heading}/ (got: ${headtext.slice(0, 120)})`);
  for (const t of x.text || []) if (!re(t).test(info.text)) fails.push(`text not found: /${t}/`);
  for (const t of x.softText || []) if (!re(t).test(info.text)) softs.push(`expected text missing: /${t}/`);
  for (const t of x.denyText || []) if (re(t).test(info.text)) fails.push(`forbidden text present: /${t}/`);
  if (x.finalUrl && !re(x.finalUrl).test(info.url)) fails.push(`final URL ${info.url.slice(0, 100)} did not match /${x.finalUrl}/`);
  if (x.lang && !info.htmlLang.toLowerCase().startsWith(x.lang)) fails.push(`html lang is "${info.htmlLang}", expected "${x.lang}"`);
  if (x.devanagari && !DEVANAGARI.test(info.text)) fails.push('no Devanagari text on the page (Hindi not applied)');
  if (S.isolation) {
    const l = leaks(info.text, S.isolation);
    if (l.length) fails.push(`LEAK: ${l.length} /1-only course name(s) visible: ${l.slice(0, 4).join('; ')}`);
  }
  return { fails, softs };
}

async function selectorChecks(env) {
  const x = env.S.expect || {};
  const fails = [];
  const softs = [];
  for (const sel of x.selectors || []) if (!(await env.page.locator(sel).count())) fails.push(`element not found: ${sel}`);
  for (const sel of x.softSelectors || []) if (!(await env.page.locator(sel).count())) softs.push(`expected element missing: ${sel}`);
  for (const sel of x.denySelectors || []) if (await env.page.locator(sel).count()) fails.push(`forbidden element present: ${sel}`);
  return { fails, softs };
}

async function checkPage(env) {
  const { page, rec, S } = env;
  let info = await page.evaluate(gatherPage);
  rec.finalUrl = info.url;
  rec.title = info.title.slice(0, 120);
  const access = S.access || 'ok';
  const isGuest = !env.persona.username;
  const reqPath = (rec.url || '').split('?')[0];

  if (rec.download) {
    env.fail(access === 'refused' ? 'a file download started - the export was allowed' : 'navigation turned into a file download');
    return;
  }
  // Session lost: a logged-in persona must never land on the login page.
  if (!isGuest && info.path.startsWith('/login/')) {
    env.fail('redirected to the login page (session lost or not authenticated)');
    return;
  }
  const errtext = `${info.errBox} ${info.alerts}`;
  const refused = REFUSAL.test(errtext) || (info.text.length < 6000 && REFUSAL.test(info.text)) || [401, 403].includes(rec.http);
  const phperr = (info.text.match(PHPERR) || [])[0];
  if (phperr) env.fail(`PHP/Moodle error text on the page: ${phperr.slice(0, 120)}`);
  const dbg = (info.text.match(DEBUGTXT) || [])[0];
  if (dbg && !refused) env.fail(`debug output on the page (${dbg.trim()}) - an error that is not a permission refusal`);
  const missing = [...new Set(info.text.match(MISSING) || [])].slice(0, 6);
  if (missing.length) env.fail(`missing-string markers: ${missing.join(' ')}`);
  if (UNRENDERED.test(info.text)) env.soft('unrendered {{template}} placeholder in the page text');

  // Sent elsewhere instead of the requested page: to the login page for a guest, to
  // the dashboard for a logged-in user (how e.g. the cart refuses a tenant).
  const redirectedAway = !!reqPath && info.path !== reqPath
    && (isGuest
      ? info.path.startsWith('/login/')
      : !reqPath.startsWith('/my/') && /^\/(my\/(dashboard\.php|index\.php)?)?$/.test(info.path));

  if (access === 'refused') {
    if (rec.http >= 500) env.fail(`HTTP ${rec.http} instead of a clean refusal`);
    else if (refused) env.note(`refused as expected: ${norm(errtext).slice(0, 80)}`);
    else if (redirectedAway) env.note(`refused by redirect to ${info.path}`);
    else if (info.errBox) env.fail(`an error box that is not a permission refusal: ${norm(info.errBox).slice(0, 120)}`);
    else if (!phperr) env.fail('expected a refusal but the page rendered');
    return;
  }
  if (access === 'either') {
    if (rec.http >= 500) env.fail(`HTTP ${rec.http}`);
    else if (refused) {
      env.note('access refused for this persona');
      return;
    } else if (info.errBox) {
      env.fail(`Moodle error box: ${norm(info.errBox).slice(0, 140)}`);
      return;
    } else if (redirectedAway) {
      env.note(`redirected to ${info.path}`);
      return;
    } else env.note('access granted');
  } else {
    if (rec.http >= 400) env.fail(`HTTP ${rec.http}`);
    if (refused) env.fail(`access refused: ${norm(errtext || info.text).slice(0, 140)}`);
    else if (info.errBox) env.fail(`Moodle error box: ${norm(info.errBox).slice(0, 140)}`);
    else if (redirectedAway && !(S.expect && S.expect.finalUrl)) env.soft(`redirected to ${info.path} instead of ${reqPath}`);
  }
  if (env.rec.fails.length && access === 'ok' && (refused || info.errBox)) return;

  // Journey expectations, polled briefly: the theme fills some regions over AJAX.
  const pollMs = access === 'ok' ? (S.pollMs ?? 15000) : 0;
  const t0 = Date.now();
  let ex = evalExpect(env, info);
  let sx = await selectorChecks(env);
  while ((ex.fails.length || sx.fails.length) && Date.now() - t0 < pollMs) {
    await page.waitForTimeout(1500);
    info = await page.evaluate(gatherPage);
    ex = evalExpect(env, info);
    sx = await selectorChecks(env);
  }
  ex.fails.concat(sx.fails).forEach(m => env.fail(m));
  ex.softs.concat(sx.softs).forEach(m => env.soft(m));
}

async function capture(env) {
  const { page, rec, S } = env;
  const base = `${String(env.e.index).padStart(PAD, '0')}-${env.pkey}-${S.id}`;
  if (S.focus) {
    const f = page.locator(S.focus);
    if (await f.count()) await f.first().scrollIntoViewIfNeeded().catch(() => {});
  }
  if (!NOSHOTS) {
    await page.screenshot({ path: path.join(OUT, `${base}-desktop.png`) }).then(() => rec.shots.push(`${base}-desktop.png`)).catch(() => {});
  }
  for (const w of [590, 390]) {
    await page.setViewportSize({ width: w, height: 1000 });
    await page.waitForTimeout(700);
    if (w === 590 && !NOSHOTS) {
      await page.screenshot({ path: path.join(OUT, `${base}-mobile.png`) }).then(() => rec.shots.push(`${base}-mobile.png`)).catch(() => {});
    }
    const m = await page.evaluate(measureOverflow).catch(() => null);
    if (!m) continue;
    rec.overflow[w] = m;
    if (m.doc > 1) env.fail(`horizontal scroll at ${w}px (page ${m.sw}px wide in a ${m.cw}px viewport)`);
    else if (m.offenders.length) env.soft(`scroll container wider than the viewport at ${w}px: ${m.offenders.join(', ')}`);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
}

// ---------------------------------------------------------------- one step
async function runStep(context, e, me) {
  const t0 = Date.now();
  const missing = new Set();
  const S = resolveDeep(e.step, me, missing);
  const env = makeEnv(context, e, me, S);
  const rec = env.rec;

  if (missing.size) {
    rec.status = 'SKIP';
    rec.skipReason = `context value missing: ${[...missing].join(', ')} - run seed_journey_content.php`;
    return rec;
  }
  if (/^(enrolFree|cart)/.test(S.handler || '') && !CTX.courses) {
    rec.status = 'SKIP';
    rec.skipReason = 'seeded course context missing - run seed_journey_content.php';
    return rec;
  }
  if (S.flag) {
    const state = (CTX.flags && CTX.flags[S.flag]) ? CTX.flags[S.flag][String(e.persona.tenant)] : undefined;
    if (state === false) {
      rec.status = 'SKIP';
      rec.skipReason = `flag off: ${S.flag} (tenant /${e.persona.tenant}) - feature not enabled locally`;
      return rec;
    }
  }

  const usePage = S.page !== false;
  try {
    if (usePage) {
      const page = await context.newPage();
      env.page = page;
      page.setDefaultTimeout(240000);
      page.on('dialog', d => d.dismiss().catch(() => {}));
      page.on('console', m => {
        if (m.type() !== 'error') return;
        const t = m.text().slice(0, 220);
        if (!IGNORE_CONSOLE.some(r => r.test(t))) rec.consoleErrors.push(t);
      });
      page.on('pageerror', err => {
        if (!IGNORE_CONSOLE.some(r => r.test(err.message))) rec.consoleErrors.push(('pageerror: ' + err.message).slice(0, 220));
      });
      page.on('response', r => {
        try {
          if (r.request().isNavigationRequest() && r.frame() === page.mainFrame()) rec.http = r.status();
          else if (r.status() >= 400 && rec.badResources.length < 5) rec.badResources.push(`${r.status()} ${new URL(r.url()).pathname}`);
        } catch (err) { /* response without a frame (worker) */ }
      });
    }
    if (S.handler) {
      const h = HANDLERS[S.handler];
      if (!h) throw new Error(`unknown handler ${S.handler}`);
      await h(env);
    } else if (S.url && usePage) {
      await env.nav(S.url);
    }
    if (usePage) {
      await settle(env);
      await checkPage(env);
      await capture(env);
    }
  } catch (err) {
    env.fail('error: ' + String(err.message).split('\n')[0].slice(0, 300));
    if (env.page && !/^about:blank/.test(env.page.url())) await capture(env).catch(() => {});
  } finally {
    if (env.page) await env.page.close().catch(() => {});
  }

  rec.blocked = [...env.blocked].slice(0, 6);
  rec.consoleErrors = rec.consoleErrors.slice(0, 6);
  if (rec.consoleErrors.length) {
    const bad = rec.badResources.length ? ` [${rec.badResources.join(', ')}]` : '';
    if (S.landing) env.fail(`${rec.consoleErrors.length} JS console error(s) on a landing page: ${rec.consoleErrors[0]}${bad}`);
    else env.soft(`${rec.consoleErrors.length} JS console error(s): ${rec.consoleErrors[0].slice(0, 120)}${bad}`);
  }
  rec.status = rec.fails.length ? 'FAIL' : rec.softs.length ? 'CHECK' : 'PASS';
  rec.ms = Date.now() - t0;
  return rec;
}

// ---------------------------------------------------------------- login
async function login(context, username) {
  const page = await context.newPage();
  page.setDefaultTimeout(240000);
  try {
    // One retry from a fresh login page when Moodle answers "Your session has
    // timed out" (a login token that no longer matches its session - seen once
    // on this slow box, not reproducible).
    for (let attempt = 1; attempt <= 2; attempt++) {
      for (let g = 1; g <= 2; g++) {
        try {
          await page.goto(BASE + '/login/index.php', { waitUntil: 'domcontentloaded', timeout: 240000 });
          break;
        } catch (err) {
          if (g === 2) throw err;
          if (CONN_ERR.test(String(err.message))) await waitForServer();
          else await page.waitForTimeout(10000);
        }
      }
      await page.fill('#username', username);
      await page.fill('#password', creds[username].password);
      // The login POST can take minutes on a cold cache: wait for the redirect
      // away from the login page, or for the login page to come back with an error.
      await page.click('#loginbtn', { noWaitAfter: true });
      const outcome = await Promise.race([
        page.waitForURL(u => !/\/login\/index\.php/.test(String(u)), { timeout: 240000 }).then(() => 'ok'),
        page.waitForFunction(() => location.pathname.includes('/login/')
          && /session has timed out|invalid login|account has been locked|too many/i.test(document.body ? document.body.innerText : ''),
        null, { timeout: 240000, polling: 1000 }).then(() => 'error'),
      ]);
      if (outcome === 'ok') {
        const p = new URL(page.url()).pathname;
        if (/change_password|\/admin\/tool\/policy|\/user\/policy/.test(p)) {
          return { ok: false, reason: `interstitial after login: ${p}` };
        }
        return { ok: true };
      }
      const why = (await page.locator('.alert, .loginerrors, #loginerrormessage').allInnerTexts().catch(() => [])).join(' | ');
      if (attempt === 1 && /session has timed out/i.test(why)) continue;
      return { ok: false, reason: `login refused: ${why.slice(0, 200)}` };
    }
    return { ok: false, reason: 'login did not complete' };
  } catch (err) {
    const why = await page.locator('.alert, .loginerrors, #loginerrormessage').allInnerTexts().catch(() => []);
    return { ok: false, reason: `login did not complete: ${String(err.message).split('\n')[0].slice(0, 120)} ${why.join(' | ').slice(0, 160)}` };
  } finally {
    await page.close().catch(() => {});
  }
}

// ---------------------------------------------------------------- main
let plan = FULL.filter(e => !FILTER_PERSONA.length || FILTER_PERSONA.includes(e.pkey));
if (FILTER_PERSONA.length) {
  const unknown = FILTER_PERSONA.filter(k => !J.personas[k]);
  if (unknown.length) {
    console.error(`unknown persona(s): ${unknown.join(', ')}. Known: ${Object.keys(J.personas).join(', ')}`);
    process.exit(2);
  }
}
if (ONLY_STEPS.length) plan = plan.filter(e => ONLY_STEPS.includes(e.step.id));
if (RETRY_FAILED) {
  plan = plan.filter(e => {
    const r = results.find(x => x.persona === e.pkey && x.step === e.step.id);
    return !r || r.status === 'FAIL';
  });
}
if (FROM) {
  const [fp, fs_] = FROM.includes(':') ? FROM.split(':') : ['', FROM];
  const at = plan.findIndex(e => e.step.id === fs_ && (!fp || e.pkey === fp.replace(/^vp_/, '')));
  if (at < 0) {
    console.error(`--from ${FROM}: no such step in the selected plan`);
    process.exit(2);
  }
  plan = plan.slice(at);
}
if (LIMIT) {
  const seen = {};
  plan = plan.filter(e => { seen[e.pkey] = (seen[e.pkey] || 0) + 1; return seen[e.pkey] <= LIMIT; });
}
if (!plan.length) {
  console.log('nothing to run for these filters');
  process.exit(0);
}

const groups = [];
for (const e of plan) {
  const g = groups[groups.length - 1];
  if (g && g.pkey === e.pkey) g.entries.push(e);
  else groups.push({ pkey: e.pkey, entries: [e] });
}
console.log(`Running ${plan.length} step(s) across ${groups.length} persona(s) on ${BASE}; output ${OUT}`);

const browser = await chromium.launch({
  channel: 'chrome', headless: true,
  args: ['--disable-gpu', '--disable-extensions', '--disable-background-networking', '--disable-dev-shm-usage'],
});
let exitCode = 0;
try {
  for (const g of groups) {
    const p = personaMeta[g.pkey];
    // serviceWorkers 'block': the PWA worker loses the session on post-login navigations in headless Chrome.
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
    // Local-only guarantee: every non-local request except the font hosts is aborted (its host is recorded on the step).
    let blockedHosts = new Set();
    await context.route(u => /^https?:$/.test(u.protocol) && !LOCAL.test(u.href) && !ALLOWED_EXTERNAL.test(u.href), route => {
      try { blockedHosts.add(new URL(route.request().url()).host); } catch (err) { /* ignore */ }
      route.abort('blockedbyclient').catch(() => {});
    });
    let me = '';
    if (p.username) {
      me = creds[p.username] ? creds[p.username].id : '';
      const t0 = Date.now();
      const lg = creds[p.username] ? await login(context, p.username) : { ok: false, reason: `no credentials for ${p.username} - run provision_local_personas.php` };
      const rec = {
        persona: g.pkey, username: p.username, step: 'login', name: 'Log in', index: 0, url: '/login/index.php',
        status: lg.ok ? 'PASS' : 'FAIL', fails: lg.ok ? [] : [lg.reason], softs: [], notes: [], consoleErrors: [], shots: [],
        overflow: {}, ms: Date.now() - t0, at: new Date().toISOString(),
      };
      upsert(rec);
      writeResults();
      console.log(`${g.pkey}/login: ${rec.status}${lg.ok ? '' : ' - ' + lg.reason}`);
      if (!lg.ok) {
        exitCode = 1;
        for (const e of g.entries) {
          upsert({ persona: g.pkey, username: p.username, step: e.step.id, name: e.step.name, index: e.index, url: e.step.url || '',
            status: 'SKIP', skipReason: 'login failed', fails: [], softs: [], notes: [], consoleErrors: [], shots: [], overflow: {}, at: new Date().toISOString() });
        }
        writeResults();
        await context.close();
        continue;
      }
    }
    for (const e of g.entries) {
      blockedHosts = new Set();
      let rec = await runStep(context, e, me);
      if (rec.status === 'FAIL' && rec.fails.some(f => CONN_ERR.test(f) || /Target (page|closed)|browser has been closed/i.test(f))) {
        console.log(`${g.pkey}/${e.step.id}: connection failure - waiting for port 8080 and re-running the step once`);
        await waitForServer();
        rec = await runStep(context, e, me);
        rec.notes.push('re-ran once after a server connection drop');
      }
      if (!rec.blocked || !rec.blocked.length) rec.blocked = [...blockedHosts].slice(0, 6);
      upsert(rec);
      writeResults();
      if (rec.status === 'FAIL') exitCode = 1;
      const why = rec.status === 'FAIL' ? rec.fails[0] : rec.status === 'CHECK' ? rec.softs[0] : rec.status === 'SKIP' ? rec.skipReason : (rec.notes[0] || '');
      console.log(`${String(e.index).padStart(PAD, '0')} ${g.pkey}/${e.step.id}: ${rec.status}${why ? ' - ' + String(why).slice(0, 150) : ''} [${Math.round((rec.ms || 0) / 1000)}s]`);
    }
    await context.close();
  }
} finally {
  await browser.close().catch(() => {});
  writeResults();
}
const tally = results.reduce((a, r) => { a[r.status] = (a[r.status] || 0) + 1; return a; }, {});
console.log('TALLY (all stored results)', JSON.stringify(tally));
process.exit(exitCode);
