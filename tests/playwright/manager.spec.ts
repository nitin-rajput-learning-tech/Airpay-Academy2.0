import { test } from '@playwright/test';
import { personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Manager / Supervisor persona journeys (F-033).
 *
 * Credentials: PLAYWRIGHT_MANAGER_USER / PLAYWRIGHT_MANAGER_PASS.
 * Skips cleanly when unset.
 */
const creds = personaCreds('MANAGER');
const SKIP = 'set PLAYWRIGHT_MANAGER_USER / PLAYWRIGHT_MANAGER_PASS to run';

test.describe('Manager / Supervisor', () => {
    test('login lands on a working /my/ dashboard', async ({ page }) => {
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await page.goto('/my/');
        await assertAuthenticated(page);
    });

    // Signature journey — approving a subordinate's request. Needs a
    // seeded pending request owned by a report of this manager (and, for
    // course-share, the commerce.crossTenantRequest.enabled flag ON), so
    // it is staged as a fixme until those fixtures exist.
    test('approves a pending team request and the state flips', async ({ page }) => {
        test.fixme(true, 'TODO: needs a seeded pending request owned by a direct report');
        // 1. login → the manager approvals surface (local_airpay_request
        //    inbox, or course-share manage_requests.php for Super Admin)
        // 2. locate a pending row → click Approve (with sesskey)
        // 3. assert the success notification + the row leaves "pending"
    });
});
