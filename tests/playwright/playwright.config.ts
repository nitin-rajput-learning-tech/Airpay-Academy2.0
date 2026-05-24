import { defineConfig, devices } from '@playwright/test';

/**
 * Linux-based Playwright gate for Airpay Academy / Sentientia LMS.
 *
 * Runs functional + visual smoke against a containerised Moodle
 * (moodlehq/moodle-php-apache:8.2) in the CI `playwright-linux` job.
 *
 * baseURL is configurable so the same suite runs against:
 *   - GitHub Actions docker stack (default http://localhost:8000)
 *   - Local XAMPP                 (PLAYWRIGHT_BASE_URL=http://localhost:8080/moodle)
 *   - Staging / preview           (PLAYWRIGHT_BASE_URL=https://staging.airpay.academy)
 *
 * See docs/ci/PLAYWRIGHT-GATE.md for the full runbook.
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8000';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.ts',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI
        ? [['list'], ['github'], ['html', { open: 'never', outputFolder: 'playwright-report' }], ['junit', { outputFile: 'test-results/junit.xml' }]]
        : [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    snapshotPathTemplate: '__screenshots__/{projectName}/{testFilePath}/{arg}{ext}',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
        navigationTimeout: 30_000,
        actionTimeout: 10_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 900 } },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'], viewport: { width: 1280, height: 900 } },
        },
    ],
});
