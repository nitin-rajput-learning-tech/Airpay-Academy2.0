/* runtime-scorm12.js - SCORM 1.2 adapter (window.API) behind the common runtime interface:
 *   init, setLocation, setSuspend, getSuspend, recordInteraction, setScore, setStatus, commit, finish
 * Registers window.SentientiaRuntimes.scorm12 = factory(options); exports the factory under Node.
 */
(function (root) {
  'use strict';
  var core = root.SentientiaCore || (typeof require === 'function' ? require('./core.js') : null);
  var SUSPEND_HARD_LIMIT = 4096;
  var RESPONSE_LIMIT = 255;
  var COMMIT_INTERVAL_MS = 500;

  function safeStorage() {
    try { return typeof localStorage !== 'undefined' ? localStorage : null; } catch (err) { return null; }
  }

  function findApi(win, depth) {
    var current = win;
    var hops = 0;
    while (current && hops < 500) {
      try { if (current.API) { return current.API; } } catch (err) { break; }
      try {
        if (!current.parent || current.parent === current) { break; }
        current = current.parent;
      } catch (err) { break; }
      hops++;
    }
    try {
      if (depth < 5 && win && win.opener && win.opener !== win) { return findApi(win.opener, depth + 1); }
    } catch (err) { /* cross-origin opener */ }
    return null;
  }

  function pad(value) { return (value < 10 ? '0' : '') + value; }

  function cmiTime(date) {
    return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
  }

  function createRuntime(options) {
    options = options || {};
    var win = options.win || (typeof window !== 'undefined' ? window : null);
    var storage = options.storage !== undefined ? options.storage : safeStorage();
    var courseId = options.courseId || 'course';
    var timers = options.timers || { setTimeout: function (fn, ms) { return setTimeout(fn, ms); }, clearTimeout: function (id) { clearTimeout(id); } };
    var now = options.now || function () { return new Date(); };
    var api = null;
    var fallback = null;
    var initialized = false;
    var finished = false;
    var commitTimer = null;
    var lastSuspend = '';
    var lastLocation = '';

    function get(name) {
      try { return String(api.LMSGetValue(name) || ''); } catch (err) { return ''; }
    }

    function set(name, value) {
      try { return String(api.LMSSetValue(name, String(value))) !== 'false'; } catch (err) { return false; }
    }

    function commitNow() {
      try { api.LMSCommit(''); } catch (err) { /* LMS unreachable */ }
    }

    function scheduleCommit() {
      if (!initialized || finished || commitTimer) { return; }
      commitTimer = timers.setTimeout(function () {
        commitTimer = null;
        commitNow();
      }, COMMIT_INTERVAL_MS);
    }

    function flush() {
      if (commitTimer) {
        timers.clearTimeout(commitTimer);
        commitTimer = null;
      }
      commitNow();
    }

    function useFallback(label) {
      fallback = core.createLocalFallback(storage, 'sentientia:' + courseId + ':scorm12', label);
      runtime.tracked = false;
      runtime.label = label;
      return fallback.init();
    }

    var runtime = {
      standard: 'scorm12',
      tracked: false,
      label: 'not tracked',
      init: function () {
        api = findApi(win, 0);
        if (!api) { return useFallback('not tracked (no SCORM 1.2 API found)'); }
        var ok;
        try { ok = api.LMSInitialize(''); } catch (err) { ok = 'false'; }
        if (String(ok) === 'false') { return useFallback('not tracked (LMSInitialize failed)'); }
        initialized = true;
        runtime.tracked = true;
        runtime.label = 'SCORM 1.2';
        var status = get('cmi.core.lesson_status');
        if (!status || status === 'not attempted') { set('cmi.core.lesson_status', 'incomplete'); }
        var mastery = parseFloat(get('cmi.student_data.mastery_score'));
        lastSuspend = get('cmi.suspend_data');
        lastLocation = get('cmi.core.lesson_location');
        return Promise.resolve({
          tracked: true,
          label: 'SCORM 1.2',
          masteryScore: isNaN(mastery) ? null : mastery,
          suspend: lastSuspend,
          location: lastLocation,
          entry: get('cmi.core.entry') || 'ab-initio',
          launchMode: get('cmi.core.lesson_mode') || 'normal',
          lmsStatus: status
        });
      },
      setLocation: function (id) {
        if (fallback) { return fallback.setLocation(id); }
        if (!initialized) { return; }
        lastLocation = String(id || '').slice(0, 255);
        set('cmi.core.lesson_location', lastLocation);
      },
      setSuspend: function (token) {
        if (fallback) { return fallback.setSuspend(token); }
        if (!initialized) { return; }
        var value = String(token || '');
        if (value.length > SUSPEND_HARD_LIMIT) { value = value.slice(0, SUSPEND_HARD_LIMIT); }
        lastSuspend = value;
        set('cmi.suspend_data', value);
      },
      getSuspend: function () {
        return fallback ? fallback.getSuspend() : lastSuspend;
      },
      recordInteraction: function (record) {
        if (fallback) { return fallback.recordInteraction(record); }
        if (!initialized) { return; }
        var count = parseInt(get('cmi.interactions._count'), 10);
        if (isNaN(count)) { count = 0; }
        var prefix = 'cmi.interactions.' + count + '.';
        set(prefix + 'id', String(record.id).slice(0, 255));
        set(prefix + 'type', record.type);
        set(prefix + 'student_response', core.formatResponse(record.response, 'scorm12').slice(0, RESPONSE_LIMIT));
        if (record.correctAnswer) {
          set(prefix + 'correct_responses.0.pattern', core.formatResponse(record.correctAnswer, 'scorm12').slice(0, RESPONSE_LIMIT));
        }
        set(prefix + 'result', record.correct === true ? 'correct' : (record.correct === false ? 'wrong' : 'neutral'));
        set(prefix + 'time', cmiTime(now()));
        if (typeof record.weighting === 'number') { set(prefix + 'weighting', record.weighting); }
        scheduleCommit();
      },
      setScore: function (score) {
        if (fallback) { return fallback.setScore(score); }
        if (!initialized) { return; }
        set('cmi.core.score.raw', Math.round(score.raw * 100) / 100);
        set('cmi.core.score.min', typeof score.min === 'number' ? score.min : 0);
        set('cmi.core.score.max', typeof score.max === 'number' ? score.max : 100);
        scheduleCommit();
      },
      setStatus: function (status) {
        if (fallback) { return fallback.setStatus(status); }
        if (!initialized) { return; }
        var value = 'incomplete';
        if (status.success === 'passed' || status.success === 'failed') { value = status.success; }
        else if (status.completion === 'completed') { value = 'completed'; }
        set('cmi.core.lesson_status', value);
        scheduleCommit();
      },
      commit: function () {
        if (fallback) { return fallback.commit(); }
        scheduleCommit();
      },
      finish: function () {
        if (fallback) { return fallback.finish(); }
        if (!initialized || finished) { return; }
        finished = true;
        set('cmi.core.exit', 'suspend');
        flush();
        try { api.LMSFinish(''); } catch (err) { /* LMS unreachable */ }
      }
    };
    return runtime;
  }

  root.SentientiaRuntimes = root.SentientiaRuntimes || {};
  root.SentientiaRuntimes.scorm12 = createRuntime;
  if (typeof module === 'object' && module.exports) { module.exports = createRuntime; }
}(typeof window !== 'undefined' ? window : globalThis));
