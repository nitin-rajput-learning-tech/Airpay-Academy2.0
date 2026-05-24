// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright runner config for Sentientia LMS E2E (Goal B).
 *
 * The existing .mjs scripts in this folder are standalone Node
 * scripts that import { chromium } from @playwright/test as a
 * library. This config wires the @playwright/test runner so
 * proper test()/expect() suites under tests/ can be discovered
 * and reported.
 *
 * Run locally:
 *   cd moodle-enhancement/audit/playwright
 *   npx playwright test
 *
 * Run a single suite:
 *   npx playwright test tests/surfaces.spec.mjs
 *
 * @see HARNESS_RUNBOOK.md for the broader testing strategy.
 */
export default defineConfig({
    testDir: './tests',
    testMatch: '*.spec.mjs',
    timeout: 60_000,                  // 60s per test — Moodle is slow on first hit
    expect: { timeout: 10_000 },
    fullyParallel: false,             // Sequential — they share a Moodle DB state
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,                       // Single worker — local XAMPP, no parallel safety
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    use: {
        baseURL: 'http://localhost:8080/moodle',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
    },
    projects: [
        // Firefox is the default — Chromium headless has a known
        // STATUS_HEAP_CORRUPTION crash on Node 24 + Windows 10
        // (exit code 3221225506 immediately on launch). Firefox
        // launches cleanly in the same env.
        // Run Chromium projects when on a CI runner or different
        // host where the crash doesn't reproduce.
        {
            name: 'firefox-desktop',
            use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 900 } },
        },
        {
            name: 'firefox-mobile-590',
            use: { ...devices['Desktop Firefox'], viewport: { width: 590, height: 800 } },
        },
        {
            name: 'chromium-desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
        },
        {
            name: 'chromium-mobile-590',
            use: { ...devices['Desktop Chrome'], viewport: { width: 590, height: 800 } },
        },
        // ── WDAC workaround projects (2026-05-24) ──────────────────────
        // Nitin's local machine has Windows Defender Application Control
        // blocking the Playwright-downloaded Firefox + Chromium binaries
        // at OS level (Access denied on direct invoke). The system-
        // installed Chrome IS signed and unblocked, so we can use
        // `channel: 'chrome'` to point Playwright at it instead.
        // Use these projects on a Nitin-machine local run; the standard
        // chromium-desktop project is correct for CI / non-WDAC hosts.
        {
            name: 'chrome-system-desktop',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 900 },
                channel: 'chrome',
            },
        },
        {
            name: 'chrome-system-mobile-590',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 590, height: 800 },
                channel: 'chrome',
            },
        },
    ],
});
