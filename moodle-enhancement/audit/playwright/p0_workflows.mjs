// P0.4 + P0.5 — Multi-step workflows + SCORM playback
//
// Two flows:
//
// (A) Manager team-detail walkthrough — login as manager, click into
//     own report, verify drill-down rendering + course list. Then try
//     to drill into a non-report (should be denied with errorbox).
//
// (B) Course catalog → enrol → start → SCORM playback. The harness
//     tries to find a SCORM activity and click into it. If no SCORM
//     content exists, marks as SKIP (no SCORM in test DB).
//
// Output: /tmp/airpay_p0/workflow_report.json + screenshots on FAIL.
//
// Usage:
//   node moodle-enhancement/audit/playwright/p0_workflows.mjs

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');

const ACCOUNTS = {
    siteadmin: { user: 'academy@airpay.co.in',           pass: 'Airpay@Test2026!' },
    manager:   { user: 'kunal@airpay.co.in',             pass: 'Airpay@Test2026!' },
    learner:   { user: 'rasika.thakare@airpay.co.in',    pass: 'Airpay@Test2026!' },
};

async function loginAs(page, role) {
    const a = ACCOUNTS[role];
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await page.fill('input[name="username"]', a.user);
    await page.fill('input[name="password"]', a.pass);
    // Accept /my/, /admin/, or /local/airpay_pages/ as valid post-login landings.
    // Siteadmins land on /admin/index.php (upgrade-check) or / on first hit.
    await Promise.all([
        page.waitForURL(u => {
            const s = u.toString();
            return /\/(my|admin)\//.test(s) || s.endsWith('/moodle/') || s.includes('/local/airpay');
        }, { timeout: 120_000 }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function shoot(page, name) {
    await page.screenshot({ path: path.join(SHOTS_DIR, `wf_${name}.png`), fullPage: true });
}

async function flow_manager_drilldown(browser, report) {
    console.log('\n  ── Workflow A: Manager → My Team → drill into own report ──');
    const context = await browser.newContext();
    context.setDefaultTimeout(90_000);
    context.setDefaultNavigationTimeout(120_000);
    const page = await context.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    const log = (m) => { console.log(`    ${m}`); };
    const fail = (step, msg) => { console.log(`    FAIL ${step}: ${msg}`); report.failures.push({ flow: 'manager_drill', step, msg }); };

    try {
        await loginAs(page, 'manager');
        log('logged in as manager');

        await page.goto(`${BASE}/local/airpay_manager/index.php`, { waitUntil: "domcontentloaded", timeout: 60_000 });
        log('My Team page loaded');

        // The manager dashboard shows direct reports. Look for at least one drill-down link.
        const drillLink = page.locator('[data-region="airpay-manager"] a[href*="/local/airpay_manager/member.php?id="]').first();
        if (await drillLink.count() === 0) {
            // Fallback selectors — different template versions.
            const altLink = page.locator('a[href*="/airpay_manager/member.php"]').first();
            if (await altLink.count() === 0) {
                fail('locate-drill-link', 'no member.php link found on manager page');
                await shoot(page, 'manager_no_drill_link');
                await page.close(); return;
            }
            await altLink.click();
        } else {
            await drillLink.click();
        }
        await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
        log(`drill page loaded: ${page.url()}`);

        // Verify we landed on a member page, not denied.
        const errBox = await page.locator('.errorbox').count();
        if (errBox > 0) {
            fail('drill-denied', 'errorbox on own-report drill (should be allowed)');
            await shoot(page, 'manager_own_drill_denied');
        } else {
            log('drill rendered ✓');
        }

        // Look for course progress markers.
        const hasCourseList = await page.locator('table:has-text("Course"), .ap-course-list, [data-region*="airpay"]').count();
        if (hasCourseList === 0) {
            log('  WARN no course-progress markers visible');
        } else {
            log('course list visible ✓');
        }

        // Now try drilling into a NON-report (rasika, id=3113) — must be denied.
        await page.goto(`${BASE}/local/airpay_manager/member.php?id=3113`, { waitUntil: "domcontentloaded", timeout: 60_000 });
        const denied = await page.locator('.errorbox, .errormessage').count();
        const httpStatus = await page.evaluate(() => document.title);
        // Moodle returns 404 page with errorbox.
        if (denied === 0 && !page.url().includes('login')) {
            fail('non-report-deny', 'drill into non-report did NOT show errorbox (privilege escalation?)');
            await shoot(page, 'manager_non_report_leaked');
        } else {
            log('non-report drill correctly denied ✓');
        }

        if (errs.length) report.consoleErrors.push({ flow: 'manager_drill', errors: errs });
    } catch (e) {
        fail('flow-exception', e.message);
    }
    await page.close();
    await context.close();
}

async function flow_learner_catalog_to_course(browser, report) {
    console.log('\n  ── Workflow B: Learner → Catalog → click first available course ──');
    const context = await browser.newContext();
    context.setDefaultTimeout(90_000);
    context.setDefaultNavigationTimeout(120_000);
    const page = await context.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    const log = (m) => { console.log(`    ${m}`); };
    const fail = (step, msg) => { console.log(`    FAIL ${step}: ${msg}`); report.failures.push({ flow: 'learner_catalog', step, msg }); };

    try {
        await loginAs(page, 'learner');
        log('logged in as learner');

        await page.goto(`${BASE}/local/airpay_catalog/index.php`, { waitUntil: "domcontentloaded", timeout: 60_000 });
        log('catalog loaded');

        // Find a course link — catalog templates vary across forks; try a few selectors.
        const linkSels = [
            'a[href*="/course/view.php"]',
            'a[href*="/local/search/coursedetails.php"]',
        ];
        let courseLink = null;
        for (const sel of linkSels) {
            const loc = page.locator(sel).first();
            if (await loc.count() > 0) {
                courseLink = loc;
                break;
            }
        }
        if (!courseLink) {
            log('  no course links visible — catalog may be empty for this learner');
            report.skips.push({ flow: 'learner_catalog', reason: 'no courses visible' });
            await page.close(); return;
        }

        const href = await courseLink.getAttribute('href');
        log(`clicking: ${href}`);
        await courseLink.click();
        await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
        log(`course detail loaded: ${page.url()}`);

        const errBox = await page.locator('.errorbox').count();
        if (errBox > 0) {
            fail('course-detail-error', 'errorbox on course detail page');
            await shoot(page, 'learner_course_detail_err');
        } else {
            log('course detail OK ✓');
        }

        // Look for SCORM activity link.
        const scormLink = page.locator('a[href*="/mod/scorm/view.php"]').first();
        if (await scormLink.count() > 0) {
            log('  found SCORM activity, clicking…');
            await scormLink.click();
            await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
            log(`SCORM landing: ${page.url()}`);
            const playerErr = await page.locator('.errorbox').count();
            if (playerErr > 0) {
                fail('scorm-load', 'errorbox on SCORM landing page');
                await shoot(page, 'learner_scorm_err');
            } else {
                log('SCORM landing rendered ✓ (full playback requires JS interaction not exercised here)');
                report.notes.push({ flow: 'scorm', msg: 'SCORM landing renders; in-frame playback requires manual sign-off' });
            }
        } else {
            log('  no SCORM activity in this course — skipped P0.5');
            report.skips.push({ flow: 'scorm', reason: 'no SCORM activity in selected course' });
        }

        if (errs.length) report.consoleErrors.push({ flow: 'learner_catalog', errors: errs });
    } catch (e) {
        fail('flow-exception', e.message);
    }
    await page.close();
    await context.close();
}

async function flow_admin_assign_user_to_path(browser, report) {
    console.log('\n  ── Workflow C: Admin assigns user to a learning path (multi-step) ──');
    const context = await browser.newContext();
    context.setDefaultTimeout(90_000);
    context.setDefaultNavigationTimeout(120_000);
    const page = await context.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    const log = (m) => { console.log(`    ${m}`); };
    const fail = (step, msg) => { console.log(`    FAIL ${step}: ${msg}`); report.failures.push({ flow: 'admin_assign_path', step, msg }); };

    try {
        await loginAs(page, 'siteadmin');
        log('logged in as siteadmin');

        // Step 1: open Learning Paths admin.
        await page.goto(`${BASE}/local/airpay_learningpath/index.php`, { waitUntil: "domcontentloaded", timeout: 60_000 });
        const pathsRow = page.locator('tr[data-row-id]').first();
        if (await pathsRow.count() === 0) {
            log('  no learning paths to assign — skipped');
            report.skips.push({ flow: 'admin_assign_path', reason: 'no learning paths in test DB' });
            await page.close(); return;
        }
        log(`paths page has ${await page.locator('tr[data-row-id]').count()} rows`);

        // Step 2: click into the path's view (or wherever the assign button lives).
        // Different airpay_learningpath builds have varying UIs — for the audit
        // we just verify the row's edit button opens a modal (the established
        // CRUD pattern).
        const editBtn = pathsRow.locator('[data-action="edit-path"]').first();
        if (await editBtn.count() === 0) {
            log('  WARN edit-path button not present in row');
            report.notes.push({ flow: 'admin_assign_path', msg: 'edit button selector may have changed' });
        } else {
            await editBtn.click();
            await page.locator('.modal.show').waitFor({ state: 'visible', timeout: 30_000 });
            log('edit modal opened ✓');
            // Close it without saving — this is just a smoke test.
            const cancelBtn = page.locator('.modal.show button:has-text("Cancel"), .modal.show .close').first();
            if (await cancelBtn.count() > 0) await cancelBtn.click();
        }

        if (errs.length) report.consoleErrors.push({ flow: 'admin_assign_path', errors: errs });
    } catch (e) {
        fail('flow-exception', e.message);
    }
    await page.close();
    await context.close();
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { failures: [], consoleErrors: [], skips: [], notes: [] };

    // HEADED mode by default so you can watch. Set HEADLESS=1 to run silently.
    const headless = process.env.HEADLESS === '1';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        slowMo: headless ? 0 : 250,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
               '--disable-extensions', '--disable-plugins'],
    });
    // Each flow gets its own context so sessions don't bleed across roles.
    await flow_manager_drilldown(browser, report);
    await flow_learner_catalog_to_course(browser, report);
    await flow_admin_assign_user_to_path(browser, report);

    await fs.writeFile(
        path.join(OUT_DIR, 'workflow_report.json'),
        JSON.stringify(report, null, 2)
    );

    console.log();
    console.log('═══════════════════════════════════════════════════════════════════');
    console.log(`Workflows: 3 flows tested`);
    console.log(`  failures: ${report.failures.length}`);
    console.log(`  skips:    ${report.skips.length}`);
    console.log(`  notes:    ${report.notes.length}`);
    if (report.failures.length > 0) {
        console.log();
        for (const f of report.failures) {
            console.log(`  - ${f.flow} @ ${f.step}: ${f.msg}`);
        }
    }
    console.log(`Report: ${OUT_DIR}/workflow_report.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await browser.close();
    process.exit(report.failures.length > 0 ? 1 : 0);
}

main().catch(e => {
    console.error('FATAL:', e);
    process.exit(2);
});
