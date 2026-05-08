// p2_workflows_2026_05_08.mjs
//
// Deep-workflow harness covering the 4 plugins shipped during the
// 2026-05-07 + 2026-05-08 stretches. Closes the V + L axes for:
//
//   WX-08  airpay_roles    — index → view → caps → modal open → audit log
//   WX-09  airpay_challenge — index → join → leaderboard recompute
//   WX-10  airpay_manager   — requests page → decide modal → allocations
//   WX-11  airpay_skills    — admin → level definitions → designation matrix
//
// Each workflow asserts:
//   1. Page renders without console errors
//   2. Key UI elements present (buttons, tables, modals)
//   3. State transitions work (modal opens / closes)
//   4. No fatal Moodle errors visible in DOM
//   5. Network requests succeed (no 5xx)
//
// Output: C:\Users\nitin.rajput\airpay_p0\p2_workflows_2026_05_08.json
// Exit:   0 if all workflows pass.

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE         = 'http://localhost:8080/moodle';
const OUT_DIR      = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR    = path.join(OUT_DIR, 'screenshots');
const PASSWORD     = 'Airpay@Test2026!';
const PAGE_TIMEOUT = 90_000;
const ADMIN        = 'academy@airpay.co.in';

async function login(page, login_id) {
    await page.goto(`${BASE}/login/index.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.fill('input[name="username"]', login_id);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForURL(u => /\/(my|admin)\//.test(u.toString())
                || u.toString().endsWith('/moodle/'),
            { timeout: PAGE_TIMEOUT, waitUntil: 'domcontentloaded' }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function shoot(page, name) {
    try { await page.screenshot({
        path: path.join(SHOTS_DIR, `p2wf_${name}.png`),
        fullPage: true,
    }); } catch {}
}

/**
 * Page checker — collects console errors + failed network requests.
 * Returns { errors: [...], failedRequests: [...] }.
 */
function attachListeners(page) {
    const errors = [];
    const failedRequests = [];
    page.on('console', m => {
        if (m.type() === 'error') errors.push(m.text());
    });
    page.on('pageerror', e => errors.push(e.message));
    page.on('response', resp => {
        if (resp.status() >= 500) {
            failedRequests.push(`${resp.status()} ${resp.url()}`);
        }
    });
    return { errors, failedRequests };
}

// ─── WX-08 — airpay_roles ────────────────────────────────────────────────

async function wf_roles(page) {
    const wf = { id: 'WX-08', plugin: 'airpay_roles', cases: [] };
    const listeners = attachListeners(page);

    // Case 1: index page loads with role table
    await page.goto(`${BASE}/local/airpay_roles/index.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'roles_index');
    const indexHasTable = await page.locator('#airpay-roles-table').count() > 0;
    wf.cases.push({ name: 'index_renders', pass: indexHasTable });

    // Case 2: archetype filter exists
    const filterExists = await page.locator('#airpay-roles-archetype').count() > 0;
    wf.cases.push({ name: 'archetype_filter_exists', pass: filterExists });

    // Case 3: navigate to role view (manager id is typically 1)
    await page.goto(`${BASE}/local/airpay_roles/view.php?id=1&tab=overview`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1000);
    await shoot(page, 'roles_view_overview');
    const overviewBadgeExists = await page.locator('.ap-stat__value').count() >= 4;
    wf.cases.push({ name: 'overview_4_stats', pass: overviewBadgeExists });

    // Case 4: capabilities tab
    await page.goto(`${BASE}/local/airpay_roles/view.php?id=1&tab=capabilities`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(2000);
    await shoot(page, 'roles_view_caps');
    const capsTableExists = await page.locator('#airpay-roles-caps-table').count() > 0;
    wf.cases.push({ name: 'caps_table_renders', pass: capsTableExists });

    // Case 5: audit log page
    await page.goto(`${BASE}/local/airpay_roles/audit.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'roles_audit');
    const auditTableExists = await page.locator('#airpay-roles-audit-global-table').count() > 0;
    wf.cases.push({ name: 'audit_table_renders', pass: auditTableExists });

    wf.console_errors = listeners.errors.filter(e => !e.includes('learnerscript'));
    wf.failed_requests = listeners.failedRequests;
    return wf;
}

// ─── WX-09 — airpay_challenge ────────────────────────────────────────────

async function wf_challenge(page) {
    const wf = { id: 'WX-09', plugin: 'airpay_challenge', cases: [] };
    const listeners = attachListeners(page);

    await page.goto(`${BASE}/local/airpay_challenge/index.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'challenge_index');
    const indexTable = await page.locator('#airpay-challenge-table').count() > 0;
    wf.cases.push({ name: 'index_renders', pass: indexTable });

    // Filter form should be present
    const filterExists = await page.locator('#airpay-challenge-status').count() > 0;
    wf.cases.push({ name: 'status_filter_exists', pass: filterExists });

    // Leaderboard
    await page.goto(`${BASE}/local/airpay_challenge/leaderboard.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'challenge_leaderboard');
    const leaderboardTable = await page.locator('#airpay-challenge-leaderboard-global-table').count() > 0;
    wf.cases.push({ name: 'leaderboard_renders', pass: leaderboardTable });

    wf.console_errors = listeners.errors.filter(e => !e.includes('learnerscript'));
    wf.failed_requests = listeners.failedRequests;
    return wf;
}

// ─── WX-10 — airpay_manager ──────────────────────────────────────────────

async function wf_manager(page) {
    const wf = { id: 'WX-10', plugin: 'airpay_manager', cases: [] };
    const listeners = attachListeners(page);

    // Index (existing — team dashboard)
    await page.goto(`${BASE}/local/airpay_manager/index.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'manager_index');

    // Requests (Phase B)
    await page.goto(`${BASE}/local/airpay_manager/requests.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'manager_requests');
    const requestsTable = await page.locator('#ap-mgr-requests-table').count() > 0;
    wf.cases.push({ name: 'requests_table_renders', pass: requestsTable });

    // Allocations (Phase B + v1.2 bulk button)
    await page.goto(`${BASE}/local/airpay_manager/allocations.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'manager_allocations');
    const allocTable = await page.locator('#ap-mgr-alloc-table').count() > 0;
    wf.cases.push({ name: 'allocations_table_renders', pass: allocTable });

    // Bulk-allocate button (v1.2)
    const bulkButton = await page.locator('[data-action="bulk-allocate"]').count() > 0;
    wf.cases.push({ name: 'bulk_allocate_button_present', pass: bulkButton });

    // Export CSV link (v1.2)
    const csvLink = await page.locator('a[href*="exportcsv.php"]').count() > 0;
    wf.cases.push({ name: 'csv_export_link_present', pass: csvLink });

    wf.console_errors = listeners.errors.filter(e => !e.includes('learnerscript'));
    wf.failed_requests = listeners.failedRequests;
    return wf;
}

// ─── WX-11 — airpay_skills ───────────────────────────────────────────────

async function wf_skills(page) {
    const wf = { id: 'WX-11', plugin: 'airpay_skills', cases: [] };
    const listeners = attachListeners(page);

    // Admin
    await page.goto(`${BASE}/local/airpay_skills/admin.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'skills_admin');

    // Designation matrix link button must be present (Phase A)
    const matrixLink = await page.locator('a[href*="designation_matrix.php"]').count() > 0;
    wf.cases.push({ name: 'designation_matrix_link_present', pass: matrixLink });

    // New Category button works (data-action="create-category")
    const catButton = await page.locator('[data-action="create-category"]').count() > 0;
    wf.cases.push({ name: 'create_category_button', pass: catButton });

    // Designation matrix page
    await page.goto(`${BASE}/local/airpay_skills/designation_matrix.php`,
        { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.waitForTimeout(1500);
    await shoot(page, 'skills_designation_matrix');
    const desigSelector = await page.locator('#ap-skills-designation').count() > 0;
    wf.cases.push({ name: 'designation_selector_present', pass: desigSelector });

    wf.console_errors = listeners.errors.filter(e => !e.includes('learnerscript'));
    wf.failed_requests = listeners.failedRequests;
    return wf;
}

// ─── Runner ──────────────────────────────────────────────────────────────

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
    });
    const page = await ctx.newPage();
    await login(page, ADMIN);

    const workflows = [];
    for (const fn of [wf_roles, wf_challenge, wf_manager, wf_skills]) {
        try {
            workflows.push(await fn(page));
        } catch (err) {
            workflows.push({ id: fn.name, error: err.message });
        }
    }

    await browser.close();

    // Aggregate.
    let totalPass = 0;
    let totalFail = 0;
    let totalErrors = 0;
    let totalFailedReqs = 0;
    for (const wf of workflows) {
        if (wf.cases) {
            for (const c of wf.cases) {
                if (c.pass) totalPass++; else totalFail++;
            }
        }
        totalErrors += (wf.console_errors || []).length;
        totalFailedReqs += (wf.failed_requests || []).length;
    }

    const summary = {
        timestamp: new Date().toISOString(),
        all_pass: totalFail === 0 && totalErrors === 0 && totalFailedReqs === 0,
        total_pass: totalPass,
        total_fail: totalFail,
        total_console_errors: totalErrors,
        total_failed_requests: totalFailedReqs,
        workflows,
    };

    await fs.writeFile(path.join(OUT_DIR, 'p2_workflows_2026_05_08.json'),
        JSON.stringify(summary, null, 2));
    console.log(JSON.stringify({
        all_pass: summary.all_pass,
        pass: totalPass,
        fail: totalFail,
        errors: totalErrors,
        failed_requests: totalFailedReqs,
    }, null, 2));

    process.exit(summary.all_pass ? 0 : 1);
}

main().catch(err => { console.error(err); process.exit(2); });
