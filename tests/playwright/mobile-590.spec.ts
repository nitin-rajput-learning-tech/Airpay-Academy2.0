import { test, expect } from '@playwright/test';

/**
 * Mobile (590px) surface — happy path.
 *
 * 590px is the primary mobile breakpoint locked into
 * scss/moodle/custom_media.scss. Asserts that at this viewport
 * the login surface renders without horizontal overflow and the
 * navbar collapses to a mobile-friendly height. Catches the
 * regression class: layout pushes the page wider than the viewport,
 * navbar grows to desktop height, or login form drops off-screen.
 */
test.describe('Mobile 590px layout', () => {
    test.use({ viewport: { width: 590, height: 900 } });

    test('login renders without horizontal overflow at 590px', async ({ page }) => {
        test.setTimeout(240_000); // single-process local FCGI serializes PHP; align with render-smoke's local pacing (CI is much faster)
        await page.goto('/login/index.php', { waitUntil: 'domcontentloaded' }); // NEVER default-'load' here: serialized local PHP makes full-subresource waits flood the queue (render-smoke's documented pattern)

        await expect(page.locator('body')).toBeVisible();

        const scrollMetrics = await page.evaluate(() => ({
            documentWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            bodyWidth: document.body.scrollWidth,
        }));
        expect(scrollMetrics.documentWidth).toBeLessThanOrEqual(scrollMetrics.clientWidth + 2);
        expect(scrollMetrics.bodyWidth).toBeLessThanOrEqual(scrollMetrics.clientWidth + 2);

        const navbar = page.locator('header .navbar, header.navbar, nav.navbar').first();
        if ((await navbar.count()) > 0) {
            const box = await navbar.boundingBox();
            expect(box).not.toBeNull();
            expect(box!.height).toBeLessThanOrEqual(120);
        }

        const username = page.locator('#username'); // NOT [name=username]: Moodle's guest-access form carries a hidden username=guest input on the same page
        await expect(username).toBeVisible();
        const userBox = await username.boundingBox();
        expect(userBox).not.toBeNull();
        expect(userBox!.x).toBeGreaterThanOrEqual(0);
        expect(userBox!.x + userBox!.width).toBeLessThanOrEqual(scrollMetrics.clientWidth + 2);
    });
});
