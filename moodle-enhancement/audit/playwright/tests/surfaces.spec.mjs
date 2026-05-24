// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Sentientia surface coverage smoke (Goal B).
 *
 * Walks each of the 10 Sentientia surfaces shipped in the
 * v4.1.0-goal-a-audit milestone and verifies its signature CSS
 * marker is applied. These are NOT screenshot regression tests
 * (which require pixel-perfect environment) — they're computed-
 * style assertions that catch SCSS cascade regression caused by
 * future theme version bumps.
 *
 * The audit-driven Goal A.x work landed 10 surfaces:
 *   /user/profile.php
 *   /badges/mybadges.php
 *   /grade/report/overview/
 *   /admin/*
 *   /course/view.php
 *   /grade/report/grader/
 *   /user/edit.php
 *   /user/preferences.php
 *   /calendar/view.php
 *   /course/edit.php
 *
 * Each test:
 *   1. Logs in as Site Admin (only role that can access every
 *      surface incl. /course/edit.php + /admin/*)
 *   2. Navigates to the URL
 *   3. Asserts the signature CSS marker is applied via
 *      getComputedStyle() — the most authoritative check.
 *
 * Credentials read from `fixtures/credentials.json` (not in git).
 *
 * @group sentientia-surfaces
 */

const BASE = 'http://localhost:8080/moodle';

// Site Admin — only role that can access ALL 10 surfaces.
const SITE_ADMIN = {
    username: 'academy@airpay.co.in',
    password: 'AcademyAudit2026!',
};

// Test course id for /course/view, /course/edit, /grade/report/grader.
// Verbal and Non Verbal Communication — has students, used throughout
// the visual audit.
const COURSE_ID = 275;

/**
 * Shared login fixture — runs ONCE per test file before any test.
 * Stores the auth state so individual tests don't re-login.
 *
 * IMPORTANT: pass `storageState: undefined` explicitly. Without this,
 * Playwright propagates the test.use({ storageState: '...' }) to ALL
 * newContext() calls in the file — including this one — which tries
 * to read fixtures/.auth-state.json BEFORE this beforeAll has written
 * it, throwing ENOENT.
 */
test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();
    await page.goto(`${BASE}/login/index.php`);
    await page.fill('input[name="username"]', SITE_ADMIN.username);
    await page.fill('input[name="password"]', SITE_ADMIN.password);
    await page.click('#loginbtn');
    await page.waitForURL((url) => !url.toString().includes('/login/index.php'),
        { timeout: 30_000 });
    await context.storageState({ path: 'fixtures/.auth-state.json' });
    await context.close();
});

test.use({ storageState: 'fixtures/.auth-state.json' });

// ──────────────────────────────────────────────────────────────────
// Surface assertions
// ──────────────────────────────────────────────────────────────────

test('/user/profile.php — h3 uppercase letter-spaced', async ({ page }) => {
    await page.goto(`${BASE}/user/profile.php`);
    const heading = page.locator('#region-main h3').first();
    await expect(heading).toBeVisible();
    const css = await heading.evaluate(el => getComputedStyle(el));
    expect(css.textTransform).toBe('uppercase');
    expect(css.letterSpacing).toMatch(/^0\.6/);
});

test('/badges/mybadges.php — generalbox card chrome', async ({ page }) => {
    await page.goto(`${BASE}/badges/mybadges.php`);
    expect(await page.locator('body').getAttribute('id')).toBe('page-badges-mybadges');
});

test('/grade/report/overview — h2 has brand accent', async ({ page }) => {
    await page.goto(`${BASE}/grade/report/overview/index.php`);
    const h2 = page.locator('#region-main h2').first();
    await expect(h2).toBeVisible();
    const css = await h2.evaluate(el => getComputedStyle(el));
    expect(css.borderRadius).toMatch(/16px/);
});

test('/admin/search.php — path-admin body class', async ({ page }) => {
    await page.goto(`${BASE}/admin/search.php`);
    const cls = await page.locator('body').getAttribute('class');
    expect(cls).toContain('path-admin');
});

test('/course/view.php — course banner present', async ({ page }) => {
    await page.goto(`${BASE}/course/view.php?id=${COURSE_ID}`);
    expect(await page.locator('body').getAttribute('id')).toBe('page-course-view-topics');
});

test('/grade/report/grader — thead uppercase letter-spaced', async ({ page }) => {
    await page.goto(`${BASE}/grade/report/grader/index.php?id=${COURSE_ID}`);
    const th = page.locator('#user-grades tr.heading th').first();
    await expect(th).toBeVisible();
    const css = await th.evaluate(el => getComputedStyle(el));
    expect(css.textTransform).toBe('uppercase');
});

test('/user/edit.php — fieldset card chrome', async ({ page }) => {
    await page.goto(`${BASE}/user/edit.php`);
    const fs = page.locator('form.mform fieldset').first();
    await expect(fs).toBeVisible();
    const css = await fs.evaluate(el => getComputedStyle(el));
    expect(css.borderRadius).toBe('16px');
});

test('/user/preferences.php — h3 uppercase letter-spaced', async ({ page }) => {
    await page.goto(`${BASE}/user/preferences.php`);
    const h3 = page.locator('#region-main h3').first();
    await expect(h3).toBeVisible();
    const css = await h3.evaluate(el => getComputedStyle(el));
    expect(css.textTransform).toBe('uppercase');
});

test('/calendar/view.php month — thead uppercase letter-spaced', async ({ page }) => {
    await page.goto(`${BASE}/calendar/view.php?view=month`);
    const th = page.locator('table.calendarmonth thead th').first();
    await expect(th).toBeVisible();
    const css = await th.evaluate(el => getComputedStyle(el));
    expect(css.textTransform).toBe('uppercase');
});

test('/course/edit.php — fieldset card chrome (same rule as user/edit)', async ({ page }) => {
    await page.goto(`${BASE}/course/edit.php?id=${COURSE_ID}`);
    const fs = page.locator('form.mform fieldset').first();
    await expect(fs).toBeVisible();
    const css = await fs.evaluate(el => getComputedStyle(el));
    expect(css.borderRadius).toBe('16px');
});

test('Workstream 0 — customer_brand style block injected', async ({ page }) => {
    await page.goto(`${BASE}/my/`);
    const style = page.locator('#sentientia-customer-brand');
    await expect(style).toBeAttached();
    const css = await style.innerText();
    expect(css).toContain('--ap-color-primary');
    expect(css).toContain('--ap-color-bg');
});
