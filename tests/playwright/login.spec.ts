import { test, expect } from '@playwright/test';

/**
 * Login surface — happy path.
 *
 * Asserts the login form renders, accepts input, and contains the
 * CSRF logintoken Moodle requires for any auth POST. Catches the
 * regression class: theme change accidentally hides the form, drops
 * the logintoken, or breaks the password field name attribute.
 */
test.describe('Login page', () => {
    test('renders username + password form with CSRF token', async ({ page }) => {
        await page.goto('/login/index.php');

        await expect(page).toHaveTitle(/.+/);

        const username = page.locator('input[name="username"]');
        const password = page.locator('input[name="password"]');
        const submit = page.locator('button[type="submit"], input[type="submit"]').first();

        await expect(username).toBeVisible();
        await expect(password).toBeVisible();
        await expect(submit).toBeVisible();

        await expect(username).toBeEditable();
        await expect(password).toBeEditable();
        await expect(password).toHaveAttribute('type', 'password');

        const logintoken = page.locator('input[name="logintoken"]');
        await expect(logintoken).toHaveCount(1);
        const tokenValue = await logintoken.inputValue();
        expect(tokenValue.length).toBeGreaterThan(0);

        await username.fill('demo.user');
        await expect(username).toHaveValue('demo.user');
    });
});
