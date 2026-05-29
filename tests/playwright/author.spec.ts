import { test } from '@playwright/test';
import { personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Course Author persona journeys (F-033).
 *
 * Credentials: PLAYWRIGHT_AUTHOR_USER / PLAYWRIGHT_AUTHOR_PASS.
 * Skips cleanly when unset.
 */
const creds = personaCreds('AUTHOR');
const SKIP = 'set PLAYWRIGHT_AUTHOR_USER / PLAYWRIGHT_AUTHOR_PASS to run';

test.describe('Course Author', () => {
    test('login lands on a working /my/ dashboard', async ({ page }) => {
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await page.goto('/my/');
        await assertAuthenticated(page);
    });

    // Signature journey — authoring a course. Mutates catalog state and
    // depends on the author having a category they can create in, so it
    // is staged as a fixme until a sandbox category + cleanup fixture
    // exist (otherwise repeated CI runs would litter the catalog).
    test('creates a course and adds an activity', async ({ page }) => {
        test.fixme(true, 'TODO: needs a sandbox category + post-run cleanup so CI does not litter the catalog');
        // 1. login → /course/management.php (or the author create-course entry)
        // 2. create a course in the sandbox category
        // 3. turn editing on → add an activity → assert it renders on /course/view.php
        // 4. (cleanup) delete the sandbox course
    });
});
