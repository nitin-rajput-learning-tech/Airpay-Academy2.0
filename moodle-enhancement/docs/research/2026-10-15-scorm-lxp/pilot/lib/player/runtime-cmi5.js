/* runtime-cmi5.js - cmi5 (xAPI) adapter behind the common runtime interface.
 *
 * Launch: index.html?endpoint=...&fetch=...&actor=...&registration=...&activityId=...
 *   1. POST fetch URL once -> { "auth-token": ... }  (used as Authorization: Basic <token>)
 *   2. GET  State "LMS.LaunchData" -> contextTemplate, launchMode, masteryScore, moveOn
 *   3. GET  State "sentientia.suspend" (our own resume document) and send "initialized"
 * Statements: initialized, completed, passed | failed, terminated (cmi5 defined: category cmi5,
 * registration, sessionid extension, masteryScore extension on passed/failed) plus plain
 * xAPI "answered" statements for every scenario edge and quiz answer (cmi5 allowed).
 * "terminated" goes through navigator.sendBeacon using the xAPI alternate request syntax
 * (POST ?method=POST with form fields) because sendBeacon cannot set headers; fetch keepalive
 * is the fallback. Failed statements are queued, retried with backoff and persisted.
 * Without launch parameters the player runs with a local-storage fallback labelled "not tracked".
 */
(function (root) {
  'use strict';
  var core = root.SentientiaCore || (typeof require === 'function' ? require('./core.js') : null);
  var XAPI_VERSION = '1.0.3';
  var STATE_ID = 'sentientia.suspend';
  var MAX_ATTEMPTS = 6;
  var SAVE_DEBOUNCE_MS = 500;
  var VERBS = {
    initialized: 'http://adlnet.gov/expapi/verbs/initialized',
    completed: 'http://adlnet.gov/expapi/verbs/completed',
    passed: 'http://adlnet.gov/expapi/verbs/passed',
    failed: 'http://adlnet.gov/expapi/verbs/failed',
    terminated: 'http://adlnet.gov/expapi/verbs/terminated',
    answered: 'http://adlnet.gov/expapi/verbs/answered'
  };
  var CMI5_CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/cmi5';
  var MOVEON_CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/moveon';
  var EXT_SESSION = 'https://w3id.org/xapi/cmi5/context/extensions/sessionid';
  var EXT_MASTERY = 'https://w3id.org/xapi/cmi5/context/extensions/masteryscore';
  var INTERACTION_TYPE = 'http://adlnet.gov/expapi/activities/cmi.interaction';

  function safeStorage() {
    try { return typeof localStorage !== 'undefined' ? localStorage : null; } catch (err) { return null; }
  }

  function parseLaunch(search) {
    var params = {};
    String(search || '').replace(/^\?/, '').split('&').forEach(function (pair) {
      if (!pair) { return; }
      var eq = pair.indexOf('=');
      var key = decodeURIComponent(eq < 0 ? pair : pair.slice(0, eq)).replace(/\+/g, ' ');
      var value = decodeURIComponent((eq < 0 ? '' : pair.slice(eq + 1)).replace(/\+/g, ' '));
      params[key] = value;
    });
    var actor = null;
    try { actor = params.actor ? JSON.parse(params.actor) : null; } catch (err) { actor = null; }
    return {
      endpoint: params.endpoint || '',
      fetch: params.fetch || '',
      actor: actor,
      registration: params.registration || '',
      activityId: params.activityId || ''
    };
  }

  function uuid() {
    try {
      if (typeof crypto !== 'undefined' && crypto.randomUUID) { return crypto.randomUUID(); }
    } catch (err) { /* fall through */ }
    var template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
    return template.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });
  }

  function isoDuration(ms) {
    return 'PT' + (Math.round(ms / 10) / 100).toFixed(2) + 'S';
  }

  function encodeQuery(params) {
    return Object.keys(params).map(function (key) {
      return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');
  }

  function createRuntime(options) {
    options = options || {};
    var fetchImpl = options.fetch || (typeof fetch === 'function' ? fetch.bind(root) : null);
    var beacon = options.sendBeacon || (typeof navigator !== 'undefined' && navigator.sendBeacon ? navigator.sendBeacon.bind(navigator) : null);
    var search = options.search !== undefined ? options.search : (typeof location !== 'undefined' ? location.search : '');
    var storage = options.storage !== undefined ? options.storage : safeStorage();
    var timers = options.timers || { setTimeout: function (fn, ms) { return setTimeout(fn, ms); }, clearTimeout: function (id) { clearTimeout(id); } };
    var clock = options.now || function () { return new Date(); };
    var courseId = options.courseId || 'course';
    var launch = parseLaunch(search);
    var fallback = null;
    var auth = null;
    var launchData = {};
    var template = {};
    var state = { token: '', location: '' };
    var sessionStart = clock().getTime();
    var sentCompleted = false;
    var sentResult = false;
    var finished = false;
    var queue = [];
    var sending = false;
    var saveTimer = null;
    var lastScore = null;
    var queueKey = 'sentientia:cmi5:queue:' + (launch.registration || 'none');

    function endpointUrl(path, params) {
      var base = launch.endpoint.replace(/\/?$/, '/');
      var query = params ? encodeQuery(params) : '';
      return base + path + (query ? '?' + query : '');
    }

    function headers() {
      return { 'Authorization': auth, 'X-Experience-API-Version': XAPI_VERSION, 'Content-Type': 'application/json' };
    }

    function stateParams(stateId) {
      return {
        stateId: stateId,
        activityId: launch.activityId,
        agent: JSON.stringify(launch.actor),
        registration: launch.registration
      };
    }

    function normalMode() {
      return !launchData.launchMode || launchData.launchMode === 'Normal';
    }

    function baseContext(defined, moveOn) {
      var context = JSON.parse(JSON.stringify(template || {}));
      context.registration = launch.registration;
      context.contextActivities = context.contextActivities || {};
      var categories = (context.contextActivities.category || []).slice();
      if (defined) { categories.push({ id: CMI5_CATEGORY, objectType: 'Activity' }); }
      if (moveOn) { categories.push({ id: MOVEON_CATEGORY, objectType: 'Activity' }); }
      if (categories.length) { context.contextActivities.category = categories; }
      context.extensions = context.extensions || {};
      if (!context.extensions[EXT_SESSION] && launchData.sessionId) { context.extensions[EXT_SESSION] = launchData.sessionId; }
      return context;
    }

    function definedStatement(verb, result, moveOn) {
      var statement = {
        id: uuid(),
        actor: launch.actor,
        verb: { id: VERBS[verb], display: { 'en-US': verb } },
        object: { id: launch.activityId, objectType: 'Activity' },
        context: baseContext(true, moveOn),
        timestamp: clock().toISOString()
      };
      if (result) { statement.result = result; }
      return statement;
    }

    function persistQueue() {
      try {
        if (storage) { storage.setItem(queueKey, JSON.stringify(queue.map(function (item) { return item.stmt; }))); }
      } catch (err) { /* ignore */ }
    }

    function loadQueue() {
      try {
        var raw = storage ? storage.getItem(queueKey) : null;
        if (raw) {
          JSON.parse(raw).forEach(function (stmt) { queue.push({ stmt: stmt, attempts: 0 }); });
        }
      } catch (err) { /* ignore */ }
    }

    function postStatement(stmt) {
      return fetchImpl(endpointUrl('statements'), {
        method: 'POST',
        headers: headers(),
        body: JSON.stringify(stmt),
        keepalive: true
      }).then(function (response) {
        if (!response.ok) { throw new Error('LRS returned HTTP ' + response.status); }
        return response;
      });
    }

    function flush() {
      if (sending || !queue.length || !auth || !fetchImpl) { return; }
      sending = true;
      var item = queue[0];
      postStatement(item.stmt).then(function () {
        queue.shift();
        persistQueue();
        sending = false;
        flush();
      }).catch(function (err) {
        sending = false;
        item.attempts++;
        if (item.attempts >= MAX_ATTEMPTS) {
          queue.shift();
          persistQueue();
          if (typeof console !== 'undefined') { console.warn('cmi5: dropped statement after retries', err); }
          flush();
          return;
        }
        timers.setTimeout(flush, Math.min(30000, 1000 * Math.pow(2, item.attempts - 1)));
      });
    }

    function enqueue(stmt) {
      queue.push({ stmt: stmt, attempts: 0 });
      persistQueue();
      flush();
    }

    function saveState() {
      if (saveTimer) { return; }
      saveTimer = timers.setTimeout(function () {
        saveTimer = null;
        writeState();
      }, SAVE_DEBOUNCE_MS);
    }

    function writeState() {
      if (!auth || !fetchImpl) { return Promise.resolve(); }
      return fetchImpl(endpointUrl('activities/state', stateParams(STATE_ID)), {
        method: 'PUT',
        headers: headers(),
        body: JSON.stringify(state),
        keepalive: true
      }).catch(function () { /* retried on the next change */ });
    }

    function useFallback(label) {
      fallback = core.createLocalFallback(storage, 'sentientia:' + courseId + ':cmi5', label);
      runtime.tracked = false;
      runtime.label = label;
      return fallback.init();
    }

    function sendTerminated() {
      var stmt = definedStatement('terminated', { duration: isoDuration(clock().getTime() - sessionStart) }, false);
      var sent = false;
      if (beacon && typeof URLSearchParams === 'function') {
        var form = new URLSearchParams();
        form.set('Authorization', auth);
        form.set('X-Experience-API-Version', XAPI_VERSION);
        form.set('Content-Type', 'application/json');
        form.set('content', JSON.stringify(stmt));
        try { sent = beacon(endpointUrl('statements', { method: 'POST' }), form); } catch (err) { sent = false; }
      }
      if (!sent && fetchImpl) {
        postStatement(stmt).catch(function () { /* page is unloading */ });
      }
      return stmt;
    }

    var runtime = {
      standard: 'cmi5',
      tracked: false,
      label: 'not tracked',
      queue: queue,
      init: function () {
        if (!launch.endpoint || !launch.fetch || !launch.actor || !launch.registration || !launch.activityId || !fetchImpl) {
          return useFallback('not tracked (no cmi5 launch parameters)');
        }
        return fetchImpl(launch.fetch, { method: 'POST' })
          .then(function (response) { return response.json(); })
          .then(function (body) {
            if (!body || !body['auth-token']) { throw new Error('fetch URL returned no auth-token'); }
            auth = 'Basic ' + body['auth-token'];
            return fetchImpl(endpointUrl('activities/state', stateParams('LMS.LaunchData')), { headers: headers() });
          })
          .then(function (response) { return response.ok ? response.json() : {}; })
          .then(function (data) {
            launchData = data || {};
            template = launchData.contextTemplate || {};
            var ext = template.extensions || {};
            launchData.sessionId = ext[EXT_SESSION];
            return fetchImpl(endpointUrl('activities/state', stateParams(STATE_ID)), { headers: headers() });
          })
          .then(function (response) { return response.ok ? response.json() : null; })
          .then(function (saved) {
            if (saved && typeof saved === 'object') {
              state = { token: String(saved.token || ''), location: String(saved.location || '') };
            }
            loadQueue();
            enqueue(definedStatement('initialized', null, false));
            runtime.tracked = true;
            runtime.label = 'cmi5';
            return {
              tracked: true,
              label: 'cmi5',
              masteryScore: typeof launchData.masteryScore === 'number' ? launchData.masteryScore * 100 : null,
              suspend: state.token,
              location: state.location,
              entry: state.token ? 'resume' : 'ab-initio',
              launchMode: launchData.launchMode || 'Normal',
              moveOn: launchData.moveOn || null
            };
          })
          .catch(function (err) {
            return useFallback('not tracked (cmi5 launch failed: ' + (err && err.message ? err.message : 'error') + ')');
          });
      },
      setLocation: function (id) {
        if (fallback) { return fallback.setLocation(id); }
        state.location = String(id || '');
        saveState();
      },
      setSuspend: function (token) {
        if (fallback) { return fallback.setSuspend(token); }
        state.token = String(token || '');
        saveState();
      },
      getSuspend: function () {
        return fallback ? fallback.getSuspend() : state.token;
      },
      recordInteraction: function (record) {
        if (fallback) { return fallback.recordInteraction(record); }
        var definition = { type: INTERACTION_TYPE, interactionType: record.type };
        if (record.correctAnswer) { definition.correctResponsesPattern = [core.formatResponse(record.correctAnswer, 'scorm2004')]; }
        if (record.description) { definition.description = record.description; }
        if (record.name) { definition.name = record.name; }
        if (record.choices) { definition.choices = record.choices; }
        var result = { response: core.formatResponse(record.response, 'scorm2004') };
        if (record.correct === true || record.correct === false) { result.success = record.correct; }
        enqueue({
          id: uuid(),
          actor: launch.actor,
          verb: { id: VERBS.answered, display: { 'en-US': 'answered' } },
          object: { id: launch.activityId + '/' + record.id, objectType: 'Activity', definition: definition },
          result: result,
          context: baseContext(false, false),
          timestamp: clock().toISOString()
        });
      },
      setScore: function (score) {
        if (fallback) { return fallback.setScore(score); }
        lastScore = score;
      },
      setStatus: function (status) {
        if (fallback) { return fallback.setStatus(status); }
        if (!auth || !normalMode()) { return; }
        var duration = isoDuration(clock().getTime() - sessionStart);
        if (status.completion === 'completed' && !sentCompleted) {
          sentCompleted = true;
          enqueue(definedStatement('completed', { completion: true, duration: duration }, true));
        }
        if ((status.success === 'passed' || status.success === 'failed') && !sentResult) {
          sentResult = true;
          var result = { success: status.success === 'passed', duration: duration };
          if (lastScore) {
            var min = typeof lastScore.min === 'number' ? lastScore.min : 0;
            var max = typeof lastScore.max === 'number' ? lastScore.max : 100;
            var scaled = typeof lastScore.scaled === 'number' ? lastScore.scaled : (max > min ? (lastScore.raw - min) / (max - min) : 0);
            result.score = { scaled: Math.round(scaled * 10000) / 10000, raw: lastScore.raw, min: min, max: max };
          }
          var stmt = definedStatement(status.success, result, true);
          if (typeof launchData.masteryScore === 'number') { stmt.context.extensions[EXT_MASTERY] = launchData.masteryScore; }
          enqueue(stmt);
        }
      },
      commit: function () {
        if (fallback) { return fallback.commit(); }
        flush();
      },
      finish: function () {
        if (fallback) { return fallback.finish(); }
        if (!auth || finished) { return; }
        finished = true;
        if (saveTimer) { timers.clearTimeout(saveTimer); saveTimer = null; }
        writeState();
        flush();
        return sendTerminated();
      }
    };
    return runtime;
  }

  createRuntime.parseLaunch = parseLaunch;
  root.SentientiaRuntimes = root.SentientiaRuntimes || {};
  root.SentientiaRuntimes.cmi5 = createRuntime;
  if (typeof module === 'object' && module.exports) { module.exports = createRuntime; }
}(typeof window !== 'undefined' ? window : globalThis));
