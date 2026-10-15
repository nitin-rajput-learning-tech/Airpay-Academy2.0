/* smoke_package.js - headless learner simulation against an EXTRACTED package.
 *
 * Usage: node smoke_package.js <extracted-package-dir> <scorm12|scorm2004|cmi5>
 * Prints one JSON line {"ok": bool, "standard": ..., "checks": [{name, ok, detail}]}.
 *
 * It loads the package's own core.js, runtime-<standard>.js and course-data.js, plugs the runtime
 * into a fake LMS (SCORM API object, or a fake LRS for cmi5) that enforces the limits Moodle enforces
 * (4,096-character suspend_data and 255-character interaction responses for SCORM 1.2), then:
 *   1. plays the best path through every scenario and answers every quiz question correctly -> must pass;
 *   2. plays a failing attempt -> must report failed;
 *   3. resumes from the saved suspend data in a fresh runtime and compares the decoded state;
 *   4. checks the worst-case state fits the standard's limit without truncation;
 *   5. checks exit=suspend (SCORM) / terminated (cmi5), interaction ids and counts.
 * The DOM player is not executed here (that needs a browser; see pilot/evidence/); this proves the
 * adapters, the state token and the scoring against the real compiled course data.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const dir = path.resolve(process.argv[2] || '.');
const standard = process.argv[3] || 'scorm12';
const checks = [];

function check(name, ok, detail) { checks.push({ name: name, ok: !!ok, detail: detail === undefined ? '' : String(detail) }); }

const core = require(path.join(dir, 'core.js'));
globalThis.SentientiaCore = core;
const makeRuntime = require(path.join(dir, 'runtime-' + standard + '.js'));
const dataText = fs.readFileSync(path.join(dir, 'course-data.js'), 'utf8');
const PREFIX = 'window.SENTIENTIA_COURSE = ';
const DATA = JSON.parse(dataText.slice(PREFIX.length).trim().replace(/;$/, ''));
const doc = DATA.doc;
const blocks = doc.blocks;
const mastery = DATA.mastery;
const immediate = { setTimeout: function (fn) { fn(); return 1; }, clearTimeout: function () {} };
const tick = function () { return new Promise(function (resolve) { setImmediate(resolve); }); };
async function settle(runtime) { for (let i = 0; i < 25; i++) { await tick(); if (!runtime.queue || !runtime.queue.length) { break; } } }

// ---- fake LMS for SCORM 1.2 / 2004 -------------------------------------------------------------------

function fakeScormApi(kind, store) {
  const is12 = kind === 'scorm12';
  const limits = is12 ? { suspend: 4096, response: 255 } : { suspend: 64000, response: 4000 };
  if (!store.init) {
    if (is12) {
      Object.assign(store, { 'cmi.core.lesson_status': 'not attempted', 'cmi.student_data.mastery_score': String(mastery), 'cmi.core.entry': 'ab-initio', 'cmi.core.lesson_mode': 'normal', 'cmi.suspend_data': '', 'cmi.core.lesson_location': '' });
    } else {
      Object.assign(store, { 'cmi.completion_status': 'unknown', 'cmi.success_status': 'unknown', 'cmi.scaled_passing_score': String(mastery / 100), 'cmi.entry': 'ab-initio', 'cmi.mode': 'normal', 'cmi.suspend_data': '', 'cmi.location': '' });
    }
    store.init = true;
    store.rejected = [];
    store.calls = { commit: 0, finish: 0 };
    store['cmi.interactions._count'] = '0';
  }
  function set(name, value) {
    value = String(value);
    if (name === 'cmi.suspend_data' && value.length > limits.suspend) { store.rejected.push(name + ' too long: ' + value.length); return 'false'; }
    if (/^cmi\.interactions\.\d+\.(student_response|learner_response)$/.test(name) && value.length > limits.response) { store.rejected.push(name + ' too long: ' + value.length); return 'false'; }
    const match = /^cmi\.interactions\.(\d+)\.id$/.exec(name);
    if (match) { store['cmi.interactions._count'] = String(Math.max(parseInt(store['cmi.interactions._count'], 10), parseInt(match[1], 10) + 1)); }
    store[name] = value;
    return 'true';
  }
  const common = {
    LMSGetLastError: function () { return '0'; }, GetLastError: function () { return '0'; },
    LMSGetErrorString: function () { return ''; }, GetErrorString: function () { return ''; },
    LMSGetDiagnostic: function () { return ''; }, GetDiagnostic: function () { return ''; }
  };
  if (is12) {
    return Object.assign({
      LMSInitialize: function () { store.initialised = true; return 'true'; },
      LMSGetValue: function (n) { return store[n] === undefined ? '' : store[n]; },
      LMSSetValue: set,
      LMSCommit: function () { store.calls.commit++; return 'true'; },
      LMSFinish: function () { store.calls.finish++; store.finished = true; return 'true'; }
    }, common);
  }
  return Object.assign({
    Initialize: function () { store.initialised = true; return 'true'; },
    GetValue: function (n) { return store[n] === undefined ? '' : store[n]; },
    SetValue: set,
    Commit: function () { store.calls.commit++; return 'true'; },
    Terminate: function () { store.calls.finish++; store.finished = true; return 'true'; }
  }, common);
}

// ---- fake LRS for cmi5 --------------------------------------------------------------------------------

function fakeLrs() {
  const lrs = { statements: [], states: {}, beacons: [], fetches: 0 };
  const EXT_SESSION = 'https://w3id.org/xapi/cmi5/context/extensions/sessionid';
  lrs.fetch = function (url, options) {
    options = options || {};
    lrs.fetches++;
    const ok = function (body) { return Promise.resolve({ ok: true, status: 200, json: function () { return Promise.resolve(body); } }); };
    if (/\/fetch$/.test(url)) { return ok({ 'auth-token': 'dGVzdDp0b2tlbg==' }); }
    if (/activities\/state/.test(url)) {
      const stateId = decodeURIComponent((/stateId=([^&]+)/.exec(url) || [])[1] || '');
      if ((options.method || 'GET') === 'PUT') { lrs.states[stateId] = JSON.parse(options.body); return ok({}); }
      if (stateId === 'LMS.LaunchData') {
        return ok({ contextTemplate: { extensions: { [EXT_SESSION]: 'session-1' }, contextActivities: { grouping: [{ id: 'urn:test:course', objectType: 'Activity' }] } }, launchMode: 'Normal', masteryScore: mastery / 100, moveOn: 'CompletedAndPassed' });
      }
      if (lrs.states[stateId]) { return ok(lrs.states[stateId]); }
      return Promise.resolve({ ok: false, status: 404, json: function () { return Promise.resolve(null); } });
    }
    if (/statements/.test(url) && options.method === 'POST') { lrs.statements.push(JSON.parse(options.body)); return ok([]); }
    return Promise.resolve({ ok: false, status: 404, json: function () { return Promise.resolve(null); } });
  };
  lrs.sendBeacon = function (url, form) {
    const content = form && form.get ? form.get('content') : null;
    if (content) { lrs.statements.push(JSON.parse(content)); lrs.beacons.push(url); }
    return true;
  };
  return lrs;
}

function cmi5Options(lrs) {
  const actor = encodeURIComponent(JSON.stringify({ objectType: 'Agent', account: { homePage: 'https://example.invalid', name: 'learner-1' } }));
  const search = '?endpoint=' + encodeURIComponent('https://lrs.invalid/xapi/') + '&fetch=' + encodeURIComponent('https://lrs.invalid/fetch') + '&actor=' + actor + '&registration=11111111-1111-4111-8111-111111111111&activityId=' + encodeURIComponent('urn:test:course/au1');
  return { courseId: DATA.courseId, search: search, fetch: lrs.fetch, sendBeacon: lrs.sendBeacon, storage: null, timers: immediate };
}

// ---- learner simulation -------------------------------------------------------------------------------

function bestEdge(node) { return (node.edges || []).find(function (e) { return e.correct === true; }) || (node.edges || [])[0]; }
function worstEdge(node) { return (node.edges || []).find(function (e) { return e.correct === false && e.outcome === 'irrecoverable'; }) || (node.edges || []).find(function (e) { return e.correct === false; }) || (node.edges || [])[0]; }

function playScenario(block, pick) {
  const path = [];
  let node = core.followInfo(block, core.nodeById(block, block.start));
  let guard = 0;
  while (node && node.kind === 'decision' && guard++ < 100) {
    const edge = pick(node);
    path.push({ node: node, edge: edge });
    node = core.followInfo(block, core.nodeById(block, edge.to));
  }
  return path;
}

function wrongAnswer(question) {
  const right = core.correctAnswer(question);
  if (question.type === 'multichoice' || question.type === 'truefalse') {
    const other = (question.options || []).find(function (o) { return o.correct !== true; });
    return { type: right.type, value: other ? other.id : '' };
  }
  if (question.type === 'multiresponse') {
    const other = (question.options || []).find(function (o) { return o.correct !== true; });
    return { type: 'm', value: other ? [other.id] : [] };
  }
  return { type: 'x', value: (question.pairs || []).map(function (p, i, all) { return [p.id, all[(i + 1) % all.length].id]; }) };
}

function simulate(runtime, good) {
  const state = { lang: DATA.baseLang, blockIndex: blocks.length, visited: blocks.map(function () { return true; }), scenarios: {}, quizzes: {} };
  let interactions = 0;
  blocks.forEach(function (block) {
    if (block.type === 'scenario') {
      const steps = playScenario(block, good ? bestEdge : worstEdge);
      state.scenarios[block.id] = steps.map(function (s) { return s.edge.id; });
      steps.forEach(function (s) {
        const rightEdge = (s.node.edges || []).find(function (e) { return e.correct === true; });
        runtime.recordInteraction({ id: block.id + '.' + s.node.id, type: 'choice', response: { type: 'c', value: s.edge.id }, correctAnswer: rightEdge ? { type: 'c', value: rightEdge.id } : null, correct: s.edge.correct === true ? true : (s.edge.correct === false ? false : null) });
        interactions++;
      });
    } else if (block.type === 'quiz') {
      const answers = {};
      (block.questions || []).forEach(function (q) {
        answers[q.id] = good ? core.correctAnswer(q) : wrongAnswer(q);
        const graded = core.scoreQuestion(q, answers[q.id]);
        runtime.recordInteraction({ id: q.id, type: core.interactionType(q), response: answers[q.id], correctAnswer: core.correctAnswer(q), correct: graded.correct, weighting: q.points });
        interactions++;
      });
      state.quizzes[block.id] = { submitted: true, answers: answers };
    }
  });
  const result = core.courseScore(doc, state, mastery);
  const limit = standard === 'scorm12' ? core.LIMITS.scorm12 : core.LIMITS.scorm2004;
  const fitted = core.fitToken(state, limit);
  runtime.setSuspend(fitted.token);
  runtime.setLocation('summary');
  runtime.setScore({ raw: result.percent, min: 0, max: 100, scaled: result.percent / 100 });
  runtime.setStatus({ completion: result.complete ? 'completed' : 'incomplete', success: result.complete ? (result.passed ? 'passed' : 'failed') : 'unknown', progress: 1 });
  runtime.commit();
  return { state: state, result: result, token: fitted.token, truncated: fitted.truncated, interactions: interactions };
}

function longestPath(block) {
  const memo = {};
  function best(id, stack) {
    if (memo[id]) { return memo[id]; }
    const node = core.nodeById(block, id);
    if (!node || stack[id]) { return []; }
    stack[id] = true;
    let candidate = [];
    (node.edges || []).forEach(function (e) {
      const p = [e.id].concat(best(e.to, stack));
      if (p.join(',').length > candidate.join(',').length) { candidate = p; }
    });
    if (node.next) { const p = best(node.next, stack); if (p.join(',').length > candidate.join(',').length) { candidate = p; } }
    delete stack[id];
    memo[id] = candidate;
    return candidate;
  }
  return best(block.start, {});
}

function worstState() {
  const longestLang = DATA.langs.map(function (l) { return l.tag; }).sort(function (a, b) { return b.length - a.length; })[0];
  const state = { lang: longestLang, blockIndex: blocks.length - 1, visited: blocks.map(function () { return true; }), scenarios: {}, quizzes: {} };
  blocks.forEach(function (block) {
    if (block.type === 'scenario') { state.scenarios[block.id] = longestPath(block); }
    if (block.type === 'quiz') {
      const answers = {};
      (block.questions || []).forEach(function (q) {
        const ids = (q.options || []).map(function (o) { return o.id; });
        const pairs = (q.pairs || []).map(function (p) { return p.id; });
        const longest = function (arr) { return arr.slice().sort(function (a, b) { return b.length - a.length; })[0] || ''; };
        if (q.type === 'multichoice') { answers[q.id] = { type: 'c', value: longest(ids) }; }
        else if (q.type === 'truefalse') { answers[q.id] = { type: 't', value: longest(ids) }; }
        else if (q.type === 'multiresponse') { answers[q.id] = { type: 'm', value: ids }; }
        else { answers[q.id] = { type: 'x', value: pairs.map(function (id) { return [id, longest(pairs)]; }) }; }
      });
      state.quizzes[block.id] = { submitted: true, answers: answers };
    }
  });
  return state;
}

function sameState(a, b) {
  return JSON.stringify(a) === JSON.stringify(b);
}

async function runScorm() {
  const kind = standard;
  const store = {};
  const win = { API: kind === 'scorm12' ? fakeScormApi(kind, store) : undefined, API_1484_11: kind === 'scorm2004' ? fakeScormApi(kind, store) : undefined };
  let runtime = makeRuntime({ courseId: DATA.courseId, win: win, storage: null, timers: immediate });
  const info = await runtime.init();
  check('init: tracked by the LMS', info.tracked === true, info.label);
  check('init: mastery score read from the LMS', info.masteryScore === mastery, 'LMS ' + info.masteryScore + ' vs course ' + mastery);
  const good = simulate(runtime, true);
  runtime.finish();
  check('good attempt: course complete and passed', good.result.complete && good.result.passed, 'percent ' + good.result.percent);
  const statusKey = kind === 'scorm12' ? 'cmi.core.lesson_status' : 'cmi.success_status';
  check('good attempt: LMS status is passed', store[statusKey] === 'passed', store[statusKey]);
  if (kind === 'scorm2004') { check('good attempt: completion_status completed', store['cmi.completion_status'] === 'completed', store['cmi.completion_status']); }
  const rawKey = kind === 'scorm12' ? 'cmi.core.score.raw' : 'cmi.score.raw';
  check('good attempt: score written', parseFloat(store[rawKey]) >= mastery, store[rawKey]);
  check('exit is suspend before finish', store[kind === 'scorm12' ? 'cmi.core.exit' : 'cmi.exit'] === 'suspend', store[kind === 'scorm12' ? 'cmi.core.exit' : 'cmi.exit']);
  check('finish called once', store.calls.finish === 1, store.calls.finish);
  const limit = kind === 'scorm12' ? 4096 : 64000;
  check('suspend_data within the LMS limit', (store['cmi.suspend_data'] || '').length > 0 && store['cmi.suspend_data'].length <= limit, (store['cmi.suspend_data'] || '').length + ' / ' + limit);
  check('no truncation of the state token', good.truncated.length === 0, good.truncated.join(','));
  check('LMS rejected no value', store.rejected.length === 0, store.rejected.join('; '));
  check('interaction count matches decisions + questions', parseInt(store['cmi.interactions._count'], 10) === good.interactions, store['cmi.interactions._count'] + ' vs ' + good.interactions);
  const idKeys = Object.keys(store).filter(function (k) { return /^cmi\.interactions\.\d+\.id$/.test(k); });
  check('interaction ids are URL-safe ids, not text', idKeys.every(function (k) { return /^[A-Za-z0-9_.-]{1,64}$/.test(store[k]); }), idKeys.length + ' ids');
  const responseKeys = Object.keys(store).filter(function (k) { return /\.(student_response|learner_response)$/.test(k); });
  check('responses are id-only and short', responseKeys.every(function (k) { return store[k].length <= 255; }), responseKeys.length + ' responses');

  // resume
  const resumeRuntime = makeRuntime({ courseId: DATA.courseId, win: win, storage: null, timers: immediate });
  const info2 = await resumeRuntime.init();
  const plain = await core.decompressToken(info2.suspend);
  const restored = core.decodeState(plain, blocks.length);
  const expected = JSON.parse(JSON.stringify(good.state));
  check('resume: suspend data returned by the LMS', info2.suspend === store['cmi.suspend_data'] && info2.suspend.length > 0, 'entry=' + info2.entry);
  check('resume: decoded state equals the saved state', sameState({ lang: restored.lang, blockIndex: restored.blockIndex, visited: restored.visited, scenarios: restored.scenarios, quizzes: restored.quizzes }, expected), '');

  // failing attempt on a fresh LMS
  const store2 = {};
  const win2 = { API: kind === 'scorm12' ? fakeScormApi(kind, store2) : undefined, API_1484_11: kind === 'scorm2004' ? fakeScormApi(kind, store2) : undefined };
  runtime = makeRuntime({ courseId: DATA.courseId, win: win2, storage: null, timers: immediate });
  await runtime.init();
  const bad = simulate(runtime, false);
  runtime.finish();
  check('bad attempt: reported as failed', !bad.result.passed && store2[statusKey] === 'failed', 'percent ' + bad.result.percent + ', status ' + store2[statusKey]);

  // no LMS: graceful fallback
  const alone = makeRuntime({ courseId: DATA.courseId, win: {}, storage: null, timers: immediate });
  const info3 = await alone.init();
  check('no LMS: runs untracked without throwing', info3.tracked === false && /not tracked/.test(info3.label), info3.label);
}

async function runCmi5() {
  const lrs = fakeLrs();
  let runtime = makeRuntime(cmi5Options(lrs));
  const info = await runtime.init();
  check('init: cmi5 launch succeeded (fetch token, launch data)', info.tracked === true && lrs.fetches >= 2, info.label);
  check('init: mastery score from LMS.LaunchData', info.masteryScore === mastery, info.masteryScore);
  const good = simulate(runtime, true);
  runtime.setStatus({ completion: 'completed', success: good.result.passed ? 'passed' : 'failed', progress: 1 });
  await settle(runtime);
  runtime.finish();
  await settle(runtime);
  const verbs = lrs.statements.map(function (s) { return s.verb.id.split('/').pop(); });
  check('statements: initialized first', verbs[0] === 'initialized', verbs.slice(0, 3).join(','));
  check('statements: completed and passed sent once', verbs.filter(function (v) { return v === 'completed'; }).length === 1 && verbs.filter(function (v) { return v === 'passed'; }).length === 1, verbs.join(','));
  check('statements: terminated sent last via sendBeacon', verbs[verbs.length - 1] === 'terminated' && lrs.beacons.length === 1, verbs[verbs.length - 1]);
  check('statements: one answered per decision and question', verbs.filter(function (v) { return v === 'answered'; }).length === good.interactions, verbs.filter(function (v) { return v === 'answered'; }).length + ' vs ' + good.interactions);
  const passed = lrs.statements.find(function (s) { return s.verb.id.split('/').pop() === 'passed'; });
  check('passed: carries cmi5 category, registration, session, mastery score', !!passed && JSON.stringify(passed.context.contextActivities.category || []).indexOf('cmi5/context/categories/cmi5') >= 0 && passed.context.registration === '11111111-1111-4111-8111-111111111111' && passed.context.extensions['https://w3id.org/xapi/cmi5/context/extensions/sessionid'] === 'session-1' && passed.context.extensions['https://w3id.org/xapi/cmi5/context/extensions/masteryscore'] === mastery / 100, '');
  check('passed: result.success true with scaled score', !!passed && passed.result.success === true && passed.result.score && passed.result.score.scaled >= mastery / 100, passed ? JSON.stringify(passed.result.score) : '');
  const stateDoc = lrs.states['sentientia.suspend'];
  check('resume document saved to the LRS state API', !!stateDoc && stateDoc.token === good.token, stateDoc ? stateDoc.token.length + ' chars' : 'none');

  const lrs2 = fakeLrs();
  lrs2.states['sentientia.suspend'] = stateDoc;
  const runtime2 = makeRuntime(cmi5Options(lrs2));
  const info2 = await runtime2.init();
  const restored = core.decodeState(await core.decompressToken(info2.suspend), blocks.length);
  check('resume: state restored from the LRS', sameState({ lang: restored.lang, blockIndex: restored.blockIndex, visited: restored.visited, scenarios: restored.scenarios, quizzes: restored.quizzes }, JSON.parse(JSON.stringify(good.state))), 'entry=' + info2.entry);

  const lrs3 = fakeLrs();
  runtime = makeRuntime(cmi5Options(lrs3));
  await runtime.init();
  const bad = simulate(runtime, false);
  runtime.setStatus({ completion: 'completed', success: bad.result.passed ? 'passed' : 'failed', progress: 1 });
  await settle(runtime);
  const failed = lrs3.statements.some(function (s) { return s.verb.id.split('/').pop() === 'failed'; });
  check('bad attempt: failed statement, no passed', failed && !lrs3.statements.some(function (s) { return s.verb.id.split('/').pop() === 'passed'; }), '');

  const alone = makeRuntime({ courseId: DATA.courseId, search: '', fetch: lrs.fetch, storage: null, timers: immediate });
  const info3 = await alone.init();
  check('no launch parameters: runs untracked without throwing', info3.tracked === false && /not tracked/.test(info3.label), info3.label);
}

async function main() {
  try {
    if (standard === 'cmi5') { await runCmi5(); } else { await runScorm(); }
    const worst = worstState();
    const limit = standard === 'scorm12' ? core.LIMITS.scorm12 : core.LIMITS.scorm2004;
    const fitted = core.fitToken(worst, limit);
    check('worst-case state fits the limit with no truncation', fitted.truncated.length === 0 && fitted.token.length <= limit, fitted.token.length + ' chars of ' + limit);
    if (standard === 'scorm12') { check('worst-case plain token within the pre-compression budget', fitted.token.length <= core.LIMITS.scorm12Precompress, fitted.token.length + ' / ' + core.LIMITS.scorm12Precompress); }
  } catch (err) {
    check('simulation ran without throwing', false, err && err.stack ? err.stack.split('\n').slice(0, 3).join(' | ') : err);
  }
  const ok = checks.every(function (c) { return c.ok; });
  process.stdout.write(JSON.stringify({ ok: ok, standard: standard, checks: checks }) + '\n');
  process.exit(ok ? 0 : 1);
}

main();
