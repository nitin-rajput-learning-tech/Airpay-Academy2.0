// p2_a11y_2026_05_08.mjs
//
// axe-core WCAG 2.1 AA scan extending p1_phase_h_a11y_axe.mjs to cover
// the new admin surfaces shipped during the 2026-05-07 + 2026-05-08
// stretches. Closes the V axis (visual / a11y) for Tier-1 + Tier-3 +
// Phase-2 features.
//
// Usage:
//   cd moodle-enhancement/audit/playwright
//   node p2_a11y_2026_05_08.mjs
//
// Output:
//   C:\Users\nitin.rajput\airpay_p0\p2_a11y_2026_05_08.json
//   C:\Users\nitin.rajput\airpay_p0\screenshots\p2axe_<surface>.png
//
// Exit: 0 if ZERO critical/serious axe violations across all surfaces,
//       non-zero otherwise (so CI can gate on it).

import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE         = 'http://localhost:8080/moodle';
const OUT_DIR      = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR    = path.join(OUT_DIR, 'screenshots');
const PASSWORD     = 'Airpay@Test2026!';
const PAGE_TIMEOUT = 90_000;
const ADMIN        = 'academy@airpay.co.in';

// All admin surfaces shipped during the 2026-05-07 / 2026-05-08 stretch.
// Each entry: {id, url, callers}.
const SURFACES = [
    // airpay_roles (shipped 2026-05-07 + Phase 2 today)
    { id: 'roles-index',           url: '/local/airpay_roles/index.php' },
    { id: 'roles-audit',           url: '/local/airpay_roles/audit.php' },
    { id: 'roles-view-overview',   url: '/local/airpay_roles/view.php?id=1&tab=overview' },
    { id: 'roles-view-caps',       url: '/local/airpay_roles/view.php?id=1&tab=capabilities' },
    { id: 'roles-view-audit',      url: '/local/airpay_roles/view.php?id=1&tab=audit' },

    // airpay_challenge (shipped 2026-05-07 + Phase 2 today)
    { id: 'challenge-index',       url: '/local/airpay_challenge/index.php' },
    { id: 'challenge-leaderboard', url: '/local/airpay_challenge/leaderboard.php' },

    // airpay_manager (shipped 2026-05-08 + v1.2.0 today)
    { id: 'manager-index',         url: '/local/airpay_manager/index.php' },
    { id: 'manager-requests',      url: '/local/airpay_manager/requests.php' },
    { id: 'manager-allocations',   url: '/local/airpay_manager/allocations.php' },

    // airpay_skills Phase A (shipped 2026-05-08)
    { id: 'skills-admin',          url: '/local/airpay_skills/admin.php' },
    { id: 'skills-designation-matrix',
                                   url: '/local/airpay_skills/designation_matrix.php' },
    // airpay_skills Phase A.2 (shipped 2026-05-08 v1.4.0)
    { id: 'skills-course-mapping',
                                   url: '/local/airpay_skills/course_mapping.php' },

    // airpay_notifications (Phase C from 2026-05-08)
    { id: 'notifications-index',   url: '/local/airpay_notifications/index.php' },
    // airpay_notifications Phase C.2 (per-user prefs, 2026-05-08 v1.4.0)
    { id: 'notifications-prefs',   url: '/local/airpay_notifications/prefs.php' },
    // airpay_users Phase E.1 (skills tab on profile, 2026-05-08 v1.4.0)
    { id: 'users-profile-skills',  url: '/local/airpay_users/profile.php?id=2' },
    // airpay_programs Phase F.1 (prereq enforcement, 2026-05-08 v1.3.0)
    { id: 'programs-view',         url: '/local/airpay_programs/view.php?id=2&tab=overview' },
    // airpay_users Phase E.3 (bulk-CSV status change, 2026-05-08 v1.6.0)
    { id: 'users-bulk-csv',        url: '/local/airpay_users/bulk_csv.php' },
    // airpay_evaluation Phase G.1 (import template, 2026-05-08 v1.5.0)
    { id: 'evaluation-import',     url: '/local/airpay_evaluation/import_template.php' },
];

async function login(page, login_id) {
    await page.goto(`${BASE}/login/index.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.fill('input[name="username"]', login_id);
    await page.fill('input[name="password"]', PASSWORD);
    // noWaitAfter avoids Playwright's auto-wait for navigation timeout —
    // we have an explicit waitForFunction that handles it more robustly.
    await page.click('#loginbtn', { noWaitAfter: true });
    // Pass undefined as arg so Playwright applies our options correctly.
    await page.waitForFunction(() => {
        if (window.location.href.includes('/login/index.php')) return false;
        return !!document.querySelector(
            'a[href*="/login/logout.php"], #user-menu-toggle, [data-region="user-menu"], a[data-region="logout-link"]');
    }, undefined, { timeout: PAGE_TIMEOUT });
}

async function shoot(page, name) {
    try { await page.screenshot({
        path: path.join(SHOTS_DIR, `p2axe_${name}.png`),
        fullPage: true,
    }); } catch {}
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });

    // Use the locally-installed Google Chrome instead of chromium-headless-shell.
    // Real Chrome = real font rendering, real scrollbars, real viewport behavior
    // — and avoids the STATUS_DLL_NOT_FOUND issue with the headless-shell binary.
    // Set HARNESS_HEADLESS=1 in env to force headless mode (CI).
    const headless = process.env.HARNESS_HEADLESS === '1';
    const browser = await chromium.launch({
        channel: 'chrome',
        headless,
    });
    const ctx = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
    });
    const page = await ctx.newPage();
    await login(page, ADMIN);

    const results = [];

    for (const surface of SURFACES) {
        console.log(`[axe] ${surface.id}  ${surface.url}`);
        try {
            await page.goto(`${BASE}${surface.url}`,
                { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
            // Wait for datatable render or 2s settle.
            await page.waitForTimeout(1500);
            await shoot(page, surface.id);

            const axe = new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
            const r = await axe.analyze();

            const critical = r.violations.filter(v => v.impact === 'critical');
            const serious  = r.violations.filter(v => v.impact === 'serious');
            const moderate = r.violations.filter(v => v.impact === 'moderate');

            results.push({
                surface: surface.id,
                url: surface.url,
                critical: critical.length,
                serious: serious.length,
                moderate: moderate.length,
                violations: r.violations.map(v => ({
                    id: v.id,
                    impact: v.impact,
                    nodes: v.nodes.length,
                    help: v.help,
                })),
            });
        } catch (err) {
            results.push({
                surface: surface.id,
                url: surface.url,
                error: err.message,
            });
        }
    }

    await browser.close();

    const total_critical = results.reduce((s, r) => s + (r.critical || 0), 0);
    const total_serious  = results.reduce((s, r) => s + (r.serious  || 0), 0);
    const total_moderate = results.reduce((s, r) => s + (r.moderate || 0), 0);
    const errored        = results.filter(r => r.error);

    const summary = {
        timestamp: new Date().toISOString(),
        production_ready: total_critical === 0 && total_serious === 0,
        total_critical,
        total_serious,
        total_moderate,
        errored_count: errored.length,
        surfaces_scanned: results.length,
        surfaces: results,
    };

    await fs.writeFile(path.join(OUT_DIR, 'p2_a11y_2026_05_08.json'),
        JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({
        production_ready: summary.production_ready,
        critical: total_critical,
        serious:  total_serious,
        moderate: total_moderate,
        errored:  errored.length,
        surfaces: results.length,
    }, null, 2));

    process.exit(summary.production_ready && errored.length === 0 ? 0 : 1);
}

main().catch(err => { console.error(err); process.exit(2); });
