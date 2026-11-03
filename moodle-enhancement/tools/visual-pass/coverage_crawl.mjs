// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY, read-only coverage crawl (2026-11-02). It visits every URL that coverage_urls.php lists (one course
// per format, one activity per module type, every local_sentientia_* index.php) as one persona. It records the HTTP
// status, the evidence_lib scan problems (PHP message, debug dump, missing string, unrendered tag) and the console
// errors. Nothing is clicked or submitted.
//
//   php coverage_urls.php > <list>.json                  (cwd = moodle5/public)
//   node coverage_crawl.mjs --urls <list>.json [--persona vp_siteadmin] [--out <file>.json]
//
// Exit code 1 when any URL has a problem.
import fs from 'node:fs';
import { go, launch, opt, scan, session } from './evidence_lib.mjs';

const LIST = opt('--urls', '');
const PERSONA = opt('--persona', 'vp_siteadmin');
const OUT = opt('--out', '');
if (!LIST) {
  console.error('--urls <file> is required (php coverage_urls.php > file)');
  process.exit(2);
}
const urls = JSON.parse(fs.readFileSync(LIST, 'utf8'));
const rows = [];
const browser = await launch();
try {
  const { context, page } = await session(browser, PERSONA);
  for (const u of urls) {
    page.errs.length = 0;
    let status = 0;
    let problems = [];
    let final = '';
    try {
      const res = await go(page, u.url);
      status = res ? res.status() : 0;
      problems = (await scan(page)).problems;
      final = page.url().replace(/^https?:\/\/[^/]+/, '');
    } catch (e) {
      problems = ['navigation failed: ' + String(e.message).split('\n')[0].slice(0, 160)];
    }
    if (status >= 400) problems.unshift(`HTTP ${status}`);
    const row = { ...u, status, final, problems, console: [...page.errs] };
    rows.push(row);
    const bad = row.problems.length || row.console.length;
    console.log(`${bad ? 'FAIL' : 'ok  '}  ${u.kind.padEnd(6)} ${u.name.padEnd(34)} ${status} ${u.url}` +
      (bad ? `  ${JSON.stringify({ problems: row.problems, console: row.console.slice(0, 3) })}` : ''));
    if (OUT) fs.writeFileSync(OUT, JSON.stringify(rows, null, 2));
  }
  await context.close();
} finally {
  await browser.close();
}
const failed = rows.filter(r => r.problems.length || r.console.length).length;
console.log(`${rows.length - failed} ok, ${failed} with problems`);
process.exit(failed ? 1 : 0);
