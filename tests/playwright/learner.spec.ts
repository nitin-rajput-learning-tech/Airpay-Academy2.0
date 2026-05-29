import { test, expect } from '@playwright/test';
import { personaCreds, login, assertAuthenticated, assertReachable } from './persona-helpers';

/**
 * Learner persona journeys (F-033).
 *
 * Credentials: PLAYWRIGHT_LEARNER_USER / PLAYWRIGHT_LEARNER_PASS.
 * Each test skips cleanly when they are unset, so the gate stays green
 * until a learner test account is provisioned.
 */
const creds = personaCreds('LEARNER');
const SKIP = 'set PLAYWRIGHT_LEARNER_USER / PLAYWRIGHT_LEARNER_PASS to run';

test.describe('Learner', () => {
    test('login lands on a working /my/ dashboard', async ({ page }) => {
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await page.goto('/my/');
        await assertAuthenticated(page);
    });

    test('can reach the course catalog with course cards', async ({ page }) => {
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await assertReachable(page, '/local/airpay_catalog/index.php');
        const courseLink = page
            .locator('a[href*="/local/airpay_catalog/course.php"], a[href*="/course/view.php"]')
            .first();
        await expect(courseLink).toBeVisible();
    });

    // Deeper mutating journey — needs run-to-green against a seeded free
    // course + a clean (un-enrolled) learner account, so it is staged as
    // a fixme until those fixtures exist.
    test('enrols in a free course and sees it on /my/', async ({ page }) => {
        test.fixme(true, 'TODO: needs a seeded free course + un-enrolled learner fixture');
        // 1. login → /local/airpay_catalog/index.php
        // 2. open a known free course → click Enrol
        // 3. confirm enrolment → land on /course/view.php
        // 4. goto /my/ → assert the course appears in the course list
    });
});
