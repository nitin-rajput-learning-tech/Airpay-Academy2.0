// Goal A walkthrough — Learner persona (fatma.khamis@airpay.tz).
//
// Mechanically walks the core Learner surfaces, capturing desktop +
// mobile-590px screenshots into the visual-audit folder. Designed to
// be the first persona-walk so the methodology can be validated;
// remaining personas reuse this same script with different login
// credentials + a different surface list.
//
// Run from project root (or from audit-runner/):
//   node audit-runner/walk-learner.mjs

import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const BASE = 'http://localhost:8080/moodle';
const USERNAME = 'fatma.khamis@airpay.tz';
const PASSWORD = 'AcademyAudit2026!';

const OUT_ROOT = resolve(
  process.cwd(),
  process.cwd().endsWith('audit-runner') ? '..' : '.',
  'moodle-enhancement/docs/visual-audit-2026-05-22/01-learner');

mkdirSync(resolve(OUT_ROOT, 'desktop'), { recursive: true });
mkdirSync(resolve(OUT_ROOT, 'mobile-590px'), { recursive: true });

// Surfaces to capture for the Learner persona (Section 10.1 of the
// May-12 master doc). Each entry: [filename-prefix, path, label].
// Login + dashboard come first so the screenshot sequence shows the
// actual journey rather than alphabetical order.
const SURFACES = [
  ['01-login',           '/login/index.php',                          'Login page'],
  ['02-dashboard',       '/my/dashboard.php',                         'Dashboard (post-login)'],
  ['03-my-courses',      '/my/courses.php',                           'My Courses'],
  ['04-catalogue',       '/local/airpay_catalog/index.php',           'Course catalogue'],
  ['05-profile',         '/user/profile.php',                         'My profile'],
  ['06-edit-profile',    '/user/edit.php',                            'Edit profile'],
  ['07-preferences',     '/user/preferences.php',                     'Preferences'],
  ['08-grades',          '/grade/report/overview/index.php',          'Grades overview'],
  ['09-calendar',        '/calendar/view.php?view=month',             'Calendar'],
  ['10-messaging',       '/message/index.php',                        'Messages'],
  ['11-private-files',   '/user/files.php',                           'Private files'],
  ['12-badges',          '/badges/mybadges.php',                      'My badges'],
  ['13-skill-radar',     '/local/airpay_skills/myradar.php',          'Skill radar'],
  ['14-my-requests',     '/local/airpay_request/index.php',           'My requests'],
  ['15-notifications',   '/message/notificationpreferences.php',      'Notification preferences'],
];

async function captureAtBreakpoint(context, breakpoint, viewport) {
  const page = await context.newPage();
  await page.setViewportSize(viewport);

  // Login once per context.
  await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded' });
  await page.screenshot({
    path: resolve(OUT_ROOT, breakpoint, '01-login.png'),
    fullPage: false,
  });

  await page.fill('#username', USERNAME);
  await page.fill('#password', PASSWORD);
  // Some login forms submit via AJAX and don't trigger a real navigation;
  // others do. Click + wait for *either* navigation OR the dashboard URL
  // to appear in window.location. Don't fail the whole walk if either
  // path times out — we'll still capture what's on screen.
  await Promise.race([
    Promise.all([
      page.waitForURL(/dashboard|my|index/i, { timeout: 20000 }).catch(() => null),
      page.click('#loginbtn'),
    ]),
    page.click('#loginbtn').then(() =>
      page.waitForTimeout(8000)),
  ]).catch(e => console.warn(`Login click warning: ${e.message.split('\n')[0]}`));
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  // Confirm we are logged in — if not, scream loud.
  const url = page.url();
  if (url.includes('login/index.php')) {
    console.error(`Still on login page after submit: ${url}`);
    await page.screenshot({
      path: resolve(OUT_ROOT, breakpoint, '01b-login-failed.png'),
      fullPage: true,
    });
  }

  // Now walk the surfaces, skipping the login page we already shot.
  for (const [prefix, path, label] of SURFACES) {
    if (prefix === '01-login') continue;
    const url = `${BASE}${path}`;
    try {
      console.log(`[${breakpoint}] ${prefix} -> ${label}`);
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
      // Hide cookie banners + install prompts that interrupt screenshots.
      await page.evaluate(() => {
        document.querySelectorAll('[id*="cookie"], [class*="cookie"], .sentientia-install-cta')
          .forEach(el => el.style.display = 'none');
      });
      await page.screenshot({
        path: resolve(OUT_ROOT, breakpoint, `${prefix}.png`),
        fullPage: true,
      });
    } catch (e) {
      console.warn(`[${breakpoint}] ${prefix} FAILED: ${e.message.split('\n')[0]}`);
    }
  }
  await page.close();
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 900 },
});

try {
  console.log('=== Desktop pass (1440x900) ===');
  await captureAtBreakpoint(context, 'desktop', { width: 1440, height: 900 });
  console.log('=== Mobile pass (390x844, iPhone 14 frame) ===');
  await captureAtBreakpoint(context, 'mobile-590px', { width: 390, height: 844 });
  console.log('Walk complete.');
} finally {
  await browser.close();
}
