import { test, expect, Page } from '@playwright/test';
import { personaCreds, login, assertAuthenticated } from './persona-helpers';

/**
 * Manager / Supervisor persona journeys (F-033 / matrix M2).
 *
 * Credentials: PLAYWRIGHT_MANAGER_USER / PLAYWRIGHT_MANAGER_PASS.
 * Skips cleanly when unset.
 *
 * M2 fixture: run local/sentientia_request/cli/seed_qa_pending_request.php
 * on the target instance BEFORE the suite. It seeds a pending course
 * request from qa_employee routed to qa_manager via open_supervisorid
 * (the WF-018 routing fix) and grants qa_manager the 'manager' role that
 * carries local/sentientia_request:approve.
 */
const creds = personaCreds('MANAGER');
const SKIP = 'set PLAYWRIGHT_MANAGER_USER / PLAYWRIGHT_MANAGER_PASS to run';

const DECIDE_APPROVE = '[data-action="decide-request"][data-decision="approved"]';

async function gotoSettled(page: Page, path: string): Promise<void> {
    try {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
    } catch (e) {
        if (!/ERR_ABORTED|frame was detached|interrupted by another navigation/i.test(String(e))) throw e;
        await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    }
}

test.describe('Manager / Supervisor', () => {
    test('login lands on a working /my/ dashboard', async ({ page }) => {
        test.setTimeout(240_000); // single-process local FCGI serializes PHP; the cold first login of a run can exceed 60s (CI is fast)
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await gotoSettled(page, '/my/');
        await assertAuthenticated(page);
    });

    test('approves a pending team request and the state flips', async ({ page }) => {
        test.setTimeout(240_000); // same local pacing as above
        test.skip(!creds.present, SKIP);
        await login(page, creds);
        await gotoSettled(page, '/local/sentientia_request/approvals.php');

        // The pending datatable is WS-rendered; the seeded row carries an
        // Approve button (decide AMD contract — build present since WF-019).
        const approveBtn = page.locator(DECIDE_APPROVE).first();
        await expect(approveBtn, 'seeded pending request visible in approvals inbox').toBeVisible({ timeout: 90_000 });
        const pendingBefore = await page.locator(DECIDE_APPROVE).count();

        await approveBtn.click();

        // SAVE_CANCEL modal: note is optional for approval — straight to Save.
        const saveBtn = page.locator('.modal [data-action="save"]').first();
        await expect(saveBtn, 'decide modal opened').toBeVisible({ timeout: 60_000 });

        // Save fires the decide WS, then the page self-reloads. Wait on the
        // WS response (not the racy self-reload), then navigate fresh.
        const decideResponse = page.waitForResponse(
            (r) => r.url().includes('local_sentientia_request_decide'),
            { timeout: 90_000 },
        );
        await saveBtn.click();
        const response = await decideResponse;
        expect(response.ok(), 'decide WS returned HTTP success').toBe(true);

        // State flip: the approvals inbox holds one fewer pending request
        // (usually zero — the fixture is the only seeded row).
        await gotoSettled(page, '/local/sentientia_request/approvals.php');
        await expect
            .poll(async () => page.locator(DECIDE_APPROVE).count(), {
                message: 'pending count drops after approval',
                timeout: 90_000,
            })
            .toBeLessThan(pendingBefore);
    });
});
