import { test, expect, Page } from '@playwright/test';
import { Persona, personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Gate 2 — VISUAL half (ADR-027), via Playwright screenshot diff.
 *
 * GATED OFF by default — runs ONLY when PLAYWRIGHT_VISUAL=1. Without a committed
 * baseline, toHaveScreenshot fails on first run, so the gate stays dormant (every
 * test skips) until baselines are seeded; this keeps the advisory CI job clean.
 *
 * The visual half was deferred until AFTER the white-label UI change (W-A) so the
 * baselines capture the FINAL chrome — capturing them earlier would have made them
 * instantly stale (see moodle-enhancement/docs/COVERAGE-MATRIX.md).
 *
 * ── TO ACTIVATE (one-time, on a WORKING Playwright env — CI Linux, NOT the local
 *    XAMPP box, whose slow first-load + post-purge SCSS recompile makes shots flaky):
 *   1. Seed baselines (per persona whose creds are wired):
 *        PLAYWRIGHT_VISUAL=1 PLAYWRIGHT_BASE_URL=<url> PLAYWRIGHT_LEARNER_USER=… PLAYWRIGHT_LEARNER_PASS=… \
 *          npx playwright test visual.spec.ts --update-snapshots
 *   2. Commit the generated tests/playwright/visual.spec.ts-snapshots/**.png
 *   3. Set PLAYWRIGHT_VISUAL=1 in the CI playwright-linux job env.
 *   Thereafter the gate fails any unintended pixel drift on the curated surfaces.
 *
 * Surfaces + personas mirror render-smoke (Gate 1) / a11y-smoke (Gate 2 a11y) so the
 * three gates cover the same matrix cells — expand all three in lock-step.
 */

interface Surface { path: string; name: string; }

const COURSE_ID = process.env.PLAYWRIGHT_COURSE_ID ?? '';
const VISUAL_ON = !!process.env.PLAYWRIGHT_VISUAL;

const SURFACES: Surface[] = [
    { path: '/my/',              name: 'dashboard' },
    { path: '/my/courses.php',   name: 'my-courses' },
    { path: '/user/profile.php', name: 'profile' },
];
if (COURSE_ID) {
    SURFACES.push({ path: `/course/view.php?id=${COURSE_ID}`, name: 'course-view' });
}

/** Robust navigation — mirrors render-smoke's gotoSettled (no networkidle; wait for AMD). */
async function gotoSettled(page: Page, path: string): Promise<void> {
    try {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
    } catch (e) {
        if (!/ERR_ABORTED|frame was detached|interrupted by another navigation/i.test(String(e))) throw e;
        await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    }
    await page.waitForFunction(
        () => typeof (window as { require?: unknown }).require === 'function',
        null,
        { timeout: 10_000 },
    ).catch(() => undefined);
}

async function shotSurfaces(page: Page, who: string): Promise<void> {
    for (const s of SURFACES) {
        await gotoSettled(page, s.path);
        await page.waitForTimeout(1500); // let late AMD widgets (charts/drawers) settle
        await expect(page).toHaveScreenshot(`${who}-${s.name}.png`, {
            fullPage: true,
            animations: 'disabled',
            // Tolerance for sub-pixel AA + the handful of live counters; tighten post-seed,
            // or add `mask: [page.locator('.airpay-login__hero-stat')]` for known dynamic regions.
            maxDiffPixelRatio: 0.02,
        });
    }
}

// ── Persona matrix (learner / manager / compliance / author) ───────────────
const PERSONAS: Persona[] = ['LEARNER', 'MANAGER', 'COMPLIANCE', 'AUTHOR'];
for (const persona of PERSONAS) {
    const creds = personaCreds(persona);
    test.describe(`visual · ${persona}`, () => {
        test.skip(!VISUAL_ON, 'visual gate off — set PLAYWRIGHT_VISUAL=1 after seeding baselines');
        test.skip(!creds.present, `${persona} creds not set (PLAYWRIGHT_${persona}_USER/_PASS)`);
        test(`${persona}: curated surfaces match baseline`, async ({ page }) => {
            test.setTimeout(240_000);
            await login(page, creds);
            await assertAuthenticated(page);
            await shotSurfaces(page, persona);
        });
    });
}

// ── Site admin ──────────────────────────────────────────────────────────────
test.describe('visual · ADMIN', () => {
    const user = process.env.PLAYWRIGHT_ADMIN_USER ?? '';
    const pass = process.env.PLAYWRIGHT_ADMIN_PASS ?? '';
    test.skip(!VISUAL_ON, 'visual gate off — set PLAYWRIGHT_VISUAL=1 after seeding baselines');
    test.skip(!(user && pass), 'ADMIN creds not set (PLAYWRIGHT_ADMIN_USER/_PASS)');
    test('ADMIN: curated surfaces match baseline', async ({ page }) => {
        test.setTimeout(120_000);
        await login(page, { user, pass, present: true });
        await assertAuthenticated(page);
        await shotSurfaces(page, 'ADMIN');
    });
});
