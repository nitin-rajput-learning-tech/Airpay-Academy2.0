/* proto.js - shared shell for the six prototypes: sprite, sidebar/topbar/bottom bar, theme and direction
 * toggles, toasts, dialogs and the "About this prototype" drawer. No dependencies, no build.
 * URL switches for evidence capture: ?theme=dark|light  ?dir=rtl  ?lang=ar  ?nofonts=1
 * All text reaches the DOM through textContent / setAttribute; icons are parsed from a static sprite map with DOMParser.
 */
(function () {
  'use strict';

  var ICONS = {
    home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    book: '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    wand: '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    chart: '<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>',
    branch: '<path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
    check: '<path d="M20 6L9 17l-5-5"/>',
    alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    x: '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
    sun: '<circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>',
    moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    play: '<polygon points="5 3 19 12 5 21 5 3"/>',
    pause: '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>',
    right: '<path d="M9 18l6-6-6-6"/>',
    left: '<path d="M15 18l-6-6 6-6"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    trash: '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    undo: '<path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
    flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
    mic: '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>',
    msg: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    refresh: '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    menu: '<path d="M3 12h18M3 6h18M3 18h18"/>',
    zap: '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
    up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    down: '<path d="M12 5v14M19 12l-7 7-7-7"/>',
    award: '<circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    fire: '<path d="M12 2s4 4 4 8a4 4 0 0 1-8 0c0-2 1-3 1-3s-3 2-3 6a6 6 0 0 0 12 0c0-6-6-9-6-11z"/>'
  };

  var SCREENS = [
    { id: 'learner-home', file: '01-learner-home.html', icon: 'home', label: 'Learner home', short: 'Home' },
    { id: 'course-player', file: '02-course-player.html', icon: 'book', label: 'Course player', short: 'Learn' },
    { id: 'authoring-studio', file: '03-authoring-studio.html', icon: 'wand', label: 'Authoring studio', short: 'Create' },
    { id: 'localisation-review', file: '04-localisation-review.html', icon: 'globe', label: 'Localisation review', short: 'Review' },
    { id: 'admin-analytics', file: '05-admin-analytics.html', icon: 'chart', label: 'Admin analytics', short: 'Insights' },
    { id: 'scenario-editor', file: '06-scenario-editor.html', icon: 'branch', label: 'Scenario editor', short: 'Scenario' }
  ];

  // Language set (STUDY section 7.1) with review state for the pilot course.
  var LANGS = [
    { tag: 'en', name: 'English', dir: 'ltr', state: 'approved' },
    { tag: 'hi', name: 'हिन्दी (Hindi)', dir: 'ltr', state: 'in_review' },
    { tag: 'mr', name: 'मराठी (Marathi)', dir: 'ltr', state: 'machine' },
    { tag: 'kn', name: 'ಕನ್ನಡ (Kannada)', dir: 'ltr', state: 'machine' },
    { tag: 'ta', name: 'தமிழ் (Tamil)', dir: 'ltr', state: 'in_translation' },
    { tag: 'te', name: 'తెలుగు (Telugu)', dir: 'ltr', state: 'machine' },
    { tag: 'bn', name: 'বাংলা (Bengali)', dir: 'ltr', state: 'machine' },
    { tag: 'gu', name: 'ગુજરાતી (Gujarati)', dir: 'ltr', state: 'pending' },
    { tag: 'ml', name: 'മലയാളം (Malayalam)', dir: 'ltr', state: 'pending' },
    { tag: 'pa', name: 'ਪੰਜਾਬੀ (Punjabi)', dir: 'ltr', state: 'pending' },
    { tag: 'or', name: 'ଓଡ଼ିଆ (Odia)', dir: 'ltr', state: 'pending' },
    { tag: 'ur', name: 'اردو (Urdu)', dir: 'rtl', state: 'pending' },
    { tag: 'ar', name: 'العربية (Arabic)', dir: 'rtl', state: 'reassigned' }
  ];
  var STATE_LABEL = { pending: 'Pending', machine: 'Machine draft', in_translation: 'In translation', translated: 'Translated', in_review: 'In review', reassigned: 'Reassigned', approved: 'Approved' };

  var params = new URLSearchParams(location.search);
  var root = document.documentElement;

  function guard(fn) { try { return fn(); } catch (e) { return null; } }

  function el(tag, attrs, kids) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) { return; }
      if (k === 'text') { node.textContent = v; }
      else if (k === 'class') { node.className = v; }
      else if (k.slice(0, 2) === 'on' && typeof v === 'function') { node.addEventListener(k.slice(2), v); }
      else { node.setAttribute(k, v === true ? '' : String(v)); }
    });
    (kids || []).forEach(function (kid) { if (kid) { node.appendChild(typeof kid === 'string' ? document.createTextNode(kid) : kid); } });
    return node;
  }

  function icon(name, extra) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'i' + (extra ? ' ' + extra : ''));
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    var parsed = new DOMParser().parseFromString('<svg xmlns="http://www.w3.org/2000/svg">' + (ICONS[name] || '') + '</svg>', 'image/svg+xml');
    Array.prototype.forEach.call(parsed.documentElement.childNodes, function (n) { svg.appendChild(document.importNode(n, true)); });
    return svg;
  }

  // ---- theme + direction -----------------------------------------------------------------------

  function applyTheme(mode) {
    if (mode === 'auto') { root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme', mode); }
    guard(function () { sessionStorage.setItem('sp-theme', mode); });
    var btn = document.getElementById('theme-btn');
    if (btn) {
      var dark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      btn.setAttribute('aria-label', 'Theme: ' + mode + '. Switch theme');
      btn.replaceChildren(icon(dark ? 'moon' : 'sun'), el('span', { class: 'sr-only', text: 'Theme ' + mode }));
    }
  }
  function currentTheme() { return root.getAttribute('data-theme') || 'auto'; }
  function applyDir(dir) {
    root.setAttribute('dir', dir);
    root.setAttribute('lang', dir === 'rtl' ? 'ar' : 'en');
    guard(function () { sessionStorage.setItem('sp-dir', dir); });
    var btn = document.getElementById('dir-btn');
    if (btn) { btn.setAttribute('aria-pressed', dir === 'rtl' ? 'true' : 'false'); btn.textContent = dir === 'rtl' ? 'RTL (ar)' : 'LTR (en)'; }
    document.dispatchEvent(new CustomEvent('proto:dir', { detail: dir }));
  }

  // ---- toast + dialog + live region ------------------------------------------------------------

  var toastHost;
  function toast(message) {
    if (!toastHost) {
      toastHost = el('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' });
      document.body.appendChild(toastHost);
    }
    var t = el('div', { class: 'toast', text: message });
    toastHost.appendChild(t);
    setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 4200);
  }

  function dialog(opts) {
    var d = el('dialog', { 'aria-labelledby': 'dlg-title' });
    var title = el('h2', { id: 'dlg-title', text: opts.title });
    var body = el('div', { class: 'stack' }, [].concat(opts.body || []));
    var actions = el('div', { class: 'actions' });
    (opts.actions || []).forEach(function (a) {
      actions.appendChild(el('button', {
        type: 'button', class: 'btn ' + (a.kind || ''), text: a.label,
        onclick: function () { if (!a.keepOpen) { d.close(); } if (a.onClick) { a.onClick(d); } }
      }));
    });
    d.appendChild(title); d.appendChild(body); d.appendChild(actions);
    d.addEventListener('close', function () { d.remove(); });
    document.body.appendChild(d);
    d.showModal();
    return d;
  }

  // ---- shell -------------------------------------------------------------------------------------

  function buildShell(cfg) {
    var main = document.querySelector('main');
    var app = el('div', { class: 'app' });
    var nav = el('nav', { class: 'nav', 'aria-label': 'Prototype screens' });
    SCREENS.forEach(function (s) {
      nav.appendChild(el('a', { href: s.file, 'aria-current': s.id === cfg.screen ? 'page' : null }, [icon(s.icon), s.label]));
    });
    nav.appendChild(el('a', { href: 'index.html' }, [icon('list'), 'All prototypes']));
    var sidebar = el('aside', { class: 'sidebar' }, [
      el('div', { class: 'brand' }, [el('span', { class: 'brand-mark', 'aria-hidden': 'true' }), 'Sentientia']),
      nav,
      el('div', { class: 'sidebar-foot', text: 'Prototype. Synthetic data from the UPI-dispute pilot course. Nothing here is live.' })
    ]);
    var topbar = el('header', { class: 'topbar' }, [
      el('h1', { text: cfg.title }),
      el('div', { class: 'tools' }, [
        el('button', { type: 'button', id: 'dir-btn', class: 'btn sm', 'aria-pressed': 'false', 'aria-label': 'Toggle right-to-left layout', onclick: function () { applyDir(root.getAttribute('dir') === 'rtl' ? 'ltr' : 'rtl'); } }),
        el('button', { type: 'button', id: 'theme-btn', class: 'btn sm icon', onclick: function () { applyTheme({ auto: 'light', light: 'dark', dark: 'auto' }[currentTheme()]); } }),
        el('button', { type: 'button', class: 'btn sm', id: 'about-btn', 'aria-expanded': 'false', 'aria-controls': 'about-drawer', onclick: toggleAbout }, [icon('info'), 'About'])
      ])
    ]);
    var banner = el('div', { class: 'proto-banner', role: 'note' }, [icon('info'), 'Clickable prototype - synthetic data, mock AI and mock audio. Nothing is saved or sent.']);
    var mainCol = el('div', { class: 'main' }, [topbar, banner, main]);
    app.appendChild(sidebar); app.appendChild(mainCol);
    var bottom = el('nav', { class: 'bottombar', 'aria-label': 'Prototype screens' });
    SCREENS.slice(0, 4).forEach(function (s) {
      bottom.appendChild(el('a', { href: s.file, 'aria-current': s.id === cfg.screen ? 'page' : null }, [icon(s.icon), s.short]));
    });
    bottom.appendChild(el('button', { type: 'button', onclick: moreDialog }, [icon('menu'), 'More']));
    document.body.insertBefore(app, document.body.firstChild);
    document.body.appendChild(bottom);
    main.id = 'main'; main.setAttribute('tabindex', '-1');
    document.body.insertBefore(el('a', { href: '#main', class: 'sr-only', text: 'Skip to content' }), document.body.firstChild);
    document.body.appendChild(buildAbout(cfg.about || {}));
  }

  function moreDialog() {
    var links = SCREENS.slice(4).concat([{ file: 'index.html', label: 'All prototypes', icon: 'list' }]).map(function (s) {
      return el('a', { class: 'btn', href: s.file }, [icon(s.icon), s.label]);
    });
    dialog({ title: 'More screens', body: links.concat([
      el('button', { type: 'button', class: 'btn', onclick: function () { applyTheme({ auto: 'light', light: 'dark', dark: 'auto' }[currentTheme()]); } }, [icon('sun'), 'Switch theme']),
      el('button', { type: 'button', class: 'btn', onclick: function () { applyDir(root.getAttribute('dir') === 'rtl' ? 'ltr' : 'rtl'); } }, [icon('globe'), 'Switch LTR / RTL'])
    ]), actions: [{ label: 'Close', kind: 'primary' }] });
  }

  var aboutEl;
  function buildAbout(about) {
    function list(items) { return el('ul', null, (items || []).map(function (t) { return el('li', { text: t }); })); }
    aboutEl = el('aside', { class: 'about-drawer', id: 'about-drawer', hidden: true, role: 'complementary', 'aria-label': 'About this prototype' }, [
      el('div', { class: 'row spread' }, [el('h2', { text: 'About this prototype' }), el('button', { type: 'button', class: 'btn sm icon', 'aria-label': 'Close', onclick: toggleAbout }, [icon('x')])]),
      el('p', { class: 'muted small', text: about.purpose || '' }),
      el('h3', { text: 'Things to click' }), list(about.interactions),
      el('h3', { text: 'Patterns demonstrated' }), list(about.patterns),
      el('h3', { text: 'Maps to in the real theme' }), list(about.maps),
      el('h3', { text: 'Not built in this prototype' }), list(about.notBuilt)
    ]);
    return aboutEl;
  }
  function toggleAbout() {
    var open = aboutEl.hidden;
    aboutEl.hidden = !open;
    document.getElementById('about-btn').setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { aboutEl.querySelector('button').focus(); }
  }

  function loadFonts() {
    if (params.get('nofonts')) { return; }
    window.addEventListener('load', function () {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap';
      document.head.appendChild(link);
    });
  }

  function mount(cfg) {
    document.title = cfg.title + ' - Sentientia prototype';
    var initialTheme = params.get('theme') || guard(function () { return sessionStorage.getItem('sp-theme'); }) || 'auto';
    var initialDir = params.get('dir') || guard(function () { return sessionStorage.getItem('sp-dir'); }) || 'ltr';
    buildShell(cfg);
    applyTheme(initialTheme);
    applyDir(initialDir);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aboutEl && !aboutEl.hidden) { toggleAbout(); } });
    loadFonts();
  }

  window.PROTO = { mount: mount, el: el, icon: icon, toast: toast, dialog: dialog, SCREENS: SCREENS, LANGS: LANGS, STATE_LABEL: STATE_LABEL, applyDir: applyDir, guard: guard };
}());
