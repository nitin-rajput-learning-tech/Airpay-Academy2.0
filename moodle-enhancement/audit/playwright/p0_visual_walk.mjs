// P0.1 — Visual UX walkthrough
//
// Loads each admin page in 3 viewports (desktop, tablet, mobile) AND in
// 2 themes (light, dark) and screenshots each combination. Captures
// JavaScript console errors as a side-effect (covers P0.3 too).
//
// Output:
//   /tmp/airpay_p0/screenshots/<page>__<viewport>__<theme>.png
//   /tmp/airpay_p0/visual_report.json — per-page console errors + perf timing
//
// Usage:
//   node moodle-enhancement/audit/playwright/p0_visual_walk.mjs

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const USERNAME  = 'academy@airpay.co.in';
const PASSWORD  = 'Airpay@Test2026!';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');

const PAGES = [
    { id: 'dashboard',     path: '/my/dashboard.php',                          marker: 'airpay-dash' },
    { id: 'users',         path: '/local/airpay_users/index.php',              marker: 'airpay-users' },
    { id: 'courses',       path: '/local/airpay_courses/index.php',            marker: 'airpay-courses' },
    { id: 'classroom',     path: '/local/airpay_classroom/index.php',          marker: 'airpay-classroom' },
    { id: 'exams',         path: '/local/airpay_exams/index.php',              marker: 'airpay-exams' },
    { id: 'paths',         path: '/local/airpay_learningpath/index.php',       marker: 'airpay-paths' },
    { id: 'programs',      path: '/local/airpay_programs/index.php',           marker: 'airpay-programs' },
    { id: 'reports',       path: '/local/airpay_reports/index.php',            marker: 'airpay-reports' },
    { id: 'skills',        path: '/local/airpay_skills/admin.php',             marker: 'airpay-skills' },
    { id: 'notifications', path: '/local/airpay_notifications/index.php',      marker: 'airpay-notifications' },
    { id: 'evaluations',   path: '/local/airpay_evaluation/index.php',         marker: 'airpay-evaluation' },
    { id: 'org',           path: '/local/airpay_org/admin.php',                marker: 'airpay-org' },
    { id: 'analytics',     path: '/local/airpay_analytics/index.php',          marker: null },
    { id: 'manager',       path: '/local/airpay_manager/index.php',            marker: null },
    { id: 'member',        path: '/local/airpay_manager/member.php?id=142',    marker: null },
];

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet',  width: 768,  height: 1024 },
    { name: 'mobile',  width: 590,  height: 900 },   // primary mobile per CLAUDE.md
];

const THEMES = ['light', 'dark'];

async function login(page) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"], #loginbtn, input[type="submit"]');
    await page.waitForURL(/\/my\//, { timeout: 90_000 });
}

async function setTheme(page, theme) {
    // The theme is toggled by adding 'dark-mode' class to body. localStorage
    // 'airpay-theme' = 'dark' triggers head.mustache to inject the class.
    if (theme === 'dark') {
        await page.evaluate(() => {
            localStorage.setItem('airpay-theme', 'dark');
            document.documentElement.classList.add('dark-mode');
            document.body.classList.add('dark-mode');
        });
    } else {
        await page.evaluate(() => {
            localStorage.setItem('airpay-theme', 'light');
            document.documentElement.classList.remove('dark-mode');
            document.body.classList.remove('dark-mode');
        });
    }
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { runs: [], errors: [], summary: {} };

    const browser = await chromium.launch({
        headless: true,
        channel: 'chrome', // use system-installed Google Chrome
        args: [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--incognito',                    // user requested incognito
            '--disable-extensions',
            '--disable-plugins',
        ],
    });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });

    // 1. Login once and reuse the session.
    const loginPage = await context.newPage();
    await login(loginPage);
    await loginPage.close();

    // 2. Walk every page × viewport × theme.
    let idx = 0;
    for (const vp of VIEWPORTS) {
        for (const theme of THEMES) {
            const page = await context.newPage();
            await page.setViewportSize({ width: vp.width, height: vp.height });

            // Capture console + page errors per visit.
            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });
            page.on('pageerror', err => {
                consoleErrors.push(`PAGEERROR: ${err.message}`);
            });

            for (const p of PAGES) {
                idx++;
                const start = Date.now();
                let status = 'PASS', notes = [];
                consoleErrors.length = 0;

                try {
                    await page.goto(`${BASE}${p.path}`, {
                        waitUntil: 'domcontentloaded',
                        timeout: 60_000,
                    });

                    // Apply theme after page loads — it persists via localStorage.
                    await setTheme(page, theme);

                    // Force a small wait so dark-mode class propagates to all components.
                    await page.waitForTimeout(400);

                    // Marker check.
                    if (p.marker) {
                        const found = await page.locator(`[data-region="${p.marker}"]`).count();
                        if (found === 0) {
                            notes.push(`marker ${p.marker} missing`);
                            status = 'WARN';
                        }
                    }

                    // Was the page a Moodle 404 / no-permission?
                    const isError = await page.locator('.errorbox, .errormessage').count();
                    if (isError > 0) {
                        notes.push('errorbox present');
                        status = 'FAIL';
                    }

                    // Screenshot.
                    const shotName = `${p.id}__${vp.name}__${theme}.png`;
                    await page.screenshot({
                        path: path.join(SHOTS_DIR, shotName),
                        fullPage: false, // viewport only — we want to see what user sees
                    });
                    notes.push(`shot=${shotName}`);

                    // Capture window-scoped JS errors.
                    if (consoleErrors.length) {
                        notes.push(`${consoleErrors.length} console errors`);
                        report.errors.push({
                            page: p.id, vp: vp.name, theme,
                            errors: [...consoleErrors],
                        });
                        if (status === 'PASS') status = 'WARN';
                    }
                } catch (e) {
                    status = 'FAIL';
                    notes.push(`exception: ${e.message}`);
                }

                const dur = Date.now() - start;
                console.log(`  [${idx.toString().padStart(3)}] ${status} ${p.id.padEnd(14)} ${vp.name.padEnd(7)} ${theme.padEnd(5)} ${dur}ms — ${notes.join(', ')}`);
                report.runs.push({ idx, page: p.id, vp: vp.name, theme, status, dur, notes });
            }

            await page.close();
        }
    }

    // 3. Summarise.
    const counts = report.runs.reduce((acc, r) => {
        acc[r.status] = (acc[r.status] || 0) + 1;
        return acc;
    }, {});
    report.summary = {
        total: report.runs.length,
        ...counts,
        errors_total: report.errors.reduce((n, e) => n + e.errors.length, 0),
    };

    await fs.writeFile(
        path.join(OUT_DIR, 'visual_report.json'),
        JSON.stringify(report, null, 2)
    );

    console.log();
    console.log('═══════════════════════════════════════════════════════════════════');
    console.log(`SUMMARY: ${report.summary.total} runs, ` +
                `PASS=${report.summary.PASS || 0}, ` +
                `WARN=${report.summary.WARN || 0}, ` +
                `FAIL=${report.summary.FAIL || 0}, ` +
                `console errors=${report.summary.errors_total}`);
    console.log(`Screenshots: ${SHOTS_DIR}`);
    console.log(`JSON report: ${OUT_DIR}/visual_report.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await context.close();
    await browser.close();

    process.exit((counts.FAIL || 0) > 0 ? 1 : 0);
}

main().catch(e => {
    console.error('FATAL:', e);
    process.exit(2);
});
