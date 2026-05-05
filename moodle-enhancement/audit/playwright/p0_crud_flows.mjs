// P0.2 — CRUD modal flow walk
//
// For each admin table that supports modal CRUD, exercise:
//   1. Click "Create" / "New X" button → modal opens
//   2. Fill form → submit
//   3. Verify the new row appears in the datatable
//   4. Click "Edit" on the new row → modal opens with populated values
//   5. Modify a field, submit → verify the row reflects the change
//   6. Click "Delete" → confirm modal → verify the row disappears
//
// Each plugin has slightly different field names, so config-driven.
// Failure of any step is logged but the harness continues to the next
// plugin so we get a full picture.
//
// Output:
//   /tmp/airpay_p0/screenshots/crud_<plugin>_<step>.png — for failed steps
//   /tmp/airpay_p0/crud_report.json
//
// Usage:
//   node moodle-enhancement/audit/playwright/p0_crud_flows.mjs

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const USERNAME  = 'academy@airpay.co.in';
const PASSWORD  = 'Airpay@Test2026!';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');

// Per-plugin test config. Each entry exercises a smoke create/edit/delete
// against the plugin's modal flow.
const FLOWS = [
    {
        id: 'reports',
        url: '/local/airpay_reports/index.php',
        createButton: '[data-action="create-report"]',
        // Modal form fields. The unique value is timestamped to avoid
        // colliding with prior runs.
        fields: { name: 'CRUD-test ${ts}', description: 'phpunit fixture' },
        // After submit, row appears in the datatable. Match by a unique
        // attribute on the rendered row (the datatable uses
        // data-row-id).
        rowSelector: 'tr[data-row-id]',
        editButtonInRow: '[data-action="edit-report"]',
        deleteButtonInRow: '[data-action="delete-report"]',
    },
    {
        id: 'classroom',
        url: '/local/airpay_classroom/index.php',
        createButton: '[data-action="create-classroom"]',
        fields: { name: 'CRUD-test classroom ${ts}', location: 'Audit Lab', capacity: '25' },
        rowSelector: 'tr[data-row-id]',
        editButtonInRow: '[data-action="edit-classroom"]',
        deleteButtonInRow: '[data-action="delete-classroom"]',
    },
    {
        id: 'evaluations',
        url: '/local/airpay_evaluation/index.php',
        createButton: '[data-action="create-evaluation"]',
        fields: { name: 'CRUD-test evaluation ${ts}' },
        rowSelector: 'tr[data-row-id]',
        editButtonInRow: '[data-action="edit-evaluation"]',
        deleteButtonInRow: '[data-action="delete-evaluation"]',
    },
    {
        id: 'notifications',
        url: '/local/airpay_notifications/index.php',
        createButton: '[data-action="create-rule"]',
        fields: { name: 'CRUD-test rule ${ts}' },
        rowSelector: 'tr[data-row-id]',
        editButtonInRow: '[data-action="edit-rule"]',
        deleteButtonInRow: '[data-action="delete-rule"]',
    },
];

async function login(page) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    // Promise.all so the click's auto-wait-for-navigation doesn't time
    // out before the slow XAMPP server returns the redirect.
    await Promise.all([
        page.waitForURL(/\/my\//, { timeout: 120_000 }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function waitForDatatableLoaded(page) {
    // Datatable starts with tbody innerHTML = "Loading…". Wait until it
    // either populates with rows OR shows "No records found" — both mean
    // the first AJAX completed and the action handlers are bound.
    await page.waitForFunction(() => {
        const body = document.querySelector('[data-airpay-table-body]');
        return body && !body.textContent.includes('Loading…');
    }, { timeout: 30_000 });
}

async function findModal(page) {
    // Wait for the modal shell …
    await page.locator('.modal.show').waitFor({ state: 'visible', timeout: 15_000 });
    const modal = page.locator('.modal.show').last();
    // … and then for the form INSIDE to load. Moodle's modalform fetches
    // the form HTML async, so .modal.show appears before the inputs do.
    try {
        await modal.locator('input, textarea, select').first()
            .waitFor({ state: 'visible', timeout: 15_000 });
    } catch (e) {
        // Sometimes modal contains static markup, not a form (confirm
        // dialog etc.). Don't fail the harness if no input shows.
    }
    return modal;
}

async function fillModalField(modal, fieldName, value) {
    // Try several selectors — Moodle's mform builds varied input types.
    const selectors = [
        `input[name="${fieldName}"]`,
        `textarea[name="${fieldName}"]`,
        `select[name="${fieldName}"]`,
    ];
    for (const sel of selectors) {
        const loc = modal.locator(sel).first();
        if (await loc.count() > 0) {
            const tag = await loc.evaluate(e => e.tagName.toLowerCase());
            if (tag === 'select') {
                await loc.selectOption({ index: 1 }); // pick first non-default option
            } else {
                await loc.fill(value);
            }
            return true;
        }
    }
    return false;
}

async function shoot(page, name) {
    await page.screenshot({ path: path.join(SHOTS_DIR, `crud_${name}.png`) });
}

async function runFlow(context, flow, report) {
    const ts = Date.now();
    const log = (m) => { console.log(`    ${m}`); report.steps.push({ flow: flow.id, msg: m }); };
    const fail = (step, msg) => {
        const m = `FAIL ${flow.id} @ ${step}: ${msg}`;
        console.log(`    ${m}`);
        report.failures.push({ flow: flow.id, step, msg });
    };

    const page = await context.newPage();
    const consoleErrs = [];
    page.on('pageerror', err => consoleErrs.push(err.message));
    page.on('console', msg => { if (msg.type() === 'error') consoleErrs.push(msg.text()); });

    try {
        console.log(`\n  ── Plugin: ${flow.id} ──`);
        await page.goto(`${BASE}${flow.url}`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
        log(`page loaded`);

        // Wait for the datatable's first AJAX to complete before counting
        // rows or clicking action buttons — otherwise the click handlers
        // aren't bound yet (require() chain still resolving).
        try {
            await waitForDatatableLoaded(page);
            log(`datatable loaded`);
        } catch (e) {
            log(`  WARN datatable did not finish loading in 30s`);
        }

        // 1. CREATE
        const initialRows = await page.locator(flow.rowSelector).count();
        log(`initial rows = ${initialRows}`);

        const createBtn = page.locator(flow.createButton).first();
        if (await createBtn.count() === 0) {
            fail('locate-create-button', `selector ${flow.createButton} not found`);
            await shoot(page, `${flow.id}_no_create_btn`);
            await page.close(); return;
        }
        await createBtn.click();
        try {
            const modal = await findModal(page);
            log(`create modal opened`);

            for (const [field, val] of Object.entries(flow.fields)) {
                const filled = await fillModalField(modal, field, val.replace('${ts}', ts));
                if (!filled) {
                    log(`  WARN field '${field}' not found in modal`);
                }
            }

            // Submit. Moodle modalform typically has a Submit button.
            const submitSelectors = [
                'button[type="submit"]',
                'input[type="submit"]',
                'button:has-text("Save")',
                'button:has-text("Create")',
            ];
            let submitted = false;
            for (const sel of submitSelectors) {
                const btn = modal.locator(sel).first();
                if (await btn.count() > 0 && await btn.isVisible()) {
                    await btn.click();
                    submitted = true;
                    break;
                }
            }
            if (!submitted) {
                fail('submit-create', 'no submit button found in modal');
                await shoot(page, `${flow.id}_no_submit`);
            } else {
                // Modal closes + page reloads after CRUD action.
                await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
                log(`create submitted`);
            }
        } catch (e) {
            fail('open-create-modal', e.message);
            await shoot(page, `${flow.id}_modal_fail`);
        }

        // 2. Verify row count increased.
        const afterCreate = await page.locator(flow.rowSelector).count();
        if (afterCreate <= initialRows) {
            fail('row-count-after-create', `expected > ${initialRows}, got ${afterCreate}`);
        } else {
            log(`row count after create = ${afterCreate}  ✓`);
        }

        // 3. EDIT — find the new row by name match.
        const targetName = flow.fields.name.replace('${ts}', ts);
        const targetRow = page.locator(`${flow.rowSelector}:has-text("${targetName}")`).first();
        if (await targetRow.count() === 0) {
            fail('locate-new-row', `row with text "${targetName}" not found`);
        } else {
            const editBtn = targetRow.locator(flow.editButtonInRow).first();
            if (await editBtn.count() === 0) {
                fail('locate-edit-button', `${flow.editButtonInRow} not in target row`);
            } else {
                await editBtn.click();
                try {
                    const modal = await findModal(page);
                    log(`edit modal opened`);
                    // Append " (edited)" to name.
                    const nameInput = modal.locator('input[name="name"]').first();
                    if (await nameInput.count() > 0) {
                        const current = await nameInput.inputValue();
                        await nameInput.fill(current + ' (edited)');
                    }
                    const submitBtn = modal.locator('button[type="submit"], input[type="submit"]').first();
                    await submitBtn.click();
                    await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
                    log(`edit submitted`);
                } catch (e) {
                    fail('edit-modal', e.message);
                    await shoot(page, `${flow.id}_edit_fail`);
                }
            }

            // 4. Verify the edited row exists with new label.
            const editedRow = page.locator(`${flow.rowSelector}:has-text("${targetName} (edited)")`).first();
            if (await editedRow.count() === 0) {
                fail('verify-edited-row', `row "${targetName} (edited)" not visible after edit`);
            } else {
                log(`edited row visible  ✓`);

                // 5. DELETE — confirm via core/notification dialog.
                const deleteBtn = editedRow.locator(flow.deleteButtonInRow).first();
                if (await deleteBtn.count() === 0) {
                    fail('locate-delete-button', `${flow.deleteButtonInRow} not in row`);
                } else {
                    await deleteBtn.click();
                    try {
                        // Moodle's deleteCancelPromise pops a confirm dialog.
                        const confirmBtn = page.locator('.modal.show button:has-text("Delete"), .modal.show button:has-text("Yes")').first();
                        await confirmBtn.waitFor({ state: 'visible', timeout: 10_000 });
                        await confirmBtn.click();
                        await page.waitForLoadState('domcontentloaded', { timeout: 60_000 });
                        log(`delete confirmed`);
                    } catch (e) {
                        fail('delete-confirm', e.message);
                        await shoot(page, `${flow.id}_delete_fail`);
                    }

                    // Verify row gone.
                    const stillThere = await page.locator(`${flow.rowSelector}:has-text("${targetName}")`).count();
                    if (stillThere > 0) {
                        fail('verify-deleted', `row still present after delete`);
                    } else {
                        log(`row deleted  ✓`);
                    }
                }
            }
        }

        if (consoleErrs.length) {
            log(`WARN ${consoleErrs.length} console errors during flow`);
            report.consoleErrors.push({ flow: flow.id, errors: consoleErrs });
        }

    } catch (e) {
        fail('flow-exception', e.message);
    } finally {
        await page.close();
    }
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { steps: [], failures: [], consoleErrors: [] };

    // HEADED mode (visible Chrome window) so you can watch the CRUD
    // flow click through. Set HEADLESS=1 in env to run invisibly.
    const headless = process.env.HEADLESS === '1';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        slowMo: headless ? 0 : 250,  // 250ms between actions in headed mode for readability
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
               '--disable-extensions', '--disable-plugins'],
    });
    const context = await browser.newContext();
    context.setDefaultTimeout(90_000);
    context.setDefaultNavigationTimeout(120_000);

    const loginPage = await context.newPage();
    await login(loginPage);
    await loginPage.close();

    for (const flow of FLOWS) {
        await runFlow(context, flow, report);
    }

    await fs.writeFile(
        path.join(OUT_DIR, 'crud_report.json'),
        JSON.stringify(report, null, 2)
    );

    console.log();
    console.log('═══════════════════════════════════════════════════════════════════');
    console.log(`CRUD walk: ${FLOWS.length} flows tested`);
    console.log(`  failures: ${report.failures.length}`);
    console.log(`  console-error flows: ${report.consoleErrors.length}`);
    if (report.failures.length > 0) {
        console.log();
        for (const f of report.failures) {
            console.log(`  - ${f.flow} @ ${f.step}: ${f.msg}`);
        }
    }
    console.log(`Report: ${OUT_DIR}/crud_report.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await context.close();
    await browser.close();
    process.exit(report.failures.length > 0 ? 1 : 0);
}

main().catch(e => {
    console.error('FATAL:', e);
    process.exit(2);
});
