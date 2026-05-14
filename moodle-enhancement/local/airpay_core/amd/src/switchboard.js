// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Switchboard AMD module — Phase A0 (2026-05-14).
 *
 * Behaviour:
 *   - Toggle buttons set a per-row tri-state (on / off / default)
 *   - Modified rows are visually flagged + tracked in a Map
 *   - "Apply" opens a modal listing every change for confirmation
 *   - Submit serialises the Map to JSON and POSTs the form
 *   - "Discard" reverts every modification by reloading the page
 *
 * Security: every dynamic DOM node is created via createElement +
 * textContent, never innerHTML, even with seemingly-controlled
 * values. Defence-in-depth — a regression elsewhere that lets a flag
 * key contain a backtick or `<` can't escalate to XSS here.
 *
 * @module local_airpay_core/switchboard
 */

const SELECTORS = {
    form: '[data-region="switchboard-form"]',
    flagRow: '[data-flag-row]',
    changesInput: '[data-changes-payload]',
    reasonInput: '[data-reason-input]',
    banner: '[data-banner-region]',
    changeCount: '[data-change-count]',
    changeList: '[data-change-list]',
    reasonField: '#ap-reason-field',
};

/** @type {Map<string, string>} flag_key → new tri_state */
const pendingChanges = new Map();

/**
 * Update the per-row visual state to reflect a tri-state choice and
 * remember the change. Calling with the row's current registered
 * value (i.e. "no change") removes the entry from pendingChanges
 * and clears the modified marker.
 */
function applyToggle(row, newState) {
    const originalState = row.dataset.flagTriState;
    const key = row.dataset.flagKey;

    // Update button-group active states.
    row.querySelectorAll('button[data-action^="toggle-"]').forEach((btn) => {
        btn.classList.remove('active');
    });
    const map = {on: 'toggle-on', off: 'toggle-off', default: 'toggle-default'};
    const target = row.querySelector(`button[data-action="${map[newState]}"]`);
    if (target) {
        target.classList.add('active');
    }

    if (newState === originalState) {
        pendingChanges.delete(key);
        row.removeAttribute('data-flag-modified');
    } else {
        pendingChanges.set(key, newState);
        row.dataset.flagModified = '1';
    }
    refreshBanner();
}

/**
 * Show/hide the sticky pending-changes banner. Banner appears when
 * there's at least one change pending; updates the count badge.
 */
function refreshBanner() {
    const banner = document.querySelector(SELECTORS.banner);
    if (!banner) {
        return;
    }
    const count = pendingChanges.size;
    banner.style.display = count > 0 ? 'block' : 'none';
    const counter = banner.querySelector(SELECTORS.changeCount);
    if (counter) {
        counter.textContent = String(count);
    }
}

/**
 * Build a single change list-item via DOM API.
 * Defensive: every user-derived text goes via textContent, every
 * structural element is created via createElement.
 */
function buildChangeListItem(key, oldState, newState) {
    const li = document.createElement('li');
    li.className = 'mb-2';

    const codeNode = document.createElement('code');
    codeNode.textContent = key;
    li.appendChild(codeNode);
    li.appendChild(document.createTextNode(': '));

    const oldBadge = document.createElement('span');
    oldBadge.className = 'badge bg-light text-dark';
    oldBadge.textContent = oldState;
    li.appendChild(oldBadge);
    li.appendChild(document.createTextNode(' '));

    const arrow = document.createElement('i');
    arrow.className = 'fa fa-arrow-right fa-xs';
    arrow.setAttribute('aria-hidden', 'true');
    li.appendChild(arrow);
    li.appendChild(document.createTextNode(' '));

    const newBadge = document.createElement('span');
    newBadge.className = 'badge bg-primary';
    newBadge.textContent = newState;
    li.appendChild(newBadge);

    return li;
}

/**
 * Build the "review changes" modal body — a list of every change with
 * old → new state, then submit on confirmation.
 */
function openApplyModal() {
    const list = document.querySelector(SELECTORS.changeList);
    if (!list) {
        return;
    }
    // Clear by removing each child — safer than innerHTML = ''.
    while (list.firstChild) {
        list.removeChild(list.firstChild);
    }

    for (const [key, newState] of pendingChanges) {
        const row = document.querySelector(`[data-flag-key="${CSS.escape(key)}"]`);
        const oldState = row ? row.dataset.flagTriState : 'unknown';
        list.appendChild(buildChangeListItem(key, oldState, newState));
    }

    // eslint-disable-next-line no-undef
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalEl = document.getElementById('ap-apply-modal');
        // eslint-disable-next-line no-undef
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } else {
        // Bootstrap not loaded — submit directly (fallback).
        submitChanges();
    }
}

/**
 * Serialise the pending changes to JSON, copy the reason field into
 * the hidden input, submit the form. Mustache's POST handler in
 * admin/switchboard.php applies each change via feature_flags::set().
 */
function submitChanges() {
    const form = document.querySelector(SELECTORS.form);
    const payload = document.querySelector(SELECTORS.changesInput);
    const reasonField = document.querySelector(SELECTORS.reasonField);
    const reasonHidden = document.querySelector(SELECTORS.reasonInput);
    if (!form || !payload) {
        return;
    }

    const obj = {};
    for (const [k, v] of pendingChanges) {
        obj[k] = v;
    }
    payload.value = JSON.stringify(obj);

    if (reasonField && reasonHidden) {
        reasonHidden.value = reasonField.value || '';
    }

    form.submit();
}

/**
 * Discard all pending changes — simplest reliable implementation is
 * a full page reload. Server state hasn't changed yet so no harm.
 */
function discardChanges() {
    if (pendingChanges.size === 0) {
        return;
    }
    if (window.confirm('Discard all ' + pendingChanges.size + ' pending change(s)?')) {
        window.location.reload();
    }
}

/**
 * Wire up event delegation. We use one delegated click handler on
 * the form, then dispatch by data-action. Cheaper than per-button
 * listeners; cleaner to reason about.
 */
function bind() {
    const form = document.querySelector(SELECTORS.form);
    if (!form) {
        return;
    }
    form.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-action]');
        if (!btn) {
            return;
        }
        const action = btn.dataset.action;
        const row = btn.closest(SELECTORS.flagRow);

        if (action === 'toggle-on' && row) {
            applyToggle(row, 'on');
        } else if (action === 'toggle-off' && row) {
            applyToggle(row, 'off');
        } else if (action === 'toggle-default' && row) {
            applyToggle(row, 'default');
        }
    });

    // Banner buttons live outside the form.
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-action]');
        if (!btn) {
            return;
        }
        if (btn.dataset.action === 'open-apply') {
            openApplyModal();
        } else if (btn.dataset.action === 'confirm-apply') {
            submitChanges();
        } else if (btn.dataset.action === 'discard') {
            discardChanges();
        }
    });

    refreshBanner();
}

/**
 * Entry point — called by the Mustache template.
 */
export const init = () => {
    bind();
};
