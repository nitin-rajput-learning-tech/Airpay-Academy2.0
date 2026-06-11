import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { Persona, personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Gate 2 — A11Y half (ADR-027), via axe-core.
 *
 * The accessibility half of Gate 2. It is PIXEL-INDEPENDENT, so it lands
 * safely ahead of the white-label UI change (the screenshot-diff half must be
 * seeded against the FINAL UI — see moodle-enhancement/docs/COVERAGE-MATRIX.md).
 *
 * For each persona × curated surface it asserts ZERO axe-core violations at
 * impact 'serious' or 'critical' against the WCAG 2.0/2.1 A + AA rule sets.
 * moderate/minor are printed in the failure summary but DO NOT fail the gate:
 * Moodle core ships known low-impact issues we do not own, and a noisy gate
 * gets ignored. Tighten FAIL_IMPACTS as the platform's a11y debt is paid down.
 *
 * Personas read PLAYWRIGHT_<PERSONA>_USER/_PASS and SKIP cleanly when unset
 * (mirrors render-smoke.spec.ts), so CI stays green until accounts are wired.
 * course/view is included only when PLAYWRIGHT_COURSE_ID is set.
 */

interface Surface { path: string; name: string; }

const COURSE_ID = process.env.PLAYWRIGHT_COURSE_ID ?? '';

// Same curated surface set as Gate 1 (render-smoke). Expand in lock-step.
const SURFACES: Surface[] = [
    { path: '/my/',              name: 'dashboard' },
    { path: '/my/courses.php',   name: 'my-courses' },
    { path: '/user/profile.php', name: 'profile' },
];
if (COURSE_ID) {
    SURFACES.push({ path: `/course/view.php?id=${COURSE_ID}`, name: 'course-view' });
}

// Only these impacts fail the gate. moderate/minor are advisory (reported, not failed).
const FAIL_IMPACTS = new Set(['serious', 'critical']);

/**
 * Navigate robustly — mirrors render-smoke's gotoSettled. NEVER wait for
 * networkidle (Moodle keeps polling connections open); wait for AMD instead.
 * Tolerate a server/JS redirect superseding the initial navigation.
 */
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

/** Run axe on the current page and fail on serious/critical violations. */
async function axeSurface(page: Page, label: string): Promise<void> {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const blocking = results.violations.filter((v) => FAIL_IMPACTS.has(v.impact ?? ''));
    const summary = blocking
        .map((v) => `[${v.impact}] ${v.id} ×${v.nodes.length} — ${v.help}`)
        .join('\n  ');

    expect(
        blocking,
        `${label}: ${blocking.length} serious/critical a11y violation(s):\n  ${summary}`,
    ).toHaveLength(0);
}

async function axeSurfaces(page: Page, who: string): Promise<void> {
    for (const s of SURFACES) {
        await gotoSettled(page, s.path);
        await axeSurface(page, `${who}/${s.name}`);
    }
}

// ── Persona matrix (learner / manager / compliance / author) ───────────────
const PERSONAS: Persona[] = ['LEARNER', 'MANAGER', 'COMPLIANCE', 'AUTHOR'];
for (const persona of PERSONAS) {
    const creds = personaCreds(persona);
    test.describe(`a11y · ${persona}`, () => {
        test.skip(!creds.present, `${persona} creds not set (PLAYWRIGHT_${persona}_USER/_PASS)`);
        test(`${persona}: curated surfaces have no serious/critical a11y violations`, async ({ page }) => {
            test.setTimeout(240_000); // multi-surface walk; slow XAMPP first loads (CI Linux is much faster)
            await login(page, creds);
            await assertAuthenticated(page); // fail loud if login bounced — don't a11y-scan the login page
            await axeSurfaces(page, persona);
        });
    });
}

// ── Site admin (uses the existing PLAYWRIGHT_ADMIN_USER/_PASS convention) ───
test.describe('a11y · ADMIN', () => {
    const user = process.env.PLAYWRIGHT_ADMIN_USER ?? '';
    const pass = process.env.PLAYWRIGHT_ADMIN_PASS ?? '';
    test.skip(!(user && pass), 'ADMIN creds not set (PLAYWRIGHT_ADMIN_USER/_PASS)');
    test('ADMIN: curated surfaces have no serious/critical a11y violations', async ({ page }) => {
        test.setTimeout(240_000); // align with the persona tests; admin surfaces are the slowest on single-process local FCGI
        await login(page, { user, pass, present: true });
        await assertAuthenticated(page);
        await axeSurfaces(page, 'ADMIN');
    });
});
