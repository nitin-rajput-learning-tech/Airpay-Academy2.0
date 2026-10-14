// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: the GST tax invoice / receipt evidence pass (spec GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md,
// section 11 "Afterwards"), on the orders seed_gst_evidence.php creates, with both cart flags ON for tenant /77.
//
//   node gst_checks.mjs [--phase main|off] [--out <dir>]
//
//   main  buyer vp_public77: checkout state field and its GSTIN/state refusal, receipts, order history Documents
//         links, invoices (B2C intra-state, B2C inter-state), credit notes, PDFs; the B2B invoice as the buyer sees
//         it while it awaits the IRN; then site admin vp_siteadmin on einvoice.php (before, form filled, after the
//         invoice, after the credit note) recording a TEST IRN through the real form; then the buyer again for the
//         B2B receipt, history, invoice with IRN + QR and credit note, and their PDFs.
//   off   run AFTER gst_evidence_flags.php off cart: what the same pages do with both flags OFF (default behaviour).
//
// Never submits a payment: the only checkout POST is one the server refuses before an order number is reserved.

import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { BASE, HERE, go, has, launch, opt, readJson, scan, session, shots } from './evidence_lib.mjs';

const CTX = readJson(path.join(HERE, '.gst-evidence.local.json'));
if (!CTX) {
  console.error('.gst-evidence.local.json missing - run seed_gst_evidence.php first.');
  process.exit(2);
}
const PHASE = opt('--phase', 'main');
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-10-14/gst')));
const PDFDIR = path.join(OUT, 'pdf');
fs.mkdirSync(PDFDIR, { recursive: true });
const RESULTS = path.join(OUT, PHASE === 'main' ? 'results.json' : `results-${PHASE}.json`);
const results = [];
const flush = () => fs.writeFileSync(RESULTS, JSON.stringify({ generated: new Date().toISOString(), base: BASE,
  tenant: CTX.tenant, results }, null, 1));

const O = CTX.orders;
const P = '/local/sentientia_cart/';

// One page: navigate, scan for defects, screenshot at 1440 and 590, record.
async function view(page, name, url, persona, shows, opts = {}) {
  const res = await go(page, url);
  if (opts.after) await opts.after(page);
  const s = await scan(page);
  const files = await shots(page, OUT, name, opts);
  const r = { name, url, persona, shows, http: res ? res.status() : null, files,
    problems: s.problems, overflow: files.overflow, console: page.errs.splice(0), textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 300) };
  results.push(r);
  flush();
  console.log(`${name}: http ${r.http}${s.problems.length ? ' PROBLEMS ' + s.problems.join('; ') : ''}${r.console.length ? ' console ' + r.console.length : ''}`);
  return { s, r };
}

// A PDF through the logged-in context's cookies. Saved as pdf/<file>.
async function pdf(page, url, file, persona, shows) {
  const res = await page.context().request.get(BASE + url, { timeout: 240000 });
  const body = await res.body();
  const ok = res.status() === 200 && /pdf/i.test(res.headers()['content-type'] || '') && body.slice(0, 4).toString() === '%PDF';
  if (ok) fs.writeFileSync(path.join(PDFDIR, file), body);
  results.push({ name: file, url, persona, shows, http: res.status(), pdf: ok, bytes: body.length,
    contentType: res.headers()['content-type'] || '' });
  flush();
  console.log(`${file}: http ${res.status()} ${ok ? body.length + ' bytes' : 'NOT A PDF'}`);
}

const browser = await launch();
try {
  if (PHASE === 'main') {
    // ---------------------------------------------------------------- buyer, before the IRN is recorded
    let { context: bctx, page: buyer } = await session(browser, 'vp_public77');

    await view(buyer, '01-checkout-state-field', P + 'checkout.php', 'vp_public77 (buyer, /77)',
      'Checkout with sentientia.cart.gst_tax_invoices ON: the required "State" select (place of supply) between the address and the GSTIN');
    // A refusal the server makes before an order number is reserved: no order, no gateway call.
    await go(buyer, P + 'checkout.php');
    await buyer.selectOption('#billing_state', '29');
    await buyer.fill('#billing_gstn', '27BBBBB1111B1Z8');
    await buyer.click('form[action*="checkout.php"] button[type="submit"]', { noWaitAfter: true });
    await buyer.waitForFunction(() => /GST|state/i.test(document.body.innerText) && document.querySelector('.alert-danger, .notifyproblem, [role="alert"]'),
      null, { timeout: 120000 }).catch(() => {});
    await buyer.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    {
      const s = await scan(buyer);
      const files = await shots(buyer, OUT, '02-checkout-state-gstin-mismatch');
      results.push({ name: '02-checkout-state-gstin-mismatch', url: P + 'checkout.php (POST, refused)', persona: 'vp_public77 (buyer, /77)',
        shows: 'State = Karnataka (29) with a Maharashtra GSTIN (27...): checkout refuses, shows why, keeps what was typed; no order is created',
        http: 200, files, problems: s.problems, overflow: files.overflow, console: buyer.errs.splice(0),
        textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 400) });
      flush();
    }

    await view(buyer, '03-order-history-documents-before-irn', P + 'history.php', 'vp_public77 (buyer, /77)',
      'Order history with the Documents column: Receipt for each paid order, Tax invoice and Credit note links for the issued ones; the B2B order has no Tax invoice link while it awaits the IRN',
      { after: p => p.waitForFunction(() => /Receipt/.test(document.body.innerText), null, { timeout: 120000 }).catch(() => {}) });

    await view(buyer, '04-receipt-b2c-intra', P + `receipt.php?id=${O.b2c_intra.historyid}`, 'vp_public77 (buyer, /77)',
      `Purchase receipt of order #${O.b2c_intra.orderid} (B2C, Maharashtra, two lines, partial refund shown, link to the tax invoice)`);
    await view(buyer, '05-receipt-b2c-inter', P + `receipt.php?id=${O.b2c_inter.historyid}`, 'vp_public77 (buyer, /77)',
      `Purchase receipt of order #${O.b2c_inter.orderid} (B2C, Karnataka)`);
    await view(buyer, '06-receipt-b2b-awaiting-irn', P + `receipt.php?id=${O.b2b.historyid}`, 'vp_public77 (buyer, /77)',
      `Purchase receipt of the B2B order #${O.b2b.orderid} while the invoice awaits the IRN: "Your GST tax invoice will follow", no invoice link`);
    await view(buyer, '07-invoice-b2b-awaiting-as-buyer', P + `invoice.php?id=${O.b2b.invoice.id}`, 'vp_public77 (buyer, /77)',
      'What the buyer gets when they open the awaiting-IRN B2B invoice by its URL (spec: the buyer does not see such an invoice)');

    await view(buyer, '08-invoice-b2c-intra-state', P + `invoice.php?id=${O.b2c_intra.invoice.id}`, 'vp_public77 (buyer, /77)',
      `Tax invoice ${O.b2c_intra.invoice.number}: B2C intra-state (Maharashtra to Maharashtra), CGST + SGST, odd paisa to SGST`);
    await view(buyer, '09-invoice-b2c-inter-state', P + `invoice.php?id=${O.b2c_inter.invoice.id}`, 'vp_public77 (buyer, /77)',
      `Tax invoice ${O.b2c_inter.invoice.number}: B2C inter-state (Maharashtra to Karnataka), IGST`);
    await view(buyer, '10-creditnote-b2c-intra-state', P + `creditnote.php?id=${O.b2c_intra.creditnotes[0].id}`, 'vp_public77 (buyer, /77)',
      `Credit note ${O.b2c_intra.creditnotes[0].number} for a partial refund of the intra-state invoice (CGST + SGST), referencing the invoice`);
    await view(buyer, '11-creditnote-b2c-inter-state', P + `creditnote.php?id=${O.b2c_inter.creditnotes[0].id}`, 'vp_public77 (buyer, /77)',
      `Credit note ${O.b2c_inter.creditnotes[0].number} for a partial refund of the inter-state invoice (IGST)`);

    await pdf(buyer, P + `invoice.php?id=${O.b2c_intra.invoice.id}&download=pdf`, `invoice-${O.b2c_intra.invoice.number}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the B2C intra-state tax invoice');
    await pdf(buyer, P + `receipt.php?id=${O.b2c_intra.historyid}&download=pdf`, `receipt-order-${O.b2c_intra.orderid}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the purchase receipt of the B2C intra-state order');
    await pdf(buyer, P + `creditnote.php?id=${O.b2c_intra.creditnotes[0].id}&download=pdf`, `creditnote-${O.b2c_intra.creditnotes[0].number}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the credit note of the B2C intra-state invoice');

    // ---------------------------------------------------------------- site admin: record the test IRN
    const { context: actx, page: admin } = await session(browser, 'vp_siteadmin');
    await view(admin, '12-einvoice-before-recording', P + 'einvoice.php', 'vp_siteadmin (site admin)',
      'E-invoice (IRN) page: the B2B tax invoice and the B2B credit note that wait for the IRN, one form each');

    // Wall-clock time in India, an hour ago (the date may not be in the future).
    const ackdate = new Date(Date.now() + 5.5 * 3600e3 - 3600e3).toISOString().slice(0, 16);
    const irn = id => crypto.createHash('sha256').update('VP-LOCAL-TEST-IRN-' + id).digest('hex');
    const qr = id => `eyJ0ZXN0IjoiTE9DQUwtRVZJRENFLU9OTFktTk9ULUEtUkVBTC1JUlAtUVIifQ.TESTONLY${id}.NOT-GOVERNMENT-SIGNED`;
    const fill = async (type, id, ackno) => {
      await admin.fill(`#irn_${type}_${id}`, irn(`${type}-${id}`));
      await admin.fill(`#ack_no_${type}_${id}`, ackno);
      await admin.fill(`#ack_date_${type}_${id}`, ackdate);
      await admin.fill(`#signed_qr_${type}_${id}`, qr(`${type}${id}`));
    };
    const invId = O.b2b.invoice.id;
    const cnId = O.b2b.creditnotes[0].id;
    await fill('invoice', invId, '112610000000101');
    await fill('creditnote', cnId, '112610000000102');
    {
      const files = await shots(admin, OUT, '13-einvoice-form-filled');
      results.push({ name: '13-einvoice-form-filled', url: P + 'einvoice.php', persona: 'vp_siteadmin (site admin)',
        shows: 'The same page with the IRN, acknowledgement number, date and signed QR typed into both forms (TEST values: the IRN is a SHA-256 of a label, the QR is a dummy string; nothing came from a government portal)',
        http: 200, files, problems: [], overflow: files.overflow, console: admin.errs.splice(0) });
      flush();
    }
    // Record the invoice's IRN through the real form.
    await admin.click(`[data-region="einvoice-row"][data-doc-type="invoice"][data-doc-id="${invId}"] button[type="submit"]`, { noWaitAfter: true });
    await admin.waitForFunction(() => /recorded|IRN/i.test(document.body.innerText) && document.querySelector('.alert-success, .notifysuccess'),
      null, { timeout: 180000 }).catch(() => {});
    await admin.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    {
      const s = await scan(admin);
      const files = await shots(admin, OUT, '14-einvoice-after-invoice-recorded');
      results.push({ name: '14-einvoice-after-invoice-recorded', url: P + 'einvoice.php', persona: 'vp_siteadmin (site admin)',
        shows: 'After recording the invoice IRN: success message, the invoice is gone from the list, the credit note still waits',
        http: 200, files, problems: s.problems, overflow: files.overflow, console: admin.errs.splice(0),
        textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 300) });
      flush();
    }
    // The credit note's form was reset by the redirect: fill it again and record it.
    await admin.fill(`#irn_creditnote_${cnId}`, irn(`creditnote-${cnId}`));
    await admin.fill(`#ack_no_creditnote_${cnId}`, '112610000000102');
    await admin.fill(`#ack_date_creditnote_${cnId}`, ackdate);
    await admin.fill(`#signed_qr_creditnote_${cnId}`, qr(`creditnote${cnId}`));
    await admin.click(`[data-region="einvoice-row"][data-doc-type="creditnote"][data-doc-id="${cnId}"] button[type="submit"]`, { noWaitAfter: true });
    await admin.waitForFunction(() => /recorded/i.test(document.body.innerText) && document.querySelector('.alert-success, .notifysuccess'),
      null, { timeout: 180000 }).catch(() => {});
    await admin.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    {
      const s = await scan(admin);
      const files = await shots(admin, OUT, '15-einvoice-after-all-recorded');
      results.push({ name: '15-einvoice-after-all-recorded', url: P + 'einvoice.php', persona: 'vp_siteadmin (site admin)',
        shows: 'After recording the credit note IRN as well: nothing awaits an IRN (empty state)',
        http: 200, files, problems: s.problems, overflow: files.overflow, console: admin.errs.splice(0),
        textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 300) });
      flush();
    }
    await actx.close();

    // ---------------------------------------------------------------- buyer again: the B2B documents with the IRN
    await view(buyer, '16-order-history-documents-after-irn', P + 'history.php', 'vp_public77 (buyer, /77)',
      'Order history after the IRNs were recorded: the B2B order now has Receipt, Tax invoice and Credit note links',
      { after: p => p.waitForFunction(() => /Receipt/.test(document.body.innerText), null, { timeout: 120000 }).catch(() => {}) });
    await view(buyer, '17-receipt-b2b-after-irn', P + `receipt.php?id=${O.b2b.historyid}`, 'vp_public77 (buyer, /77)',
      `Receipt of the B2B order #${O.b2b.orderid} after the IRN: link to the tax invoice instead of "will follow"`);
    await view(buyer, '18-invoice-b2b-irn-qr', P + `invoice.php?id=${invId}`, 'vp_public77 (buyer, /77)',
      `Tax invoice ${O.b2b.invoice.number}: B2B, buyer GSTIN, 10% line discount, IRN, acknowledgement number and date, QR (dummy test QR)`);
    await view(buyer, '19-creditnote-b2b-irn-qr', P + `creditnote.php?id=${cnId}`, 'vp_public77 (buyer, /77)',
      `Credit note ${O.b2b.creditnotes[0].number} of the B2B invoice, with its own IRN and QR`);
    await pdf(buyer, P + `invoice.php?id=${invId}&download=pdf`, `invoice-${O.b2b.invoice.number}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the B2B tax invoice with IRN and QR');
    await pdf(buyer, P + `creditnote.php?id=${cnId}&download=pdf`, `creditnote-${O.b2b.creditnotes[0].number}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the B2B credit note with IRN and QR');
    await pdf(buyer, P + `invoice.php?id=${O.b2c_inter.invoice.id}&download=pdf`, `invoice-${O.b2c_inter.invoice.number}.pdf`,
      'vp_public77 (buyer, /77)', 'PDF of the B2C inter-state tax invoice (IGST)');
    await bctx.close();
  } else if (PHASE === 'off-admin') {
    // The admin step of the OFF phase, for a run that lost its connection before it.
    const { context: actx, page: admin } = await session(browser, 'vp_siteadmin');
    await view(admin, 'off-05-einvoice-refused', P + 'einvoice.php', 'vp_siteadmin (site admin)',
      'Both flags OFF: the e-invoice page is refused even for a site admin');
    await actx.close();
  } else if (PHASE === 'off') {
    // Both flags OFF for tenant /77 (run gst_evidence_flags.php off cart first).
    const { context: bctx, page: buyer } = await session(browser, 'vp_public77');
    await view(buyer, 'off-01-checkout-no-state-field', P + 'checkout.php', 'vp_public77 (buyer, /77)',
      'Both flags OFF: checkout is what it always was, no State select');
    await view(buyer, 'off-02-order-history-no-documents', P + 'history.php', 'vp_public77 (buyer, /77)',
      'Both flags OFF: order history has no Documents column',
      { after: p => p.waitForFunction(() => /Placed|No orders/i.test(document.body.innerText), null, { timeout: 120000 }).catch(() => {}) });
    await view(buyer, 'off-03-receipt-refused', P + `receipt.php?id=${O.b2c_intra.historyid}`, 'vp_public77 (buyer, /77)',
      'Both flags OFF: the receipt page is not available');
    await view(buyer, 'off-04-invoice-still-viewable', P + `invoice.php?id=${O.b2c_intra.invoice.id}`, 'vp_public77 (buyer, /77)',
      'Both flags OFF: an invoice that was already issued stays viewable (spec section 2)');
    await bctx.close();
    const { context: actx, page: admin } = await session(browser, 'vp_siteadmin');
    await view(admin, 'off-05-einvoice-refused', P + 'einvoice.php', 'vp_siteadmin (site admin)',
      'Both flags OFF: the e-invoice page is refused even for a site admin');
    await actx.close();
  } else {
    console.error('unknown --phase');
    process.exit(2);
  }
} finally {
  await browser.close();
  flush();
}
