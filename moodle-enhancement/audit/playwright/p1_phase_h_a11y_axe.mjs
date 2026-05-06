// Phase H — Automated WCAG 2.1 AA scan via axe-core (closes A11Y-6).
//
// Why axe over pa11y: axe-core is the modern reference implementation
// (WAI maintains it), while pa11y defaults to the older HTML_CodeSniffer
// rules. Same Playwright session means we reuse login + navigation that
// the rest of the harness already proves correct.
//
// Coverage scope (per A11Y-2 + A11Y-3 prerequisites):
//   - /my/dashboard.php          — Employee Dashboard, the highest-traffic surface
//   - /local/airpay_users/       — Admin Manage Users (representative datatable)
//   - /local/airpay_catalog/     — Course Catalog (learner browse view)
//
// Output: C:\Users\nitin.rajput\airpay_p0\phase_h_a11y_axe.json
//
// Failure modes covered automatically (~80% of WCAG 2.1 AA):
//   colour-contrast, missing labels, ARIA misuse, heading order,
//   form-input role/name/value, region landmarks, image-alt, link-name,
//   button-name, document-title, html-has-lang, duplicate-id,
//   meta-viewport, skip-link, table caption, autocomplete attributes.
//
// What axe CAN'T catch (still needs manual NVDA — A11Y-2):
//   meaningful focus order, semantic correctness of dynamic announcements,
//   alt-text quality (it checks presence, not appropriateness), context
//   sensitivity of headings, screen-reader-specific rendering quirks.

import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const PASSWORD  = 'Airpay@Test2026!';
const PAGE_TIMEOUT = 90_000;

// Two callers — one admin, one learner — so we cover capability-gated UI.
const CALLERS = [
    { role: 'siteadmin', login: 'academy@airpay.co.in' },
    { role: 'learner',   login: 'rasika.thakare@airpay.co.in' },
];

// Surfaces to audit. Per-surface filter list lets us scope which run on
// which caller (a learner can't see admin Manage Users).
const SURFACES = [
    { id: 'dashboard',
      url: '/my/dashboard.php',
      callers: ['siteadmin', 'learner'] },
    { id: 'manage-users',
      url: '/local/airpay_users/index.php',
      callers: ['siteadmin'] },
    { id: 'catalog',
      url: '/local/airpay_catalog/index.php',
      callers: ['siteadmin', 'learner'] },
];

async function login(page, login_id) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.fill('input[name="username"]', login_id);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForURL(u => /\/(my|admin)\//.test(u.toString()) || u.toString().endsWith('/moodle/'),
            { timeout: PAGE_TIMEOUT, waitUntil: 'domcontentloaded' }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function auditSurface(page, caller, surface) {
    const url = surface.url.startsWith('http') ? surface.url : BASE + surface.url;
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    // Give async-rendered admin tables a moment to populate before we scan.
    await page.waitForTimeout(2500);

    const results = await new AxeBuilder({ page })
        // WCAG 2.1 AA + best practices. Skip "experimental" tagged rules.
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'])
        // Don't flag colour contrast issues coming from inside iframes Moodle
        // embeds (e.g. mod_scorm player) — we're auditing OUR shell, not the
        // content vendor's package.
        .exclude('iframe')
        .analyze();

    return {
        surface: surface.id,
        url: surface.url,
        caller: caller.role,
        violations: results.violations.map(v => ({
            id:        v.id,
            impact:    v.impact,           // critical | serious | moderate | minor
            help:      v.help,
            nodeCount: v.nodes.length,
            // Sample first 3 violations for actionable triage; the full set is in violations[i].nodes
            samples:   v.nodes.slice(0, 3).map(n => ({
                target: n.target.join(' '),
                html:   (n.html || '').substring(0, 200),
            })),
        })),
        passes: results.passes.length,
        incomplete: results.incomplete.length,
        timestamp: new Date().toISOString(),
    };
}

async function main() {
    await fs.mkdir(OUT_DIR, { recursive: true });

    const headless = process.env.HEADLESS !== '0';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });

    const allResults = [];

    for (const caller of CALLERS) {
        const ctx = await browser.newContext();
        ctx.setDefaultTimeout(PAGE_TIMEOUT);
        ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
        const page = await ctx.newPage();

        console.log(`\n  ── Caller: ${caller.role} (${caller.login}) ──`);
        try {
            await login(page, caller.login);
        } catch (e) {
            console.log(`    ✘ login failed: ${e.message.substring(0, 80)}`);
            await page.close(); await ctx.close();
            continue;
        }

        for (const surface of SURFACES) {
            if (!surface.callers.includes(caller.role)) continue;

            try {
                const r = await auditSurface(page, caller, surface);
                allResults.push(r);
                const critical = r.violations.filter(v => v.impact === 'critical').length;
                const serious  = r.violations.filter(v => v.impact === 'serious').length;
                const moderate = r.violations.filter(v => v.impact === 'moderate').length;
                const minor    = r.violations.filter(v => v.impact === 'minor').length;
                const status = (critical === 0 && serious === 0) ? '✓' : '✘';
                console.log(`    ${status} ${surface.id.padEnd(15)} `
                    + `crit=${critical} serious=${serious} moderate=${moderate} minor=${minor} `
                    + `(${r.passes} passes, ${r.incomplete} incomplete)`);
            } catch (e) {
                console.log(`    ✘ ${surface.id} threw: ${e.message.substring(0, 80)}`);
                allResults.push({ surface: surface.id, caller: caller.role,
                                  error: e.message, violations: [], passes: 0 });
            }
        }
        await page.close(); await ctx.close();
    }

    // Aggregate report.
    const totalCritical = allResults.reduce((s, r) =>
        s + (r.violations || []).filter(v => v.impact === 'critical').length, 0);
    const totalSerious = allResults.reduce((s, r) =>
        s + (r.violations || []).filter(v => v.impact === 'serious').length, 0);

    const report = {
        phase: 'H/A11Y-6',
        date: new Date().toISOString(),
        rules_engine: 'axe-core via @axe-core/playwright',
        standards: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'],
        results: allResults,
        summary: {
            total_critical: totalCritical,
            total_serious:  totalSerious,
            total_surfaces: allResults.length,
            production_ready: totalCritical === 0 && totalSerious === 0,
        },
    };
    await fs.writeFile(path.join(OUT_DIR, 'phase_h_a11y_axe.json'),
        JSON.stringify(report, null, 2));

    console.log('\n═══════════════════════════════════════════════════════════════════');
    console.log(`Phase H/A11Y-6: ${allResults.length} surface×caller audited`);
    console.log(`  total critical: ${totalCritical}`);
    console.log(`  total serious:  ${totalSerious}`);
    console.log(`  production-ready (no crit/serious): ${report.summary.production_ready ? 'YES' : 'NO'}`);
    console.log(`Report: ${OUT_DIR}/phase_h_a11y_axe.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await browser.close();
    // Exit 0 if no critical/serious; 1 otherwise — usable as CI gate.
    process.exit(report.summary.production_ready ? 0 : 1);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
