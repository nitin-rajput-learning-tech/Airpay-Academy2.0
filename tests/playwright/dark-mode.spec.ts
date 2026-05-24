import { test, expect } from '@playwright/test';

/**
 * Dark-mode surface — happy path.
 *
 * Sets the browser to prefers-color-scheme: dark and asserts that
 * the airpayux dark-mode CSS applies (body background isn't the
 * default light background). Catches the regression class: dark
 * tokens never compile into the bundle, or are gated behind a
 * stylesheet that never loads on the login page.
 */
test.describe('Dark mode (prefers-color-scheme)', () => {
    test.use({ colorScheme: 'dark' });

    test('login page honours prefers-color-scheme: dark', async ({ page }) => {
        await page.goto('/login/index.php');

        await expect(page.locator('body')).toBeVisible();

        const bg = await page.evaluate(() => {
            const el = document.body;
            return window.getComputedStyle(el).backgroundColor;
        });
        expect(bg).not.toBeNull();
        expect(bg).toMatch(/^rgb/);

        const lightWhite = ['rgb(255, 255, 255)', 'rgba(255, 255, 255, 1)', 'rgb(242, 244, 251)'];
        expect(lightWhite).not.toContain(bg);

        const rgbMatch = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (rgbMatch) {
            const [r, g, b] = [Number(rgbMatch[1]), Number(rgbMatch[2]), Number(rgbMatch[3])];
            const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
            expect(luminance).toBeLessThan(0.5);
        }
    });
});
