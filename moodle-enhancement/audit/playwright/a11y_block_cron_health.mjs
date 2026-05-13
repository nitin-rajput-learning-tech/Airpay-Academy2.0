// Engineering 20 — axe-core a11y test for block_airpay_cron_health.
//
// Why a fixture (not a live Moodle page)?
//
// The block lives on /admin/index.php as an opt-in widget. Adding it
// programmatically in Playwright requires walking the "Add a block"
// admin UI which is brittle across Moodle theme changes. We instead
// snapshot the block's get_content() HTML into a static fixture, then
// run axe against the fixture. The contract is that the fixture must
// stay in lockstep with the PHP source — when the block's HTML output
// changes, update the fixture in the same commit.
//
// Why scoped axe (not full-page)?
//
// We're auditing OUR block, not Moodle's chrome. Page-level rules like
// `page-has-heading-one` or `region` are skipped via AxeBuilder.include().
//
// CI usage:
//
//   node moodle-enhancement/audit/playwright/a11y_block_cron_health.mjs
//
// Exit codes:
//   0  no critical/serious axe violations
//   1  one or more critical/serious violations — block FAILS a11y gate
//   2  test harness error (Playwright failed, fixture missing, etc.)
//
// Coverage:
//   - colour-contrast (WCAG 2.1 AA — 4.5:1 normal, 3:1 large)
//   - link-name           — "View task logs" link
//   - aria-label / aria-labelledby on landmarks
//   - heading order       — the h5 sub-headings inside an h2 region
//   - colour-only severity — green/amber/red KPI numbers
//
// What axe CAN'T catch (so we annotate as TODO for manual screen-reader test):
//   - Screen reader announces "1 Airpay tasks stuck" intelligibly?
//     (the KPI value + label needs an ARIA live region for runtime updates,
//      but for static admin dashboard the current markup is acceptable)

import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import url from 'node:url';

const __dirname = path.dirname(url.fileURLToPath(import.meta.url));
const FIXTURE   = path.join(__dirname, 'fixtures', 'block_cron_health_fixture.html');
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const OUT_FILE  = 'a11y_block_cron_health.json';

async function main() {
    // Confirm the fixture exists before spinning up the browser — easier
    // to debug a missing-file error than a navigation timeout.
    try { await fs.access(FIXTURE); }
    catch { console.error(`FATAL: fixture missing: ${FIXTURE}`); process.exit(2); }

    await fs.mkdir(OUT_DIR, { recursive: true });

    // `channel: 'chrome'` uses the OS-installed Chrome rather than the
    // bundled chrome-headless-shell. On Windows the headless-shell
    // binary that ships with Playwright 1.46 fails with STATUS_DLL_
    // INIT_FAILED (0xC0000142) — the system Chrome path is robust.
    const browser = await chromium.launch({
        headless: process.env.HEADLESS !== '0',
        channel:  'chrome',
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });

    const context = await browser.newContext();
    const page    = await context.newPage();

    const fileUrl = 'file://' + FIXTURE.replace(/\\/g, '/');
    await page.goto(fileUrl, { waitUntil: 'domcontentloaded', timeout: 15_000 });

    // Run axe-core, scoped to the cron-health block region.
    //
    // We scope to `.block-region` (the simulated Moodle block chrome
    // around the block's output) rather than `.airpay-cron-health`
    // (the KPI flex container only) so that the `<h5>` sub-headings
    // and the `<ul>` task lists — which the PHP renders OUTSIDE
    // `.airpay-cron-health` — are inside the scan window. The
    // surrounding `<h1>Site administration</h1>` page chrome stays
    // out of scope.
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
        engineering: 'Eng 20 — block_airpay_cron_health a11y baseline',
        date: new Date().toISOString(),
        target: 'block_airpay_cron_health (rendered via fixture)',
        fixture: FIXTURE,
        standards: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'],
        scope: '.airpay-cron-health (scoped, page chrome excluded)',
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
    console.log(`Eng 20 / a11y_block_cron_health — ${results.violations.length} violations`);
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

    // CI gate: fail on critical or serious; tolerate moderate/minor.
    process.exit(report.summary.production_ready ? 0 : 1);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
