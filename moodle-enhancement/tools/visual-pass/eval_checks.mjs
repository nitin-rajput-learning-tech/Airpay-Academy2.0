// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// LOCAL-ONLY: the EV-18 evidence of /local/sentientia_evaluation/my_evaluations.php (learner history), on the history
// seed_eval_evidence.php gives vp_eval77 (tenant /77), with sentientia.evaluation.learner_history ON for that tenant.
//
//   node eval_checks.mjs [--phase on|off] [--out <dir>]
//
//   on   the page in English and in Hindi (?lang=hi), desktop and 590 px
//   off  run AFTER gst_evidence_flags.php off evaluation: the page answers "not available"

import fs from 'node:fs';
import path from 'node:path';
import { BASE, HERE, go, launch, opt, scan, session, shots } from './evidence_lib.mjs';

const PHASE = opt('--phase', 'on');
const OUT = path.resolve(opt('--out', path.join(HERE, '../../docs/visual-evidence/2026-10-14/ev18')));
fs.mkdirSync(OUT, { recursive: true });
const RESULTS = path.join(OUT, PHASE === 'on' ? 'results.json' : `results-${PHASE}.json`);
const results = [];
const flush = () => fs.writeFileSync(RESULTS, JSON.stringify({ generated: new Date().toISOString(), base: BASE, results }, null, 1));
const PAGE = '/local/sentientia_evaluation/my_evaluations.php';

async function view(page, name, url, shows) {
  const res = await go(page, url);
  const s = await scan(page);
  const files = await shots(page, OUT, name);
  results.push({ name, url, persona: 'vp_eval77 (test learner, /77)', shows, http: res ? res.status() : null, files,
    problems: s.problems, overflow: files.overflow, console: page.errs.splice(0), textExcerpt: s.text.replace(/\s+/g, ' ').slice(0, 700) });
  flush();
  console.log(`${name}: http ${res ? res.status() : '?'}${s.problems.length ? ' PROBLEMS ' + s.problems.join('; ') : ''}`);
}

const browser = await launch();
try {
  const { context, page } = await session(browser, 'vp_eval77');
  if (PHASE === 'on') {
    await view(page, '01-my-evaluations-en', PAGE,
      'Learner history, flag ON for tenant /77: a named form answered, an anonymous form answered (with the "not linked to you" note), a form waiting for the learner, and a closed one');
    await view(page, '02-my-evaluations-hi', PAGE + '?lang=hi', 'The same page in Hindi (?lang=hi): column titles, statuses, the anonymous note');
    await go(page, PAGE + '?lang=en');
  } else {
    await view(page, '03-my-evaluations-flag-off', PAGE, 'Flag OFF (the default): the page answers "not available", as if it did not exist');
  }
  await context.close();
} finally {
  await browser.close();
  flush();
}
