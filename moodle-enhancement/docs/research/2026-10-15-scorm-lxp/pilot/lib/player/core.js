/* core.js - pure functions shared by the Sentientia player and the Node tests.
 * No DOM access. Loads as a browser global (window.SentientiaCore) or via require().
 *
 * State token grammar (version v1), mirrored by lib/statetoken.py:
 *   token     := "v1" "|" lang "|" blockIndex "|" bitmask "|" scenarios "|" quizzes
 *   bitmask   := base64url bytes, bit i = byte[i >> 3] & (1 << (i & 7))
 *   scenarios := scenarioId ":" edgeId ("," edgeId)*  joined by ";"
 *   quizzes   := quizId ["!"] "=" questionId ":" typeChar value ("," ...)*  joined by ";"
 *   typeChar  := c (multichoice) | m (multiresponse, "+"-joined) | t (truefalse) | x (match, "left.right" "+"-joined)
 * A token starting with "z:" is deflate-raw + base64url of a plain token.
 */
(function (root, factory) {
  'use strict';
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.SentientiaCore = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var TOKEN_VERSION = 'v1';
  var COMPRESSED_PREFIX = 'z:';
  var LIMITS = { scorm12: 4000, scorm12Hard: 4096, scorm12Precompress: 3500, scorm2004: 64000, cmi5: 64000 };
  var B64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
  var DEFAULT_RTL = ['ar', 'ur', 'he', 'fa'];

  // ---- base64url ---------------------------------------------------------

  function base64urlEncode(bytes) {
    var out = '';
    var i = 0;
    for (; i + 2 < bytes.length; i += 3) {
      var n = (bytes[i] << 16) | (bytes[i + 1] << 8) | bytes[i + 2];
      out += B64[(n >> 18) & 63] + B64[(n >> 12) & 63] + B64[(n >> 6) & 63] + B64[n & 63];
    }
    var rest = bytes.length - i;
    if (rest === 1) {
      var a = bytes[i] << 16;
      out += B64[(a >> 18) & 63] + B64[(a >> 12) & 63];
    } else if (rest === 2) {
      var b = (bytes[i] << 16) | (bytes[i + 1] << 8);
      out += B64[(b >> 18) & 63] + B64[(b >> 12) & 63] + B64[(b >> 6) & 63];
    }
    return out;
  }

  function base64urlDecode(text) {
    var bytes = [];
    var buffer = 0;
    var bits = 0;
    for (var i = 0; i < text.length; i++) {
      var value = B64.indexOf(text.charAt(i));
      if (value < 0) { continue; }
      buffer = (buffer << 6) | value;
      bits += 6;
      if (bits >= 8) {
        bits -= 8;
        bytes.push((buffer >> bits) & 255);
        buffer &= (1 << bits) - 1;
      }
    }
    return bytes;
  }

  // ---- bitmask -------------------------------------------------------------

  function bitmaskEncode(visited) {
    if (!visited || !visited.length) { return ''; }
    var bytes = [];
    for (var i = 0; i < Math.ceil(visited.length / 8); i++) { bytes.push(0); }
    for (var j = 0; j < visited.length; j++) {
      if (visited[j]) { bytes[j >> 3] |= 1 << (j & 7); }
    }
    return base64urlEncode(bytes);
  }

  function bitmaskDecode(text, count) {
    var bytes = base64urlDecode(text || '');
    var out = [];
    for (var i = 0; i < count; i++) {
      out.push((i >> 3) < bytes.length && (bytes[i >> 3] & (1 << (i & 7))) !== 0);
    }
    return out;
  }

  // ---- state token ---------------------------------------------------------

  function encodeAnswer(answer) {
    var kind = answer.type;
    if (kind === 'c' || kind === 't') { return kind + String(answer.value); }
    if (kind === 'm') { return kind + (answer.value || []).join('+'); }
    if (kind === 'x') {
      return kind + (answer.value || []).map(function (pair) { return pair[0] + '.' + pair[1]; }).join('+');
    }
    throw new Error('unknown answer type ' + kind);
  }

  function decodeAnswer(text) {
    var kind = text.charAt(0);
    var body = text.slice(1);
    if (kind === 'c' || kind === 't') { return { type: kind, value: body }; }
    if (kind === 'm') { return { type: kind, value: body ? body.split('+') : [] }; }
    if (kind === 'x') {
      var pairs = [];
      body.split('+').forEach(function (item) {
        if (!item) { return; }
        var dot = item.indexOf('.');
        pairs.push(dot < 0 ? [item, ''] : [item.slice(0, dot), item.slice(dot + 1)]);
      });
      return { type: kind, value: pairs };
    }
    throw new Error('unknown answer type ' + kind);
  }

  function encodeScenarios(scenarios) {
    var parts = [];
    Object.keys(scenarios || {}).forEach(function (sid) {
      var path = scenarios[sid] || [];
      if (path.length) { parts.push(sid + ':' + path.join(',')); }
    });
    return parts.join(';');
  }

  function encodeQuizzes(quizzes) {
    var parts = [];
    Object.keys(quizzes || {}).forEach(function (qid) {
      var quiz = quizzes[qid] || {};
      var answers = quiz.answers || {};
      var ids = Object.keys(answers);
      if (!ids.length && !quiz.submitted) { return; }
      var body = ids.map(function (question) { return question + ':' + encodeAnswer(answers[question]); }).join(',');
      parts.push(qid + (quiz.submitted ? '!' : '') + '=' + body);
    });
    return parts.join(';');
  }

  function encodeState(state) {
    return [
      TOKEN_VERSION,
      String(state.lang || ''),
      String(parseInt(state.blockIndex, 10) || 0),
      bitmaskEncode(state.visited || []),
      encodeScenarios(state.scenarios),
      encodeQuizzes(state.quizzes)
    ].join('|');
  }

  function decodeState(token, blockCount) {
    if (!token || token.indexOf(COMPRESSED_PREFIX) === 0) { throw new Error('token is compressed; decompress first'); }
    var parts = token.split('|');
    if (parts.length !== 6 || parts[0] !== TOKEN_VERSION) { throw new Error('malformed state token'); }
    var scenarios = {};
    parts[4].split(';').forEach(function (entry) {
      if (!entry) { return; }
      var colon = entry.indexOf(':');
      var sid = colon < 0 ? entry : entry.slice(0, colon);
      var path = colon < 0 ? '' : entry.slice(colon + 1);
      scenarios[sid] = path ? path.split(',') : [];
    });
    var quizzes = {};
    parts[5].split(';').forEach(function (entry) {
      if (!entry) { return; }
      var eq = entry.indexOf('=');
      var head = eq < 0 ? entry : entry.slice(0, eq);
      var body = eq < 0 ? '' : entry.slice(eq + 1);
      var submitted = head.charAt(head.length - 1) === '!';
      var qid = submitted ? head.slice(0, -1) : head;
      var answers = {};
      body.split(',').forEach(function (item) {
        if (!item) { return; }
        var colon = item.indexOf(':');
        if (colon < 0) { return; }
        answers[item.slice(0, colon)] = decodeAnswer(item.slice(colon + 1));
      });
      quizzes[qid] = { submitted: submitted, answers: answers };
    });
    return {
      lang: parts[1],
      blockIndex: parseInt(parts[2], 10) || 0,
      visited: bitmaskDecode(parts[3], blockCount),
      scenarios: scenarios,
      quizzes: quizzes
    };
  }

  function cloneState(state) {
    return JSON.parse(JSON.stringify(state));
  }

  /* Truncation ladder: quiz answers (latest first), then scenario edges (latest first),
   * then the visited bitmask. The result never exceeds `limit` characters. */
  function fitToken(state, limit) {
    var truncated = [];
    var work = cloneState(state);
    var token = encodeState(work);
    if (token.length <= limit) { return { token: token, truncated: truncated }; }
    var quizIds = Object.keys(work.quizzes || {});
    for (var q = quizIds.length - 1; q >= 0 && token.length > limit; q--) {
      var quiz = work.quizzes[quizIds[q]];
      var answerIds = Object.keys(quiz.answers || {});
      while (answerIds.length && token.length > limit) {
        var dropped = answerIds.pop();
        delete quiz.answers[dropped];
        truncated.push('quiz:' + quizIds[q] + ':' + dropped);
        token = encodeState(work);
      }
    }
    var sids = Object.keys(work.scenarios || {});
    for (var s = sids.length - 1; s >= 0 && token.length > limit; s--) {
      var path = work.scenarios[sids[s]];
      while (path.length && token.length > limit) {
        truncated.push('scenario:' + sids[s] + ':' + path.pop());
        token = encodeState(work);
      }
    }
    if (token.length > limit) {
      work.visited = [];
      truncated.push('visited');
      token = encodeState(work);
    }
    if (token.length > limit) {
      token = token.slice(0, limit);
      truncated.push('hard-cut');
    }
    return { token: token, truncated: truncated };
  }

  function readAll(readable) {
    var reader = readable.getReader();
    var chunks = [];
    var total = 0;
    function pump() {
      return reader.read().then(function (result) {
        if (result.done) {
          var merged = new Uint8Array(total);
          var offset = 0;
          chunks.forEach(function (chunk) { merged.set(chunk, offset); offset += chunk.length; });
          return merged;
        }
        chunks.push(result.value);
        total += result.value.length;
        return pump();
      });
    }
    return pump();
  }

  function compressToken(token) {
    if (typeof CompressionStream !== 'function' || typeof TextEncoder !== 'function') {
      return Promise.reject(new Error('CompressionStream unavailable'));
    }
    var stream = new CompressionStream('deflate-raw');
    var writer = stream.writable.getWriter();
    writer.write(new TextEncoder().encode(token));
    writer.close();
    return readAll(stream.readable).then(function (bytes) { return COMPRESSED_PREFIX + base64urlEncode(bytes); });
  }

  function decompressToken(token) {
    if (!token || token.indexOf(COMPRESSED_PREFIX) !== 0) { return Promise.resolve(token); }
    if (typeof DecompressionStream !== 'function' || typeof TextDecoder !== 'function') {
      return Promise.reject(new Error('DecompressionStream unavailable'));
    }
    var stream = new DecompressionStream('deflate-raw');
    var writer = stream.writable.getWriter();
    writer.write(new Uint8Array(base64urlDecode(token.slice(COMPRESSED_PREFIX.length))));
    writer.close();
    return readAll(stream.readable).then(function (bytes) { return new TextDecoder().decode(bytes); });
  }

  // ---- WebVTT ----------------------------------------------------------------

  function vttTimeToMs(text) {
    var match = /^(?:(\d+):)?(\d{1,2}):(\d{1,2})[.,](\d{1,3})$/.exec(String(text).trim());
    if (!match) { return NaN; }
    var hours = parseInt(match[1] || '0', 10);
    var millis = (match[4] + '00').slice(0, 3);
    return ((hours * 60 + parseInt(match[2], 10)) * 60 + parseInt(match[3], 10)) * 1000 + parseInt(millis, 10);
  }

  function unescapeVtt(text) {
    return text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&');
  }

  function parseVtt(text) {
    var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
    var cues = [];
    var i = 0;
    if (lines.length && lines[0].indexOf('WEBVTT') === 0) { i = 1; }
    while (i < lines.length) {
      var line = lines[i];
      if (line.trim() === '' || /^(NOTE|STYLE|REGION|Language:|Kind:)/.test(line)) { i++; continue; }
      var arrow = line.indexOf('-->');
      if (arrow < 0) {
        i++;
        if (i >= lines.length) { break; }
        line = lines[i];
        arrow = line.indexOf('-->');
        if (arrow < 0) { i++; continue; }
      }
      var start = vttTimeToMs(line.slice(0, arrow));
      var end = vttTimeToMs(line.slice(arrow + 3).trim().split(/\s+/)[0]);
      i++;
      var textLines = [];
      while (i < lines.length && lines[i].trim() !== '') { textLines.push(lines[i]); i++; }
      if (!isNaN(start) && !isNaN(end)) {
        cues.push({ start: start, end: end, text: unescapeVtt(textLines.join('\n')) });
      }
    }
    return cues;
  }

  function cueAt(cues, timeMs) {
    for (var i = 0; i < cues.length; i++) {
      if (timeMs >= cues[i].start && timeMs < cues[i].end) { return cues[i]; }
    }
    return null;
  }

  // ---- questions -------------------------------------------------------------

  function correctAnswer(question) {
    var options = question.options || [];
    var correctIds = options.filter(function (o) { return o.correct === true; }).map(function (o) { return o.id; });
    if (question.type === 'multichoice') { return { type: 'c', value: correctIds[0] || '' }; }
    if (question.type === 'truefalse') { return { type: 't', value: correctIds[0] || '' }; }
    if (question.type === 'multiresponse') { return { type: 'm', value: correctIds }; }
    if (question.type === 'match') {
      return { type: 'x', value: (question.pairs || []).map(function (p) { return [p.id, p.id]; }) };
    }
    throw new Error('unknown question type ' + question.type);
  }

  function emptyAnswer(question) {
    if (question.type === 'multichoice') { return { type: 'c', value: '' }; }
    if (question.type === 'truefalse') { return { type: 't', value: '' }; }
    if (question.type === 'multiresponse') { return { type: 'm', value: [] }; }
    return { type: 'x', value: [] };
  }

  function sameSet(a, b) {
    if (a.length !== b.length) { return false; }
    var sorted = a.slice().sort();
    var other = b.slice().sort();
    for (var i = 0; i < sorted.length; i++) { if (sorted[i] !== other[i]) { return false; } }
    return true;
  }

  /* multichoice / truefalse / multiresponse are all-or-nothing; match earns proportional credit per pair. */
  function scoreQuestion(question, answer) {
    var points = typeof question.points === 'number' ? question.points : 1;
    var given = answer || emptyAnswer(question);
    var expected = correctAnswer(question);
    var earned = 0;
    var detail = {};
    if (question.type === 'multichoice' || question.type === 'truefalse') {
      earned = given.value && given.value === expected.value ? points : 0;
    } else if (question.type === 'multiresponse') {
      earned = sameSet(given.value || [], expected.value) ? points : 0;
    } else if (question.type === 'match') {
      var pairs = question.pairs || [];
      var correctPairs = 0;
      var chosen = {};
      (given.value || []).forEach(function (pair) { chosen[pair[0]] = pair[1]; });
      pairs.forEach(function (pair) {
        var ok = chosen[pair.id] === pair.id;
        detail[pair.id] = ok;
        if (ok) { correctPairs++; }
      });
      earned = pairs.length ? points * correctPairs / pairs.length : 0;
    }
    return { earned: round2(earned), max: points, correct: points > 0 ? earned >= points : true, detail: detail };
  }

  function scoreQuiz(quiz, answers) {
    var earned = 0;
    var max = 0;
    var perQuestion = {};
    (quiz.questions || []).forEach(function (question) {
      var result = scoreQuestion(question, (answers || {})[question.id]);
      perQuestion[question.id] = result;
      earned += result.earned;
      max += result.max;
    });
    var percent = max > 0 ? earned / max * 100 : 0;
    return { earned: round2(earned), max: max, percent: round1(percent), perQuestion: perQuestion };
  }

  function interactionType(question) {
    if (question.type === 'truefalse') { return 'true-false'; }
    if (question.type === 'match') { return 'matching'; }
    return 'choice';
  }

  function truefalseWord(id, standard) {
    var value = String(id);
    if (/^(t|true|1)$/i.test(value)) { return standard === 'scorm12' ? 't' : 'true'; }
    if (/^(f|false|0)$/i.test(value)) { return standard === 'scorm12' ? 'f' : 'false'; }
    return value;
  }

  /* SCORM 1.2 uses "," and "."; SCORM 2004 (and xAPI) use "[,]" and "[.]". */
  function formatResponse(answer, standard) {
    var sep = standard === 'scorm12' ? ',' : '[,]';
    var pairSep = standard === 'scorm12' ? '.' : '[.]';
    if (!answer) { return ''; }
    if (answer.type === 'c') { return String(answer.value); }
    if (answer.type === 't') { return truefalseWord(answer.value, standard); }
    if (answer.type === 'm') { return (answer.value || []).join(sep); }
    if (answer.type === 'x') {
      return (answer.value || []).map(function (pair) { return pair[0] + pairSep + pair[1]; }).join(sep);
    }
    return '';
  }

  // ---- scenarios -------------------------------------------------------------

  function nodeById(scenario, id) {
    var nodes = scenario.nodes || [];
    for (var i = 0; i < nodes.length; i++) { if (nodes[i].id === id) { return nodes[i]; } }
    return null;
  }

  function followInfo(scenario, node) {
    var guard = 0;
    while (node && node.kind === 'info' && node.next && guard++ < 1000) { node = nodeById(scenario, node.next); }
    return node;
  }

  function findEdge(node, edgeId) {
    var edges = (node && node.edges) || [];
    for (var i = 0; i < edges.length; i++) { if (edges[i].id === edgeId) { return edges[i]; } }
    return null;
  }

  function scenarioMax(scenario) {
    if (scenario.scoring && typeof scenario.scoring.max === 'number' && scenario.scoring.max > 0) { return scenario.scoring.max; }
    var total = 0;
    (scenario.nodes || []).forEach(function (node) {
      var best = 0;
      (node.edges || []).forEach(function (edge) { best = Math.max(best, edge.score_delta || 0); });
      total += best;
    });
    return Math.max(1, total);
  }

  /* Walks the recorded edge path from scenario.start; info nodes are passed through
   * before each decision. Returns the score, the node the learner is on and whether
   * a terminal has been reached (through any trailing info nodes). */
  function scoreScenario(scenario, path) {
    var score = 0;
    var current = nodeById(scenario, scenario.start);
    var validEdges = [];
    var edgesTaken = [];
    for (var i = 0; i < (path || []).length; i++) {
      var decision = followInfo(scenario, current);
      var edge = findEdge(decision, path[i]);
      if (!edge) { break; }
      score += edge.score_delta || 0;
      validEdges.push(edge.id);
      edgesTaken.push({ edge: edge, node: decision });
      current = nodeById(scenario, edge.to) || decision;
    }
    var max = scenarioMax(scenario);
    var clamped = Math.min(max, Math.max(0, score));
    var terminal = followInfo(scenario, current);
    var finished = !!(terminal && terminal.kind === 'terminal');
    var pass = scenario.scoring && typeof scenario.scoring.pass === 'number' ? scenario.scoring.pass : null;
    return {
      score: score,
      clamped: clamped,
      max: max,
      pass: pass,
      passed: pass === null ? null : clamped >= pass,
      percent: round1(clamped / max * 100),
      finished: finished,
      currentNodeId: current ? current.id : scenario.start,
      terminalOutcome: finished ? terminal.terminal_outcome : null,
      validEdges: validEdges,
      edgesTaken: edgesTaken
    };
  }

  function courseScore(doc, state, mastery) {
    var components = [];
    var declared = 0;
    var open = 0;
    (doc.blocks || []).forEach(function (block) {
      if (block.type === 'scenario') {
        var result = scoreScenario(block, (state.scenarios || {})[block.id] || []);
        var weight = block.scoring && typeof block.scoring.weight_in_course === 'number' ? block.scoring.weight_in_course : null;
        components.push({ id: block.id, kind: 'scenario', percent: result.percent, weight: weight, done: result.finished });
        if (weight === null) { open++; } else { declared += weight; }
      } else if (block.type === 'quiz') {
        var quizState = (state.quizzes || {})[block.id];
        var quizResult = scoreQuiz(block, quizState ? quizState.answers : {});
        components.push({ id: block.id, kind: 'quiz', percent: quizResult.percent, weight: null, done: !!(quizState && quizState.submitted) });
        open++;
      }
    });
    var remaining = Math.max(0, 1 - declared);
    components.forEach(function (component) {
      if (component.weight === null) { component.weight = open ? remaining / open : 0; }
    });
    var totalWeight = 0;
    var weighted = 0;
    components.forEach(function (component) { totalWeight += component.weight; weighted += component.weight * component.percent; });
    var percent = totalWeight > 0 ? weighted / totalWeight : (components.length ? 0 : 100);
    var visited = state.visited || [];
    var allVisited = (doc.blocks || []).every(function (block, index) { return !!visited[index]; });
    var allDone = components.every(function (component) { return component.done; });
    var complete = allVisited && allDone;
    var passMark = typeof mastery === 'number' ? mastery : 0;
    return {
      percent: round1(percent),
      components: components,
      complete: complete,
      passed: complete && percent >= passMark,
      mastery: passMark,
      visitedCount: visited.filter(Boolean).length,
      blockCount: (doc.blocks || []).length
    };
  }

  // ---- misc ------------------------------------------------------------------

  function round1(value) { return Math.round(value * 10) / 10; }
  function round2(value) { return Math.round(value * 100) / 100; }

  function hashString(text) {
    var hash = 2166136261;
    for (var i = 0; i < text.length; i++) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  }

  function seededShuffle(items, seed) {
    var array = items.slice();
    var s = hashString(String(seed)) || 1;
    function random() {
      s = (s + 0x6D2B79F5) | 0;
      var t = Math.imul(s ^ (s >>> 15), 1 | s);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    }
    for (var i = array.length - 1; i > 0; i--) {
      var j = Math.floor(random() * (i + 1));
      var tmp = array[i];
      array[i] = array[j];
      array[j] = tmp;
    }
    return array;
  }

  function dirForLang(lang, rtlLangs) {
    var list = rtlLangs && rtlLangs.length ? rtlLangs : DEFAULT_RTL;
    var primary = String(lang || '').split('-')[0];
    return list.indexOf(lang) >= 0 || list.indexOf(primary) >= 0 ? 'rtl' : 'ltr';
  }

  function formatTemplate(text, params) {
    return String(text).replace(/\{\$([a-z])\}/g, function (match, key) {
      return params && Object.prototype.hasOwnProperty.call(params, key) ? String(params[key]) : match;
    });
  }

  /* Shared "not tracked" fallback: suspend state lives in web storage when available. */
  function createLocalFallback(storage, key, label) {
    var memory = { suspend: '', location: '' };
    var interactions = [];
    function read() {
      try {
        var raw = storage ? storage.getItem(key) : null;
        if (raw) {
          var parsed = JSON.parse(raw);
          memory.suspend = parsed.suspend || '';
          memory.location = parsed.location || '';
        }
      } catch (err) { /* storage unavailable: memory only */ }
    }
    function write() {
      try { if (storage) { storage.setItem(key, JSON.stringify(memory)); } } catch (err) { /* ignore */ }
    }
    read();
    return {
      standard: 'local',
      tracked: false,
      label: label || 'not tracked',
      interactions: interactions,
      init: function () {
        return Promise.resolve({
          tracked: false,
          label: label || 'not tracked',
          masteryScore: null,
          suspend: memory.suspend,
          location: memory.location,
          entry: memory.suspend ? 'resume' : 'ab-initio',
          launchMode: 'normal'
        });
      },
      setLocation: function (id) { memory.location = String(id || ''); write(); },
      setSuspend: function (token) { memory.suspend = String(token || ''); write(); },
      getSuspend: function () { return memory.suspend; },
      recordInteraction: function (record) { interactions.push(record); },
      setScore: function () {},
      setStatus: function () {},
      commit: function () { write(); },
      finish: function () { write(); }
    };
  }

  return {
    TOKEN_VERSION: TOKEN_VERSION,
    COMPRESSED_PREFIX: COMPRESSED_PREFIX,
    LIMITS: LIMITS,
    base64urlEncode: base64urlEncode,
    base64urlDecode: base64urlDecode,
    bitmaskEncode: bitmaskEncode,
    bitmaskDecode: bitmaskDecode,
    encodeAnswer: encodeAnswer,
    decodeAnswer: decodeAnswer,
    encodeState: encodeState,
    decodeState: decodeState,
    cloneState: cloneState,
    fitToken: fitToken,
    compressToken: compressToken,
    decompressToken: decompressToken,
    vttTimeToMs: vttTimeToMs,
    parseVtt: parseVtt,
    cueAt: cueAt,
    correctAnswer: correctAnswer,
    emptyAnswer: emptyAnswer,
    scoreQuestion: scoreQuestion,
    scoreQuiz: scoreQuiz,
    interactionType: interactionType,
    formatResponse: formatResponse,
    nodeById: nodeById,
    followInfo: followInfo,
    findEdge: findEdge,
    scenarioMax: scenarioMax,
    scoreScenario: scoreScenario,
    courseScore: courseScore,
    seededShuffle: seededShuffle,
    hashString: hashString,
    dirForLang: dirForLang,
    formatTemplate: formatTemplate,
    createLocalFallback: createLocalFallback
  };
}));
