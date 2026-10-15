/* runtime-scorm2004.js - SCORM 2004 (4th Ed.) adapter (window.API_1484_11) behind the common
 * runtime interface. Registers window.SentientiaRuntimes.scorm2004; exports the factory under Node.
 */
(function (root) {
  'use strict';
  var core = root.SentientiaCore || (typeof require === 'function' ? require('./core.js') : null);
  var SUSPEND_LIMIT = 64000;
  var RESPONSE_LIMIT = 4000;
  var COMMIT_INTERVAL_MS = 500;

  function safeStorage() {
    try { return typeof localStorage !== 'undefined' ? localStorage : null; } catch (err) { return null; }
  }

  function findApi(win, depth) {
    var current = win;
    var hops = 0;
    while (current && hops < 500) {
      try { if (current.API_1484_11) { return current.API_1484_11; } } catch (err) { break; }
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

  function timestamp(date) {
    return date.toISOString().replace(/\.\d{3}Z$/, 'Z');
  }

  function createRuntime(options) {
    options = options || {};
    var win = options.win || (typeof window !== 'undefined' ? window : null);
    var storage = options.storage !== undefined ? options.storage : safeStorage();
    var courseId = options.courseId || 'course';
    var timers = options.timers || { setTimeout: setTimeout, clearTimeout: clearTimeout };
    var now = options.now || function () { return new Date(); };
    var api = null;
    var fallback = null;
    var initialized = false;
    var finished = false;
    var commitTimer = null;
    var lastSuspend = '';
    var lastLocation = '';

    function get(name) {
      try { return String(api.GetValue(name) || ''); } catch (err) { return ''; }
    }

    function set(name, value) {
      try { return String(api.SetValue(name, String(value))) !== 'false'; } catch (err) { return false; }
    }

    function commitNow() {
      try { api.Commit(''); } catch (err) { /* LMS unreachable */ }
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
      fallback = core.createLocalFallback(storage, 'sentientia:' + courseId + ':scorm2004', label);
      runtime.tracked = false;
      runtime.label = label;
      return fallback.init();
    }

    var runtime = {
      standard: 'scorm2004',
      tracked: false,
      label: 'not tracked',
      init: function () {
        api = findApi(win, 0);
        if (!api) { return useFallback('not tracked (no SCORM 2004 API found)'); }
        var ok;
        try { ok = api.Initialize(''); } catch (err) { ok = 'false'; }
        if (String(ok) === 'false') { return useFallback('not tracked (Initialize failed)'); }
        initialized = true;
        runtime.tracked = true;
        runtime.label = 'SCORM 2004';
        var completion = get('cmi.completion_status');
        if (!completion || completion === 'unknown' || completion === 'not attempted') { set('cmi.completion_status', 'incomplete'); }
        var scaledPassing = parseFloat(get('cmi.scaled_passing_score'));
        lastSuspend = get('cmi.suspend_data');
        lastLocation = get('cmi.location');
        return Promise.resolve({
          tracked: true,
          label: 'SCORM 2004',
          masteryScore: isNaN(scaledPassing) ? null : Math.round(scaledPassing * 1000) / 10,
          suspend: lastSuspend,
          location: lastLocation,
          entry: get('cmi.entry') || 'ab-initio',
          launchMode: get('cmi.mode') || 'normal',
          lmsStatus: completion
        });
      },
      setLocation: function (id) {
        if (fallback) { return fallback.setLocation(id); }
        if (!initialized) { return; }
        lastLocation = String(id || '').slice(0, 1000);
        set('cmi.location', lastLocation);
      },
      setSuspend: function (token) {
        if (fallback) { return fallback.setSuspend(token); }
        if (!initialized) { return; }
        var value = String(token || '');
        if (value.length > SUSPEND_LIMIT) { value = value.slice(0, SUSPEND_LIMIT); }
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
        set(prefix + 'id', String(record.id).slice(0, 4000));
        set(prefix + 'type', record.type);
        set(prefix + 'timestamp', timestamp(now()));
        set(prefix + 'learner_response', core.formatResponse(record.response, 'scorm2004').slice(0, RESPONSE_LIMIT));
        if (record.correctAnswer) {
          set(prefix + 'correct_responses.0.pattern', core.formatResponse(record.correctAnswer, 'scorm2004').slice(0, RESPONSE_LIMIT));
        }
        set(prefix + 'result', record.correct === true ? 'correct' : (record.correct === false ? 'incorrect' : 'neutral'));
        if (typeof record.weighting === 'number') { set(prefix + 'weighting', record.weighting); }
        scheduleCommit();
      },
      setScore: function (score) {
        if (fallback) { return fallback.setScore(score); }
        if (!initialized) { return; }
        var min = typeof score.min === 'number' ? score.min : 0;
        var max = typeof score.max === 'number' ? score.max : 100;
        var scaled = typeof score.scaled === 'number' ? score.scaled : (max > min ? (score.raw - min) / (max - min) : 0);
        set('cmi.score.scaled', Math.round(Math.min(1, Math.max(-1, scaled)) * 10000) / 10000);
        set('cmi.score.raw', Math.round(score.raw * 100) / 100);
        set('cmi.score.min', min);
        set('cmi.score.max', max);
        scheduleCommit();
      },
      setStatus: function (status) {
        if (fallback) { return fallback.setStatus(status); }
        if (!initialized) { return; }
        set('cmi.completion_status', status.completion === 'completed' ? 'completed' : 'incomplete');
        set('cmi.success_status', status.success === 'passed' || status.success === 'failed' ? status.success : 'unknown');
        if (typeof status.progress === 'number') {
          set('cmi.progress_measure', Math.round(Math.min(1, Math.max(0, status.progress)) * 10000) / 10000);
        }
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
        set('cmi.exit', 'suspend');
        flush();
        try { api.Terminate(''); } catch (err) { /* LMS unreachable */ }
      }
    };
    return runtime;
  }

  root.SentientiaRuntimes = root.SentientiaRuntimes || {};
  root.SentientiaRuntimes.scorm2004 = createRuntime;
  if (typeof module === 'object' && module.exports) { module.exports = createRuntime; }
}(typeof window !== 'undefined' ? window : globalThis));
