// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: re-capture of the four GST pages changed by the 2026-10-14 polish fix (after gst_checks.mjs found them):
// the checkout State select (custom-select, the Bootstrap 4 class), the receipt naming the payment method instead of
// the gateway key, the receipt PDF (Refunds heading on its own line, the tax invoice labelled, the credit notes
// listed), and einvoice.php (one title, no Site administration tabs). Run with both cart flags ON for tenant /77
// (php gst_evidence_flags.php on cart 77) and switch them OFF afterwards. New files are prefixed p-; the originals
// stay as the record of what the first pass found.
//
//   node gst_polish_checks.mjs [--out <dir>]

import fs from 'node:fs';
import path from 'node:path';
import { BASE, HERE, go, launch, opt, readJson, scan, session, shots } from './evidence_lib.mjs';

const CTX = readJson(path.join(HERE, '.gst-evidence.local.json'));
if (!CTX) {
  console.error('.gst-evidence.local.json missing - run seed_gst_evidence.php first.');
  process.exit(2);
}
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-10-14/gst')));
const PDFDIR = path.join(OUT, 'pdf');
fs.mkdirSync(PDFDIR, { recursive: true });
const RESULTS = path.join(OUT, 'results-polish.json');
const results = [];
const flush = () => fs.writeFileSync(RESULTS, JSON.stringify({ generated: new Date().toISOString(), base: BASE,
  tenant: CTX.tenant, results }, null, 1));
const O = CTX.orders;
const P = '/local/sentientia_cart/';

async function view(page, name, url, persona, shows) {
  const res = await go(page, url);
  const s = await scan(page);
  const files = await shots(page, OUT, name, {});
  results.push({ name, url, persona, shows, http: res ? res.status() : null, files, problems: s.problems,
    console: page.errs.splice(0), textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 300) });
  flush();
  console.log(`${name}: http ${res ? res.status() : '-'}${s.problems.length ? ' PROBLEMS ' + s.problems.join('; ') : ''}`);
}

async function pdf(page, url, file, persona, shows) {
  const res = await page.context().request.get(BASE + url, { timeout: 240000 });
  const body = await res.body();
  const ok = res.status() === 200 && body.slice(0, 4).toString() === '%PDF';
  if (ok) fs.writeFileSync(path.join(PDFDIR, file), body);
  results.push({ name: file, url, persona, shows, http: res.status(), pdf: ok, bytes: body.length });
  flush();
  console.log(`${file}: http ${res.status()} ${ok ? body.length + ' bytes' : 'NOT A PDF'}`);
}

const browser = await launch();
try {
  const { page: buyer } = await session(browser, 'vp_public77');
  await view(buyer, 'p-01-checkout-state-field', P + 'checkout.php', 'vp_public77 (buyer, /77)',
    'The State select styled as the theme styles a select (custom-select)');
  await view(buyer, 'p-04-receipt-b2c-intra', P + `receipt.php?id=${O.b2c_intra.historyid}`, 'vp_public77 (buyer, /77)',
    'The payment method reads "Airpay", not the gateway key "airpay"');
  await pdf(buyer, P + `receipt.php?id=${O.b2c_intra.historyid}&download=pdf`, `p-receipt-order-${O.b2c_intra.orderid}.pdf`,
    'vp_public77 (buyer, /77)', 'Receipt PDF: Refunds heading above its table, "Tax invoice: <number>", the credit notes listed');
  const { page: admin } = await session(browser, 'vp_siteadmin');
  await view(admin, 'p-12-einvoice-page', P + 'einvoice.php', 'vp_siteadmin (site admin)',
    'One page title, no Site administration tab strip');
} finally {
  await browser.close();
}
