// axe-core a11y test for block_airpay_cert_health.
//
// Mirror of a11y_block_cron_health.mjs — the cert-health block uses
// the same design + a11y pattern, so this test follows the same
// fixture-based approach (no live Moodle dependency).
//
// CI usage:
//
//   node moodle-enhancement/audit/playwright/a11y_block_cert_health.mjs
//
// Exit codes:
//   0  no critical/serious axe violations
//   1  one or more critical/serious violations — block FAILS a11y gate
//   2  test harness error (Playwright failed, fixture missing, etc.)
//
// Coverage matches the cron-health block:
//   - colour-contrast (WCAG 2.1 AA — 4.5:1 normal, 3:1 large)
//   - link-name           — "View full email delivery log" link
//   - aria-label / aria-labelledby on landmarks
//   - heading order       — no h5/h6 jumps in this block (no
//     sub-headings — flat KPI grid only)
//   - colour-only severity — green/amber/red KPI numbers backed by
//     text severity badge + aria-label

import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import url from 'node:url';

const __dirname = path.dirname(url.fileURLToPath(import.meta.url));
const FIXTURE   = path.join(__dirname, 'fixtures', 'block_cert_health_fixture.html');
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const OUT_FILE  = 'a11y_block_cert_health.json';

async function main() {
    try { await fs.access(FIXTURE); }
    catch { console.error(`FATAL: fixture missing: ${FIXTURE}`); process.exit(2); }

    await fs.mkdir(OUT_DIR, { recursive: true });

    // `channel: 'chrome'` — same Playwright/Windows workaround as the
    // cron-health test. See that file for the rationale (the bundled
    // chrome-headless-shell binary fails on Windows with
    // STATUS_DLL_INIT_FAILED).
    const browser = await chromium.launch({
        headless: process.env.HEADLESS !== '0',
        channel:  'chrome',
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });

    const context = await browser.newContext();
    const page    = await context.newPage();

    const fileUrl = 'file://' + FIXTURE.replace(/\\/g, '/');
    await page.goto(fileUrl, { waitUntil: 'domcontentloaded', timeout: 15_000 });

    // Scoped to .block-region — see the cron-health test for the
    // rationale (we audit our block, not Moodle's surrounding chrome).
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'])
        .include('.block-region')
        .analyze();

    const enriched = results.violations.map(v => ({
        id:       v.id,
        impact:   v.impact,
        help:     v.help,
        helpUrl:  v.helpUrl,
        nodeCount: v.nodes.length,
        samples:  v.nodes.slice(0, 5).map(n => ({
            target: n.target.join(' '),
            html:   (n.html || '').substring(0, 240),
            failureSummary: (n.failureSummary || '').substring(0, 400),
        })),
    }));

    const critical = enriched.filter(v => v.impact === 'critical');
    const serious  = enriched.filter(v => v.impact === 'serious');
    const moderate = enriched.filter(v => v.impact === 'moderate');
    const minor    = enriched.filter(v => v.impact === 'minor');

    const report = {
        target: 'block_airpay_cert_health (rendered via fixture)',
        fixture: FIXTURE,
        date: new Date().toISOString(),
        standards: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'],
        scope: '.block-region',
        passes: results.passes.length,
        incomplete: results.incomplete.length,
        violations: enriched,
        summary: {
            critical: critical.length,
            serious:  serious.length,
            moderate: moderate.length,
            minor:    minor.length,
            production_ready: critical.length === 0 && serious.length === 0,
        },
    };

    await fs.writeFile(path.join(OUT_DIR, OUT_FILE),
        JSON.stringify(report, null, 2));

    console.log('═══════════════════════════════════════════════════════════════════');
    console.log(`a11y_block_cert_health — ${results.violations.length} violations`);
    console.log(`  critical : ${critical.length}`);
    console.log(`  serious  : ${serious.length}`);
    console.log(`  moderate : ${moderate.length}`);
    console.log(`  minor    : ${minor.length}`);
    console.log(`  passes   : ${results.passes.length}`);
    console.log(`Report: ${OUT_DIR}/${OUT_FILE}`);
    console.log('═══════════════════════════════════════════════════════════════════');

    if (critical.length + serious.length > 0) {
        console.log('\nViolations needing fix (critical + serious):');
        for (const v of [...critical, ...serious]) {
            console.log(`  [${v.impact}] ${v.id} (${v.nodeCount} node${v.nodeCount === 1 ? '' : 's'}): ${v.help}`);
            for (const s of v.samples.slice(0, 2)) {
                console.log(`     → ${s.target}`);
            }
        }
    }

    await context.close();
    await browser.close();
    process.exit(report.summary.production_ready ? 0 : 1);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
