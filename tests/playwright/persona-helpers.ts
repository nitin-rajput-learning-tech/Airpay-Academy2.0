import { Page, expect } from '@playwright/test';

/**
 * Shared persona-login helpers for the authenticated journey specs
 * (F-033 — learner / manager / compliance / author).
 *
 * NOT a *.spec.ts file, so Playwright's `testMatch: '*.spec.ts'` does
 * not collect it as a test — it is imported by the persona specs.
 *
 * Credentials come from env vars (never hardcoded), mirroring the
 * existing admin pattern in dashboard.spec.ts
 * (PLAYWRIGHT_ADMIN_USER / PLAYWRIGHT_ADMIN_PASS). Each persona reads
 * PLAYWRIGHT_<PERSONA>_USER / PLAYWRIGHT_<PERSONA>_PASS. When a
 * persona's credentials are NOT set, that spec skips cleanly (see
 * `personaCreds` + the top-level test.skip in each spec) so CI stays
 * green until per-persona test accounts are provisioned.
 *
 * Provision accounts locally and export, e.g.:
 *   export PLAYWRIGHT_LEARNER_USER=demo.learner
 *   export PLAYWRIGHT_LEARNER_PASS='…'
 * then run:  npx playwright test learner.spec.ts
 */

export type Persona = 'LEARNER' | 'MANAGER' | 'COMPLIANCE' | 'AUTHOR';

export interface Creds {
    user: string;
    pass: string;
    /** True when both env vars are set — gate each spec on this. */
    present: boolean;
}

/** Read PLAYWRIGHT_<PERSONA>_USER / _PASS from the environment. */
export function personaCreds(persona: Persona): Creds {
    const user = process.env[`PLAYWRIGHT_${persona}_USER`] ?? '';
    const pass = process.env[`PLAYWRIGHT_${persona}_PASS`] ?? '';
    return { user, pass, present: user.length > 0 && pass.length > 0 };
}

/**
 * Log in through the real airpayux login form. Mirrors the flow proven
 * in dashboard.spec.ts: fill username + password, submit, and wait for
 * the post-login redirect to a logged-in area.
 */
export async function login(page: Page, creds: Creds): Promise<void> {
    // Retry once. Under DB/PHP-session contention Moodle can reject the first
    // POST with a transient "Your session has timed out" (login-token/session
    // race); a fresh GET + resubmit clears it. Success = the login form is gone.
    for (let attempt = 1; attempt <= 2; attempt++) {
        await page.goto('/login/index.php', { waitUntil: 'domcontentloaded' });
        // Scope to the main login form's unique ids. The airpayux/sentientia login
        // page ALSO renders a hidden guest-login form (`#guestlogin`, with its own
        // `name="username"`), so a bare `input[name="username"]` selector matches
        // two elements and trips Playwright strict mode. `#username`/`#password`/
        // `#loginbtn` (form#login) are unique and stable.
        await page.locator('#username').fill(creds.user);
        await page.locator('#password').fill(creds.pass);
        // Generous click timeout: the post-login navigation can be slow on a cold
        // hit (runtime SCSS compile, cold DB/caches); 10s actionTimeout is too tight.
        await page.locator('#loginbtn').click({ timeout: 90_000 }); // first login POST of a run can exceed 45s on the single-process local FCGI backend
        await page.waitForURL(/\/(my|admin|user|course)/i, { timeout: 90_000 }).catch(() => undefined);
        if ((await page.locator('#username').count()) === 0) {
            return; // login form gone → authenticated
        }
    }
}

/**
 * Assert we are on an authenticated page (NOT bounced back to the login
 * form) and the main content region rendered. This is the core
 * per-persona regression net: catches "this persona can't log in",
 * "dashboard layout crashes for this persona", and "broken post-login
 * redirect".
 */
export async function assertAuthenticated(page: Page): Promise<void> {
    // If auth failed, Moodle re-renders the login form (username input present).
    await expect(page.locator('input[name="username"]')).toHaveCount(0);

    const main = page.locator('#region-main, [role="main"], main').first();
    await expect(main).toBeVisible();

    // No dev-mode fatal/exception leaked onto the page.
    const html = await page.content();
    expect(html).not.toMatch(/Debug info:|Stack trace:|Coding error detected/i);
    expect(html.length).toBeGreaterThan(500);
}

/**
 * Soft reachability check for a persona-landmark URL: the page loads,
 * the main region renders, and it is not a Moodle error/permission
 * page. Avoids brittle deep-selector assertions we cannot verify
 * run-to-green here.
 */
export async function assertReachable(page: Page, url: string): Promise<void> {
    await page.goto(url);
    const main = page.locator('#region-main, [role="main"], main').first();
    await expect(main).toBeVisible();
    const html = await page.content();
    expect(html).not.toMatch(/Debug info:|Stack trace:|Coding error detected|nopermissions/i);
}
