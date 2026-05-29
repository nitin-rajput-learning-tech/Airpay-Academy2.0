import { test } from '@playwright/test';
import { personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Compliance Officer / Auditor persona journeys (F-033).
 *
 * Credentials: PLAYWRIGHT_COMPLIANCE_USER / PLAYWRIGHT_COMPLIANCE_PASS.
 * Skips cleanly when unset.
 */
const creds = personaCreds('COMPLIANCE');
const SKIP = 'set PLAYWRIGHT_COMPLIANCE_USER / PLAYWRIGHT_COMPLIANCE_PASS to run';

test.describe('Compliance / Auditor', () => {
    test('login lands on a working /my/ dashboard', async ({ page }) => {
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await page.goto('/my/');
        await assertAuthenticated(page);
    });

    // Signature journey — reaching the Compliance area from the sidebar.
    // This is the regression guard for Goal A Bug #11 ("Compliance
    // Officer can't reach Compliance from sidebar"). Staged as a fixme
    // until the exact compliance landing URL/selector is pinned against
    // a run-to-green compliance account.
    test('reaches the compliance dashboard from the sidebar', async ({ page }) => {
        test.fixme(true, 'TODO: pin the compliance landing URL/selector (Bug #11 regression guard)');
        // 1. login → /my/
        // 2. open the sidebar → click the Compliance nav entry
        // 3. assert the compliance report/dashboard region renders (not nopermissions)
    });
});
