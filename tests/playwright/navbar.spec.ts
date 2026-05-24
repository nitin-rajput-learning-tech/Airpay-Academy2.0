import { test, expect } from '@playwright/test';

/**
 * Navbar surface — happy path.
 *
 * The airpayux theme owns templates/navbar.mustache (standalone fork).
 * Asserts the navbar element renders on the login page and exposes a
 * site brand + at least one nav link target. Catches the regression
 * class: navbar template removed, brand image broken, primary nav
 * silently emits an empty <ul>.
 */
test.describe('Navbar (theme: airpayux)', () => {
    test('renders brand and at least one nav target', async ({ page }) => {
        await page.goto('/login/index.php');

        const navbar = page.locator('header .navbar, header.navbar, nav.navbar, .primary-navigation').first();
        await expect(navbar).toBeVisible();

        const brand = navbar.locator('a').filter({ has: page.locator('img, svg') }).first();
        const brandText = navbar.locator('.navbar-brand, [data-brand], a[href$="/"]').first();
        const hasBrand = (await brand.count()) > 0 || (await brandText.count()) > 0;
        expect(hasBrand).toBeTruthy();

        const anchors = navbar.locator('a[href]');
        const anchorCount = await anchors.count();
        expect(anchorCount).toBeGreaterThan(0);

        const firstHref = await anchors.first().getAttribute('href');
        expect(firstHref).not.toBeNull();
        expect((firstHref ?? '').length).toBeGreaterThan(0);

        const box = await navbar.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.height).toBeGreaterThan(0);
    });
});
