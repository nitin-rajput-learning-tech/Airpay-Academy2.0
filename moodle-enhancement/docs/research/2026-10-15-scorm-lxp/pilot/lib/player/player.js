/* player.js - the Sentientia static course player (one bundle for every course and every standard).
 *
 * Reads window.SENTIENTIA_COURSE (written by the compiler into course-data.js), picks the runtime
 * adapter for the package's standard (SentientiaRuntimes.scorm12 | scorm2004 | cmi5), restores the
 * learner's saved state (compact token, see core.js) and renders slides, deterministic branching
 * scenarios and quizzes with captions, a language switcher and RTL mirroring.
 *
 * Security: every piece of course text reaches the DOM through textContent / setAttribute only.
 * No innerHTML, no eval, no external requests (audio and captions are relative package files).
 */
(function () {
  'use strict';

  var core = window.SentientiaCore;
  var DATA = window.SENTIENTIA_COURSE;
  var doc = DATA.doc;
  var blocks = doc.blocks || [];
  var rtlLangs = DATA.rtl || [];
  var summaryIndex = blocks.length;

  var DEFAULT_UI = {
    previous: 'Previous', next: 'Next', finish: 'Finish', language: 'Language', contents: 'Contents',
    skip: 'Skip to content', check: 'Check answers', tryAgain: 'Try again', continueLabel: 'Continue',
    correct: 'Correct', incorrect: 'Not quite', partlyCorrect: 'Partly correct', score: 'Score', yourScore: 'Your score',
    passmark: 'Pass mark', passed: 'Passed', failed: 'Not passed yet', completed: 'Completed', inProgress: 'In progress',
    notStarted: 'Not started', transcript: 'Transcript', captionsOn: 'Captions on', captionsOff: 'Captions off',
    listen: 'Listen to the narration', slide: 'Slide', scenario: 'Scenario', quiz: 'Quiz', summary: 'Course summary',
    position: 'Step {$a} of {$b}', decisions: 'Decisions made: {$a}', scenarioScore: 'Scenario score: {$a} of {$b}',
    outcomeAcceptable: 'Good choice', outcomeRecoverable: 'You can recover', outcomeIrrecoverable: 'Serious mistake',
    outcomeSuccess: 'Best outcome', outcomePartial: 'Partly handled', outcomeFailure: 'Scenario failed',
    yourPath: 'Your path', restart: 'Restart the scenario', matchSelect: 'Choose a match', noAnswer: 'No answer',
    courseResult: 'Course result', blocksVisited: 'Steps visited', quizResult: 'You scored {$a} percent',
    fallbackLang: 'Shown in {$a} (not translated yet)', stateTrimmed: 'Some saved progress details were trimmed to fit the LMS limit.',
    previewBuild: 'PREVIEW BUILD - not approved for learners', trackingOn: 'Progress is being saved', mockNote: 'Mock audio is silent in this build.',
    selectLang: 'Select language', ofLabel: 'of'
  };

  // ---- state -------------------------------------------------------------------------------------

  var state = { lang: DATA.baseLang, blockIndex: 0, visited: [], scenarios: {}, quizzes: {} };
  var runtime = null;
  var runtimeInfo = null;
  var masteryScore = typeof DATA.mastery === 'number' ? DATA.mastery : 70;
  var transient = { pending: null, results: {} };
  var captionsOn = true;
  var finished = false;
  var trimmedNotice = false;
  var saveSerial = 0;
  var tocPrepared = false;

  for (var vi = 0; vi < blocks.length; vi++) { state.visited.push(false); }

  // ---- small helpers -----------------------------------------------------------------------------

  function byId(id) { return document.getElementById(id); }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        var value = attrs[key];
        if (value === null || value === undefined || value === false) { return; }
        if (key === 'text') { node.textContent = value; }
        else if (key === 'class') { node.className = value; }
        else if (key.indexOf('on') === 0 && typeof value === 'function') { node.addEventListener(key.slice(2), value); }
        else { node.setAttribute(key, value === true ? '' : String(value)); }
      });
    }
    (children || []).forEach(function (child) { if (child) { node.appendChild(child); } });
    return node;
  }

  function clear(node) { while (node.firstChild) { node.removeChild(node.firstChild); } }

  function ui(key, params) {
    var table = (DATA.ui && DATA.ui[state.lang]) || {};
    var base = (DATA.ui && DATA.ui[DATA.baseLang]) || {};
    var text = table[key] || base[key] || DEFAULT_UI[key] || key;
    return params ? core.formatTemplate(text, params) : text;
  }

  /* Resolve an i18n map: current language, else the base language (flagged), else any value. */
  function tx(map) {
    if (!map || typeof map !== 'object') { return { text: '', lang: state.lang, fallback: false }; }
    if (typeof map[state.lang] === 'string' && map[state.lang] !== '') { return { text: map[state.lang], lang: state.lang, fallback: false }; }
    if (typeof map[DATA.baseLang] === 'string') { return { text: map[DATA.baseLang], lang: DATA.baseLang, fallback: state.lang !== DATA.baseLang }; }
    var keys = Object.keys(map);
    return keys.length ? { text: String(map[keys[0]]), lang: keys[0], fallback: true } : { text: '', lang: state.lang, fallback: false };
  }

  function t(map) { return tx(map).text; }

  function textNode(tag, map, attrs) {
    var resolved = tx(map);
    var all = attrs || {};
    if (resolved.fallback) { all.lang = resolved.lang; }
    all.text = resolved.text;
    return el(tag, all);
  }

  function mediaFor(set) {
    if (!set) { return null; }
    return set[state.lang] || set[DATA.baseLang] || null;
  }

  function captionPath(block, node) {
    var holder = node || block;
    var sets = holder.captions || {};
    return sets[state.lang] || sets[DATA.baseLang] || null;
  }

  function langInfo(tag) {
    for (var i = 0; i < DATA.langs.length; i++) { if (DATA.langs[i].tag === tag) { return DATA.langs[i]; } }
    return null;
  }

  function currentDir() { return core.dirForLang(state.lang, rtlLangs); }

  function labelForBlock(block) {
    if (block.type === 'slide') { return ui('slide'); }
    if (block.type === 'scenario') { return ui('scenario'); }
    if (block.type === 'quiz') { return ui('quiz'); }
    return block.type;
  }

  function blockStatus(index) {
    var block = blocks[index];
    if (block.type === 'scenario') {
      var result = core.scoreScenario(block, state.scenarios[block.id] || []);
      if (result.finished) { return result.pass !== null && !result.passed ? 'failed' : 'done'; }
      return (state.scenarios[block.id] || []).length ? 'in-progress' : (state.visited[index] ? 'in-progress' : 'not-started');
    }
    if (block.type === 'quiz') {
      var quizState = state.quizzes[block.id];
      if (quizState && quizState.submitted) {
        var scored = core.scoreQuiz(block, quizState.answers);
        return scored.percent >= (block.pass_percent || masteryScore) ? 'passed' : 'failed';
      }
      return state.visited[index] ? 'in-progress' : 'not-started';
    }
    return state.visited[index] ? 'done' : 'not-started';
  }

  function statusLabel(status) {
    return { done: ui('completed'), passed: ui('passed'), failed: ui('failed'), 'in-progress': ui('inProgress'), 'not-started': ui('notStarted') }[status] || '';
  }

  // ---- persistence --------------------------------------------------------------------------------

  function snapshot() {
    return { lang: state.lang, blockIndex: state.blockIndex, visited: state.visited, scenarios: state.scenarios, quizzes: state.quizzes };
  }

  function limitFor() {
    return DATA.standard === 'scorm12' ? core.LIMITS.scorm12 : core.LIMITS.scorm2004;
  }

  function persist() {
    if (!runtime) { return; }
    var serial = ++saveSerial;
    var fitted = core.fitToken(snapshot(), limitFor());
    var token = fitted.token;
    if (fitted.truncated.length && !trimmedNotice) {
      trimmedNotice = true;
      showNotice(ui('stateTrimmed'));
    }
    function store(value) {
      if (serial !== saveSerial) { return; }
      runtime.setSuspend(value);
      runtime.setLocation(state.blockIndex < blocks.length ? blocks[state.blockIndex].id : 'summary');
      runtime.commit();
    }
    if (DATA.standard === 'scorm12' && token.length > core.LIMITS.scorm12Precompress) {
      core.compressToken(token).then(function (zipped) { store(zipped.length < token.length ? zipped : token); }, function () { store(token); });
    } else {
      store(token);
    }
  }

  function currentMastery() { return masteryScore; }

  function syncResult() {
    if (!runtime) { return null; }
    var result = core.courseScore(doc, state, currentMastery());
    runtime.setScore({ raw: result.percent, min: 0, max: 100, scaled: result.percent / 100 });
    var progress = blocks.length ? result.visitedCount / blocks.length : 0;
    if (result.complete) {
      runtime.setStatus({ completion: 'completed', success: result.passed ? 'passed' : 'failed', progress: 1 });
    } else {
      runtime.setStatus({ completion: 'incomplete', success: 'unknown', progress: progress });
    }
    return result;
  }

  function changed() {
    syncResult();
    persist();
  }

  // ---- notice, audio, captions ----------------------------------------------------------------------

  function showNotice(text) {
    var notice = byId('notice');
    if (!text) { notice.hidden = true; notice.textContent = ''; return; }
    notice.textContent = text;
    notice.hidden = false;
  }

  function buildAudio(audioSet, capPath, narrationText) {
    var media = mediaFor(audioSet);
    if (!media) { return null; }
    var cues = [];
    if (capPath && DATA.vtt && DATA.vtt[capPath]) { cues = core.parseVtt(DATA.vtt[capPath]); }
    var audio = el('audio', { controls: true, preload: 'none', src: media.path, 'aria-label': ui('listen') });
    var captions = el('div', { class: 'captions', 'aria-hidden': 'true', hidden: !captionsOn || !cues.length });
    var toggle = el('button', {
      type: 'button', class: 'btn btn-secondary btn-small', 'aria-pressed': captionsOn ? 'true' : 'false',
      text: captionsOn ? ui('captionsOn') : ui('captionsOff'),
      onclick: function () {
        captionsOn = !captionsOn;
        toggle.setAttribute('aria-pressed', captionsOn ? 'true' : 'false');
        toggle.textContent = captionsOn ? ui('captionsOn') : ui('captionsOff');
        captions.hidden = !captionsOn || !cues.length;
      }
    });
    audio.addEventListener('timeupdate', function () {
      var cue = core.cueAt(cues, audio.currentTime * 1000);
      captions.textContent = cue ? cue.text : '';
    });
    var tools = el('div', { class: 'audio-tools' }, [cues.length ? toggle : null]);
    var children = [audio, captions, tools];
    if (narrationText) {
      children.push(el('details', { class: 'transcript' }, [
        el('summary', { text: ui('transcript') }),
        el('p', { text: narrationText })
      ]));
    }
    return el('div', { class: 'audio-panel' }, children);
  }

  // ---- slides -----------------------------------------------------------------------------------------

  function renderSlide(block, root) {
    root.appendChild(el('p', { class: 'block-kind', text: ui('slide') }));
    root.appendChild(textNode('h2', block.title, { id: 'block-heading', tabindex: '-1' }));
    var layout = block.layout || 'bullets';
    var points = block.points || [];
    if (layout === 'statement' && points.length) {
      root.appendChild(textNode('p', points[0], { class: 'statement' }));
    } else if (points.length) {
      var list = el('ul', { class: 'points' });
      points.forEach(function (point) { list.appendChild(textNode('li', point)); });
      root.appendChild(list);
    }
    var narration = tx(block.narration);
    var audio = buildAudio(block.audio, captionPath(block, null), narration.text);
    if (audio) { root.appendChild(audio); }
    else if (narration.text) {
      root.appendChild(el('details', { class: 'transcript' }, [el('summary', { text: ui('transcript') }), el('p', { text: narration.text })]));
    }
    if (narration.fallback) { root.appendChild(el('p', { class: 'muted', text: ui('fallbackLang', { a: langName(narration.lang) }) })); }
  }

  function langName(tag) {
    var info = langInfo(tag);
    return info ? info.name : tag;
  }

  // ---- scenarios ----------------------------------------------------------------------------------------

  function correctEdgeId(node) {
    var edges = node.edges || [];
    for (var i = 0; i < edges.length; i++) { if (edges[i].correct === true) { return edges[i].id; } }
    return '';
  }

  function outcomeLabel(outcome) {
    return { acceptable: ui('outcomeAcceptable'), recoverable: ui('outcomeRecoverable'), irrecoverable: ui('outcomeIrrecoverable') }[outcome] || '';
  }

  function terminalLabel(outcome) {
    return { success: ui('outcomeSuccess'), partial: ui('outcomePartial'), failure: ui('outcomeFailure') }[outcome] || '';
  }

  function choose(block, node, edge, index) {
    var path = (state.scenarios[block.id] = state.scenarios[block.id] || []);
    path.push(edge.id);
    var chain = [];
    var next = core.nodeById(block, edge.to);
    var guard = 0;
    while (next && next.kind === 'info' && guard++ < 50) { chain.push(next); next = core.nodeById(block, next.next); }
    transient.pending = { scenarioId: block.id, edge: edge, node: node, chain: chain, step: -1 };
    if (runtime) {
      runtime.recordInteraction({
        id: block.id + '.' + node.id,
        type: 'choice',
        response: { type: 'c', value: edge.id },
        correctAnswer: correctEdgeId(node) ? { type: 'c', value: correctEdgeId(node) } : null,
        correct: edge.correct === true ? true : (edge.correct === false ? false : null),
        description: { en: node.prompt && (node.prompt[DATA.baseLang] || '') },
        name: { en: block.title && (block.title[DATA.baseLang] || '') }
      });
    }
    changed();
    render(false);
  }

  function crumbs(block) {
    var path = state.scenarios[block.id] || [];
    if (!path.length) { return null; }
    var result = core.scoreScenario(block, path);
    var list = el('ol', { class: 'crumbs', 'aria-label': ui('yourPath') });
    result.edgesTaken.forEach(function (step, i) {
      list.appendChild(el('li', { class: 'crumb outcome-' + (step.edge.outcome || 'none'), text: (i + 1) + '. ' + outcomeLabel(step.edge.outcome) }));
    });
    return list;
  }

  function renderScenario(block, root, index) {
    root.appendChild(el('p', { class: 'block-kind', text: ui('scenario') }));
    root.appendChild(textNode('h2', block.title, { id: 'block-heading', tabindex: '-1' }));
    var path = state.scenarios[block.id] || [];
    var result = core.scoreScenario(block, path);
    if (!path.length && !transient.pending) { root.appendChild(textNode('p', block.intro, { class: 'prompt' })); }
    var pending = transient.pending && transient.pending.scenarioId === block.id ? transient.pending : null;
    var area = el('div', { 'aria-live': 'polite' });
    root.appendChild(area);

    if (pending) {
      if (pending.step < 0) {
        var edge = pending.edge;
        area.appendChild(el('div', { class: 'consequence outcome-' + (edge.outcome || 'none') }, [
          el('p', { class: 'consequence-label', text: outcomeLabel(edge.outcome) }),
          textNode('p', edge.consequence || { en: '' })
        ]));
      } else {
        var shown = pending.chain[pending.step];
        area.appendChild(el('div', { class: 'consequence' }, [textNode('p', shown.prompt)]));
        var infoAudio = buildAudio(shown.audio, captionPath(block, shown), null);
        if (infoAudio) { area.appendChild(infoAudio); }
      }
      area.appendChild(el('div', { class: 'actions' }, [
        el('button', {
          type: 'button', class: 'btn btn-primary', text: ui('continueLabel'),
          onclick: function () {
            pending.step++;
            if (pending.step >= pending.chain.length) { transient.pending = null; }
            render(false);
          }
        })
      ]));
    } else {
      var node = core.followInfo(block, core.nodeById(block, result.currentNodeId));
      if (node && node.kind === 'decision') {
        var character = null;
        (block.characters || []).forEach(function (c) { if (c.id === node.character) { character = c; } });
        if (character) { area.appendChild(textNode('p', character.name, { class: 'speaker' })); }
        area.appendChild(textNode('p', node.prompt, { class: 'prompt' }));
        var promptAudio = buildAudio(node.audio, captionPath(block, node), null);
        if (promptAudio) { area.appendChild(promptAudio); }
        var choices = el('ul', { class: 'choices' });
        (node.edges || []).forEach(function (edge) {
          var item = el('li');
          var resolved = tx(edge.text);
          item.appendChild(el('button', {
            type: 'button', class: 'choice', text: resolved.text, lang: resolved.fallback ? resolved.lang : null,
            onclick: function () { choose(block, node, edge, index); }
          }));
          choices.appendChild(item);
        });
        area.appendChild(choices);
      } else if (node && node.kind === 'terminal') {
        var outcomeClass = node.terminal_outcome === 'success' ? 'acceptable' : (node.terminal_outcome === 'partial' ? 'recoverable' : 'irrecoverable');
        area.appendChild(el('div', { class: 'consequence outcome-' + outcomeClass }, [
          el('p', { class: 'consequence-label', text: terminalLabel(node.terminal_outcome) }),
          textNode('p', node.prompt)
        ]));
        var passMark = result.pass !== null ? ' - ' + ui('passmark') + ': ' + result.pass : '';
        area.appendChild(el('p', { class: 'result-score', text: ui('scenarioScore', { a: result.clamped, b: result.max }) + passMark }));
        area.appendChild(el('div', { class: 'actions' }, [
          el('button', {
            type: 'button', class: 'btn btn-secondary', text: ui('restart'),
            onclick: function () { state.scenarios[block.id] = []; transient.pending = null; changed(); render(false); }
          })
        ]));
      }
    }
    var list = crumbs(block);
    var meta = el('div', { class: 'scenario-meta' }, [
      el('span', { text: ui('decisions', { a: path.length }) }),
      el('span', { text: ui('scenarioScore', { a: result.clamped, b: result.max }) })
    ]);
    root.appendChild(meta);
    if (list) { root.appendChild(list); }
  }

  // ---- quizzes ------------------------------------------------------------------------------------------

  function quizState(block) {
    if (!state.quizzes[block.id]) { state.quizzes[block.id] = { submitted: false, answers: {} }; }
    return state.quizzes[block.id];
  }

  function orderedOptions(question, block) {
    var options = question.options || [];
    if (block.shuffle && question.type !== 'truefalse') { return core.seededShuffle(options, question.id); }
    return options;
  }

  function readAnswers(block, form) {
    var answers = {};
    (block.questions || []).forEach(function (question) {
      var group = form.querySelectorAll('[data-q="' + question.id + '"]');
      if (question.type === 'multichoice' || question.type === 'truefalse') {
        var chosen = '';
        Array.prototype.forEach.call(group, function (input) { if (input.checked) { chosen = input.value; } });
        answers[question.id] = { type: question.type === 'truefalse' ? 't' : 'c', value: chosen };
      } else if (question.type === 'multiresponse') {
        var values = [];
        Array.prototype.forEach.call(group, function (input) { if (input.checked) { values.push(input.value); } });
        answers[question.id] = { type: 'm', value: values };
      } else if (question.type === 'match') {
        var pairs = [];
        Array.prototype.forEach.call(group, function (select) { if (select.value) { pairs.push([select.getAttribute('data-left'), select.value]); } });
        answers[question.id] = { type: 'x', value: pairs };
      }
    });
    return answers;
  }

  function renderQuiz(block, root, index) {
    root.appendChild(el('p', { class: 'block-kind', text: ui('quiz') }));
    root.appendChild(textNode('h2', block.title, { id: 'block-heading', tabindex: '-1' }));
    if (block.intro) { root.appendChild(textNode('p', block.intro, { class: 'prompt' })); }
    var qs = quizState(block);
    var form = el('form', { novalidate: true });
    form.addEventListener('submit', function (event) { event.preventDefault(); });
    (block.questions || []).forEach(function (question, qi) {
      var saved = qs.answers[question.id];
      var graded = qs.submitted ? core.scoreQuestion(question, saved) : null;
      var fieldset = el('fieldset', { class: 'question' });
      fieldset.appendChild(el('legend', { class: 'stem' }, [textNode('span', question.stem)]));
      if (question.type === 'match') {
        var rights = core.seededShuffle(question.pairs || [], question.id + ':right');
        var chosen = {};
        ((saved && saved.value) || []).forEach(function (pair) { chosen[pair[0]] = pair[1]; });
        (question.pairs || []).forEach(function (pair) {
          var selectId = 'q-' + question.id + '-' + pair.id;
          var select = el('select', { id: selectId, 'data-q': question.id, 'data-left': pair.id, disabled: qs.submitted, 'aria-label': t(pair.left) });
          select.appendChild(el('option', { value: '', text: ui('matchSelect') }));
          rights.forEach(function (right) {
            var opt = el('option', { value: right.id, text: t(right.right) });
            if (chosen[pair.id] === right.id) { opt.selected = true; }
            select.appendChild(opt);
          });
          var rowClass = 'match-row' + (graded ? (graded.detail[pair.id] ? ' option-correct' : ' option-incorrect') : '');
          fieldset.appendChild(el('div', { class: rowClass }, [
            el('label', { for: selectId }, [textNode('span', pair.left)]), select
          ]));
        });
      } else {
        var multi = question.type === 'multiresponse';
        var chosenValues = saved ? (multi ? saved.value || [] : [saved.value]) : [];
        orderedOptions(question, block).forEach(function (option) {
          var inputId = 'q-' + question.id + '-' + option.id;
          var input = el('input', {
            type: multi ? 'checkbox' : 'radio', id: inputId, name: 'q-' + question.id, value: option.id,
            'data-q': question.id, disabled: qs.submitted, checked: chosenValues.indexOf(option.id) >= 0
          });
          var wasChosen = chosenValues.indexOf(option.id) >= 0;
          var cls = 'option';
          if (graded) {
            if (option.correct === true) { cls += ' option-correct'; }
            else if (wasChosen) { cls += ' option-incorrect'; }
          }
          var labelBody = [textNode('span', option.text)];
          if (graded && option.feedback && (wasChosen || option.correct === true)) {
            labelBody.push(textNode('span', option.feedback, { class: 'option-feedback' }));
          }
          fieldset.appendChild(el('div', { class: cls }, [input, el('label', { for: inputId }, labelBody)]));
        });
      }
      if (graded) {
        var cls2 = graded.correct ? 'result-correct' : (graded.earned > 0 ? 'result-correct' : 'result-incorrect');
        var label = graded.correct ? ui('correct') : (graded.earned > 0 ? ui('partlyCorrect') : ui('incorrect'));
        fieldset.appendChild(el('p', { class: 'question-result ' + cls2, text: label + ' (' + graded.earned + '/' + graded.max + ')' }));
      }
      form.appendChild(fieldset);
    });
    root.appendChild(form);

    var panel = el('div', { class: 'result-panel', 'aria-live': 'polite', hidden: !qs.submitted });
    root.appendChild(panel);
    if (qs.submitted) {
      var result = core.scoreQuiz(block, qs.answers);
      var pass = result.percent >= (block.pass_percent || masteryScore);
      panel.className = 'result-panel ' + (pass ? 'result-passed' : 'result-failed');
      panel.appendChild(el('p', { class: 'result-score', text: ui('quizResult', { a: result.percent }) }));
      panel.appendChild(el('p', { text: (pass ? ui('passed') : ui('failed')) + ' - ' + ui('passmark') + ': ' + (block.pass_percent || masteryScore) + '%' }));
    }
    var actions = el('div', { class: 'actions' });
    if (!qs.submitted) {
      actions.appendChild(el('button', {
        type: 'button', class: 'btn btn-primary', text: ui('check'),
        onclick: function () {
          var answers = readAnswers(block, form);
          qs.answers = answers;
          qs.submitted = true;
          (block.questions || []).forEach(function (question) {
            var graded = core.scoreQuestion(question, answers[question.id]);
            if (runtime) {
              runtime.recordInteraction({
                id: question.id,
                type: core.interactionType(question),
                response: answers[question.id] || core.emptyAnswer(question),
                correctAnswer: core.correctAnswer(question),
                correct: graded.correct,
                weighting: question.points,
                description: { en: question.stem && (question.stem[DATA.baseLang] || '') }
              });
            }
          });
          changed();
          render(false);
        }
      }));
    } else {
      actions.appendChild(el('button', {
        type: 'button', class: 'btn btn-secondary', text: ui('tryAgain'),
        onclick: function () { state.quizzes[block.id] = { submitted: false, answers: {} }; changed(); render(false); }
      }));
    }
    root.appendChild(actions);
  }

  // ---- summary ------------------------------------------------------------------------------------------

  function renderSummary(root) {
    root.appendChild(el('p', { class: 'block-kind', text: ui('summary') }));
    root.appendChild(el('h2', { id: 'block-heading', tabindex: '-1', text: ui('courseResult') }));
    var result = core.courseScore(doc, state, currentMastery());
    var panel = el('div', { class: 'result-panel ' + (result.complete ? (result.passed ? 'result-passed' : 'result-failed') : ''), 'aria-live': 'polite' });
    panel.appendChild(el('p', { class: 'result-score', text: ui('yourScore') + ': ' + result.percent + '%' }));
    panel.appendChild(el('p', { text: result.complete ? (result.passed ? ui('passed') : ui('failed')) : ui('inProgress') }));
    panel.appendChild(el('p', { text: ui('passmark') + ': ' + result.mastery + '%  -  ' + ui('blocksVisited') + ': ' + result.visitedCount + ' ' + ui('ofLabel') + ' ' + result.blockCount }));
    root.appendChild(panel);
    var table = el('table', { class: 'summary-table' });
    table.appendChild(el('thead', null, [el('tr', null, [
      el('th', { scope: 'col', text: ui('contents') }), el('th', { scope: 'col', text: ui('score') }), el('th', { scope: 'col', text: ui('completed') })
    ])]));
    var body = el('tbody');
    result.components.forEach(function (component) {
      var block = null;
      blocks.forEach(function (b) { if (b.id === component.id) { block = b; } });
      body.appendChild(el('tr', null, [
        el('td', { text: block ? t(block.title) : component.id }),
        el('td', { text: component.percent + '%' }),
        el('td', { text: component.done ? ui('completed') : ui('inProgress') })
      ]));
    });
    table.appendChild(body);
    root.appendChild(table);
    if (DATA.preview) { root.appendChild(el('p', { class: 'muted', text: ui('previewBuild') })); }
  }

  // ---- shell: toc, nav, render --------------------------------------------------------------------------

  function renderToc() {
    var list = byId('toc');
    clear(list);
    blocks.forEach(function (block, index) {
      var status = blockStatus(index);
      var link = el('button', {
        type: 'button', class: 'toc-link', 'aria-current': index === state.blockIndex ? 'true' : null,
        onclick: function () { go(index); }
      }, [
        el('span', { class: 'toc-icon', 'aria-hidden': 'true', text: block.type === 'slide' ? 'S' : (block.type === 'scenario' ? 'D' : 'Q') }),
        el('span', { class: 'toc-title', text: t(block.title) }),
        el('span', { class: 'toc-state state-' + status, text: statusLabel(status) })
      ]);
      list.appendChild(el('li', { class: 'toc-item' }, [link]));
    });
    var done = state.blockIndex === summaryIndex;
    list.appendChild(el('li', { class: 'toc-item' }, [el('button', {
      type: 'button', class: 'toc-link', 'aria-current': done ? 'true' : null, onclick: function () { go(summaryIndex); }
    }, [el('span', { class: 'toc-icon', 'aria-hidden': 'true', text: '=' }), el('span', { class: 'toc-title', text: ui('summary') })])]));
  }

  function renderNav() {
    var prev = byId('prev-btn');
    var next = byId('next-btn');
    prev.textContent = ui('previous');
    prev.disabled = state.blockIndex <= 0;
    var last = state.blockIndex >= blocks.length - 1;
    next.textContent = state.blockIndex >= summaryIndex ? ui('finish') : (last ? ui('summary') : ui('next'));
    next.disabled = false;
    byId('position').textContent = ui('position', { a: Math.min(state.blockIndex + 1, blocks.length + 1), b: blocks.length + 1 });
    var visited = state.visited.filter(Boolean).length;
    var percent = blocks.length ? Math.round(visited / blocks.length * 100) : 0;
    byId('progress-fill').style.width = percent + '%';
    byId('progress').setAttribute('aria-valuenow', String(percent));
    byId('progress').setAttribute('aria-label', ui('blocksVisited') + ' ' + percent + '%');
  }

  function renderChrome() {
    var htmlEl = document.documentElement;
    htmlEl.setAttribute('lang', state.lang);
    htmlEl.setAttribute('dir', currentDir());
    var title = t(doc.course.title);
    document.title = title;
    byId('course-title').textContent = title;
    byId('lang-label').textContent = ui('language');
    byId('toc-heading').textContent = ui('contents');
    if (!tocPrepared) {
      tocPrepared = true;
      try { byId('toc-details').open = window.matchMedia('(min-width: 861px)').matches; } catch (err) { /* keep default */ }
    }
    byId('skip-link').textContent = ui('skip');
    var badge = byId('tracking-badge');
    badge.textContent = runtime && runtime.tracked ? runtime.label : (runtime ? runtime.label : '');
    badge.className = 'badge ' + (runtime && runtime.tracked ? 'badge-ok' : 'badge-warn');
  }

  function render(moveFocus) {
    renderChrome();
    var root = byId('block');
    clear(root);
    if (state.blockIndex >= summaryIndex) { renderSummary(root); }
    else {
      var block = blocks[state.blockIndex];
      state.visited[state.blockIndex] = true;
      if (block.type === 'slide') { renderSlide(block, root); }
      else if (block.type === 'scenario') { renderScenario(block, root, state.blockIndex); }
      else if (block.type === 'quiz') { renderQuiz(block, root, state.blockIndex); }
      else { root.appendChild(el('p', { text: block.type })); }
    }
    renderToc();
    renderNav();
    if (moveFocus) {
      var heading = byId('block-heading');
      if (heading) { heading.focus(); }
    }
  }

  function go(index) {
    if (index < 0) { index = 0; }
    if (index > summaryIndex) { index = summaryIndex; }
    if (index === state.blockIndex && index < summaryIndex) { render(true); return; }
    state.blockIndex = index;
    transient.pending = null;
    render(true);
    changed();
    window.scrollTo(0, 0);
  }

  function populateLanguages() {
    var select = byId('lang-select');
    clear(select);
    DATA.langs.forEach(function (info) {
      var label = info.name + (info.state && info.state !== 'approved' ? ' (' + info.state.replace(/_/g, ' ') + ')' : '');
      var option = el('option', { value: info.tag, text: label, lang: info.tag });
      if (info.tag === state.lang) { option.selected = true; }
      select.appendChild(option);
    });
    select.addEventListener('change', function () {
      state.lang = select.value;
      transient.pending = null;
      render(false);
      changed();
    });
    if (DATA.langs.length < 2) { byId('lang-select').hidden = true; byId('lang-label').hidden = true; }
  }

  // ---- boot ---------------------------------------------------------------------------------------------

  function chooseInitialLang(restored) {
    var tags = DATA.langs.map(function (info) { return info.tag; });
    var wanted = [];
    if (restored) { wanted.push(restored); }
    try {
      var match = /[?&]lang=([A-Za-z-]+)/.exec(window.location.search || '');
      if (match) { wanted.push(match[1]); }
    } catch (err) { /* ignore */ }
    if (DATA.packageMode === 'per_language') { wanted.unshift(DATA.langs[0].tag); }
    (navigator.languages || [navigator.language || '']).forEach(function (l) { wanted.push(String(l).split('-')[0]); });
    wanted.push(DATA.baseLang);
    for (var i = 0; i < wanted.length; i++) { if (tags.indexOf(wanted[i]) >= 0) { return wanted[i]; } }
    return tags[0];
  }

  function restore(token) {
    if (!token) { return Promise.resolve(null); }
    return core.decompressToken(token).then(function (plain) {
      try { return core.decodeState(plain, blocks.length); } catch (err) { return null; }
    }, function () { return null; });
  }

  function onFinishRequest() {
    if (finished) { return; }
    finished = true;
    if (runtime) { syncResult(); persist(); runtime.finish(); }
  }

  function boot() {
    var factory = window.SentientiaRuntimes && window.SentientiaRuntimes[DATA.standard];
    runtime = factory ? factory({ courseId: DATA.courseId, win: window }) : core.createLocalFallback(null, 'sentientia:' + DATA.courseId, 'not tracked (runtime missing)');
    var started = runtime.init();
    var ready = started && started.then ? started : Promise.resolve({});
    ready.then(function (info) {
      runtimeInfo = info || {};
      if (typeof runtimeInfo.masteryScore === 'number' && runtimeInfo.masteryScore > 0) { masteryScore = runtimeInfo.masteryScore; }
      return restore(runtimeInfo.suspend);
    }).then(function (saved) {
      var restoredLang = null;
      if (saved) {
        restoredLang = saved.lang;
        state.visited = saved.visited;
        state.scenarios = saved.scenarios;
        state.quizzes = saved.quizzes;
        state.blockIndex = Math.max(0, Math.min(saved.blockIndex, summaryIndex));
      }
      state.lang = chooseInitialLang(restoredLang);
      populateLanguages();
      byId('prev-btn').addEventListener('click', function () { go(state.blockIndex - 1); });
      byId('next-btn').addEventListener('click', function () {
        if (state.blockIndex >= summaryIndex) { onFinishRequest(); showNotice(ui('trackingOn')); return; }
        go(state.blockIndex + 1);
      });
      if (DATA.preview) { showNotice(ui('previewBuild')); }
      render(false);
      syncResult();
      persist();
      window.addEventListener('pagehide', onFinishRequest);
      document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && runtime) { runtime.commit(); } });
      window.SentientiaPlayer = { state: function () { return JSON.parse(JSON.stringify(snapshot())); }, runtime: runtime, go: go };
    });
  }

  boot();
}());
