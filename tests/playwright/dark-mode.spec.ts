import { test, expect, Page } from '@playwright/test';
import { personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Dark-mode surface — happy path.
 *
 * Applies the theme's body.dark-mode class (the product's explicit
 * opt-in dark-mode mechanism — there is intentionally NO
 * prefers-color-scheme media query in the compiled CSS) on the
 * AUTHENTICATED dashboard and asserts the dark token cascade flips the
 * body background. Catches the regression class: dark tokens never
 * compile into the bundle, or the cascade stops applying to the
 * dashboard surface.
 *
 * Rewritten 2026-06-11 (twice):
 *  - The original asserted prefers-color-scheme auto-darkening — a
 *    feature the product deliberately does not have (Chip I decision);
 *    it could never pass.
 *  - The first rewrite targeted the LOGIN page, but dark mode is an
 *    authenticated-user preference and the login page's own
 *    body#page-login-index background (id specificity) intentionally
 *    wins over body.dark-mode there. The dark-mode product surface is
 *    the authenticated app shell (see the 2026-06 "dark-mode regression
 *    walk — authenticated surfaces" task), so that is what we gate.
 */
async function gotoSettled(page: Page, path: string): Promise<void> {
    try {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
    } catch (e) {
        if (!/ERR_ABORTED|frame was detached|interrupted by another navigation/i.test(String(e))) throw e;
        await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    }
}

test.describe('Dark mode (class-driven cascade)', () => {
    const creds = personaCreds('LEARNER');
    test.skip(!creds.present, 'LEARNER creds not set (PLAYWRIGHT_LEARNER_USER/_PASS)');

    test('body.dark-mode flips the dashboard token cascade', async ({ page }) => {
        test.setTimeout(240_000); // single-process local FCGI serializes PHP; align with render-smoke's local pacing (CI is much faster)
        await login(page, creds);
        await assertAuthenticated(page);
        await gotoSettled(page, '/my/');
        await expect(page.locator('body')).toBeVisible();

        const lightBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);

        const darkBg = await page.evaluate(() => {
            document.body.classList.add('dark-mode');
            return getComputedStyle(document.body).backgroundColor;
        });
        expect(darkBg).toMatch(/^rgb/);
        expect(darkBg).not.toBe(lightBg);

        const rgbMatch = darkBg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        expect(rgbMatch).not.toBeNull();
        if (rgbMatch) {
            const [r, g, b] = [Number(rgbMatch[1]), Number(rgbMatch[2]), Number(rgbMatch[3])];
            const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
            expect(luminance).toBeLessThan(0.5);
        }
    });
});
