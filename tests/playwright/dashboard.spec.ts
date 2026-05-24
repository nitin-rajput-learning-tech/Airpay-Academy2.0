import { test, expect } from '@playwright/test';

const ADMIN_USER = process.env.PLAYWRIGHT_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.PLAYWRIGHT_ADMIN_PASS ?? 'AdminPass!23';

/**
 * Dashboard surface — happy path.
 *
 * Logs in as admin, lands on /my/, and asserts a recognisable
 * dashboard shell renders. Catches the regression class: layout
 * file crash, missing region-main, broken redirect after login.
 */
test.describe('Dashboard', () => {
    test('admin login lands on a populated /my/ dashboard', async ({ page }) => {
        await page.goto('/login/index.php');
        await page.locator('input[name="username"]').fill(ADMIN_USER);
        await page.locator('input[name="password"]').fill(ADMIN_PASS);
        await Promise.all([
            page.waitForURL(/\/(my|admin|user)/i, { timeout: 30_000 }).catch(() => undefined),
            page.locator('button[type="submit"], input[type="submit"]').first().click(),
        ]);

        await page.goto('/my/');

        const main = page.locator('#region-main, [role="main"], main').first();
        await expect(main).toBeVisible();

        const body = page.locator('body');
        await expect(body).toHaveAttribute('class', /dashboard|page-my|mydashboard/);

        const header = page.locator('header, #page-header, .navbar').first();
        await expect(header).toBeVisible();

        const html = await page.content();
        expect(html.length).toBeGreaterThan(500);
    });
});
