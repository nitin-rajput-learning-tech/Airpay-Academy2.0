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

// Local single-process PHP backends (php-cgi behind Apache proxy_fcgi)
// serialize PHP-served assets, so page loads run slow without being broken.
// Override per-run, e.g. PLAYWRIGHT_NAV_TIMEOUT=120000 for local gates.
const navTimeout = process.env.PLAYWRIGHT_NAV_TIMEOUT
    ? Number(process.env.PLAYWRIGHT_NAV_TIMEOUT)
    : 30_000;
const actionTimeout = process.env.PLAYWRIGHT_ACTION_TIMEOUT
    ? Number(process.env.PLAYWRIGHT_ACTION_TIMEOUT)
    : 10_000;

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.ts',
    timeout: 60_000,
    expect: { timeout: actionTimeout },
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    // ALWAYS 1: multi-spec invocations otherwise get one worker per file,
    // and two browsers starve the single-process PHP backend (local php-cgi
    // AND the CI docker stack) — every test times out at goto. Single-file
    // runs were never affected (gate-2 lesson, 2026-06-11).
    workers: 1,
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
        navigationTimeout: navTimeout,
        actionTimeout: actionTimeout,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
        {
            // Branded Google Chrome via the system install (channel) — used for
            // local cross-browser coverage where Playwright's bundled firefox
            // cannot run (its build needs a VC++ runtime absent on no-admin
            // Windows boxes: "side-by-side configuration is incorrect").
            name: 'chrome',
            use: { ...devices['Desktop Chrome'], channel: 'chrome', viewport: { width: 1280, height: 900 } },
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
