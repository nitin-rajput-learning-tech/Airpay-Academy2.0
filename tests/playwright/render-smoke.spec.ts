import { test, expect, Page, ConsoleMessage } from '@playwright/test';
import { Persona, personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Gate 1 — RENDER-SMOKE (ADR-027).
 *
 * The gate that catches the "looks-fine-but-broken" class screenshot audits
 * are blind to. For each persona × curated surface it asserts:
 *   (a) 0 (non-benign) console errors / page errors,
 *   (b) AMD bootstrapped — `typeof window.require === 'function'`
 *        (would have caught the blank-charts / dead-JS bug),
 *   (c) NO literal `{{`/`}}` in the rendered <body>
 *        (would have caught the course/view.php comment leak + un-rendered tags),
 *   (d) a main / app-shell landmark actually rendered.
 *
 * Personas read PLAYWRIGHT_<PERSONA>_USER/_PASS (persona-helpers convention)
 * and SKIP cleanly when unset, so CI stays green until accounts are wired.
 * course/view is included only when PLAYWRIGHT_COURSE_ID is set (it needs a
 * real, enrollable course id).
 */

interface Surface { path: string; name: string; }

const COURSE_ID = process.env.PLAYWRIGHT_COURSE_ID ?? '';

// Curated high-value surfaces every authenticated persona should reach via the
// Sentientia app-shell. Start small + correct; expand as the matrix matures.
const SURFACES: Surface[] = [
    { path: '/my/',              name: 'dashboard' },
    { path: '/my/courses.php',   name: 'my-courses' },
    { path: '/user/profile.php', name: 'profile' },
];
if (COURSE_ID) {
    // The exact surface the comment leak shipped on — pin it in the gate.
    SURFACES.push({ path: `/course/view.php?id=${COURSE_ID}`, name: 'course-view' });
}

// Console noise that should NOT fail the gate (network 404s for optional
// assets, the service worker, favicon, etc.). Keep this list tight.
const BENIGN: RegExp[] = [
    /favicon/i,
    /manifest\.json/i,
    /\/local\/sentientia_pwa\/sw\.php/i,
    /net::ERR_/i,
    /Failed to load resource.*\b(404|403)\b/i,
];
const isBenign = (text: string): boolean => BENIGN.some((re) => re.test(text));

/** The four assertions, run on whatever page is currently loaded. */
async function renderSmoke(page: Page, label: string, errors: string[]): Promise<void> {
    // (b) AMD bootstrapped — dead JS makes pages look fine but inert.
    const hasRequire = await page.evaluate(() => typeof (window as { require?: unknown }).require === 'function');
    expect(hasRequire, `${label}: window.require should be a function (AMD bootstrapped)`).toBe(true);

    // (c) no leaked / un-rendered Mustache in the body.
    const bodyText = await page.evaluate(() => document.body?.innerText ?? '');
    const leak = bodyText.match(/\{\{[^}]{0,40}|\}\}/);
    expect(leak, `${label}: rendered body leaked literal Mustache near "${leak?.[0] ?? ''}"`).toBeNull();

    // (d) the page actually rendered a shell, not a blank / partial.
    await expect(
        page.locator('#region-main, [role="main"], main, .ap-sidebar').first(),
        `${label}: main / app-shell landmark should be visible`,
    ).toBeVisible();

    // (a) 0 real console errors collected during this surface's navigation.
    const real = errors.filter((e) => !isBenign(e));
    expect(real, `${label}: console/page errors -> ${real.join(' || ')}`).toHaveLength(0);
}

/**
 * Navigate robustly. Two Moodle-specific gotchas handled here:
 *  - NEVER wait for `networkidle`: Moodle keeps polling connections open
 *    (notifications / messaging), so networkidle never fires and silently burns
 *    the whole test timeout. Wait for AMD to bootstrap as the readiness signal.
 *  - A server/JS redirect can supersede the initial navigation (`ERR_ABORTED` /
 *    "frame was detached") — e.g. `/user/profile.php` → `?id=`. The browser is
 *    then loading the redirect target, so wait for THAT to settle, don't fail.
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

/** Visit each surface and run the render-smoke assertions. */
async function smokeSurfaces(page: Page, who: string): Promise<void> {
    let errors: string[] = [];
    page.on('console', (msg: ConsoleMessage) => { if (msg.type() === 'error') errors.push(msg.text()); });
    page.on('pageerror', (err) => errors.push(String(err)));

    for (const s of SURFACES) {
        errors = []; // reset per surface (the listeners push into the live binding)
        await gotoSettled(page, s.path);
        await renderSmoke(page, `${who}/${s.name}`, errors);
    }
}

// ── Persona matrix (learner / manager / compliance / author) ───────────────
const PERSONAS: Persona[] = ['LEARNER', 'MANAGER', 'COMPLIANCE', 'AUTHOR'];
for (const persona of PERSONAS) {
    const creds = personaCreds(persona);
    test.describe(`render-smoke · ${persona}`, () => {
        test.skip(!creds.present, `${persona} creds not set (PLAYWRIGHT_${persona}_USER/_PASS)`);
        test(`${persona}: curated surfaces render clean`, async ({ page }) => {
            test.setTimeout(240_000); // multi-surface walk; slow XAMPP first loads (CI Linux is much faster)
            await login(page, creds);
            await assertAuthenticated(page); // fail loud if login bounced — don't smoke-test the login page
            await smokeSurfaces(page, persona);
        });
    });
}

// ── Site admin (uses the existing PLAYWRIGHT_ADMIN_USER/_PASS convention) ───
test.describe('render-smoke · ADMIN', () => {
    const user = process.env.PLAYWRIGHT_ADMIN_USER ?? '';
    const pass = process.env.PLAYWRIGHT_ADMIN_PASS ?? '';
    test.skip(!(user && pass), 'ADMIN creds not set (PLAYWRIGHT_ADMIN_USER/_PASS)');
    test('ADMIN: curated surfaces render clean', async ({ page }) => {
        test.setTimeout(120_000); // multi-surface walk + cold-cache first loads
        await login(page, { user, pass, present: true });
        await assertAuthenticated(page);
        await smokeSurfaces(page, 'ADMIN');
    });
});
