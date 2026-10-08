// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Switchboard AMD module — Phase A0 (2026-05-14).
 *
 * Audit fix 2026-05-15 — switched from ES module (`export const init`) to
 * traditional AMD `define()` format. Without a Grunt build of amd/build/,
 * Moodle 5.x's runtime transpilation does not always wrap ES exports in
 * an AMD `define()`, leaving RequireJS unable to locate the module ("No
 * define call for local_sentientia_platform/switchboard"). That single error
 * propagated and prevented later page JS (including Bootstrap 5 tabs on
 * /admin/search.php) from initialising, so site-admin tabs looked
 * clickable but did not actually switch panes. The AMD wrapper here
 * works with or without a build step.
 *
 * Behaviour:
 *   - Toggle buttons set a per-row tri-state (on / off / default)
 *   - Modified rows are visually flagged + tracked in a Map
 *   - "Apply" opens a core/modal_save_cancel dialog listing every change for confirmation
 *     (Moodle 5.3 compat FX-20: it used window.bootstrap.Modal, which exists in no tree, so the
 *     confirmation was silently skipped and the changes were submitted directly)
 *   - Submit serialises the Map to JSON and POSTs the form
 *   - "Discard" reverts every modification by reloading the page
 *
 * Security: every dynamic DOM node is created via createElement +
 * textContent, never innerHTML, even with seemingly-controlled
 * values. Defence-in-depth — a regression elsewhere that lets a flag
 * key contain a backtick or `<` can't escalate to XSS here.
 *
 * @module local_sentientia_platform/switchboard
 */
define(['core/modal_save_cancel', 'core/modal_events', 'core/notification'], function(ModalSaveCancel, ModalEvents, Notification) {

    var SELECTORS = {
        form: '[data-region="switchboard-form"]',
        flagRow: '[data-flag-row]',
        changesInput: '[data-changes-payload]',
        reasonInput: '[data-reason-input]',
        banner: '[data-banner-region]',
        changeCount: '[data-change-count]',
        changeList: '[data-change-list]',
        reasonField: '#ap-reason-field'
    };

    // Map<string, string> flag_key → new tri_state
    var pendingChanges = new Map();

    /**
     * Update the per-row visual state to reflect a tri-state choice and
     * remember the change. Calling with the row's current registered
     * value (i.e. "no change") removes the entry from pendingChanges
     * and clears the modified marker.
     */
    function applyToggle(row, newState) {
        var originalState = row.dataset.flagTriState;
        var key = row.dataset.flagKey;

        // Update button-group active states.
        row.querySelectorAll('button[data-action^="toggle-"]').forEach(function(btn) {
            btn.classList.remove('active');
        });
        var map = {on: 'toggle-on', off: 'toggle-off', default: 'toggle-default'};
        var target = row.querySelector('button[data-action="' + map[newState] + '"]');
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
        var banner = document.querySelector(SELECTORS.banner);
        if (!banner) {
            return;
        }
        var count = pendingChanges.size;
        banner.style.display = count > 0 ? 'block' : 'none';
        var counter = banner.querySelector(SELECTORS.changeCount);
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
        var li = document.createElement('li');
        li.className = 'mb-2';

        var codeNode = document.createElement('code');
        codeNode.textContent = key;
        li.appendChild(codeNode);
        li.appendChild(document.createTextNode(': '));

        var oldBadge = document.createElement('span');
        oldBadge.className = 'badge bg-light text-dark';
        oldBadge.textContent = oldState;
        li.appendChild(oldBadge);
        li.appendChild(document.createTextNode(' '));

        var arrow = document.createElement('i');
        arrow.className = 'fa fa-arrow-right fa-xs';
        arrow.setAttribute('aria-hidden', 'true');
        li.appendChild(arrow);
        li.appendChild(document.createTextNode(' '));

        var newBadge = document.createElement('span');
        newBadge.className = 'badge bg-primary';
        newBadge.textContent = newState;
        li.appendChild(newBadge);

        return li;
    }

    /**
     * Build the "review changes" dialog — a list of every change with old → new
     * state plus an optional reason — and submit when the admin confirms.
     *
     * Every node is created with createElement + textContent and only then serialised for the dialog body, so
     * a flag key that contains markup stays inert text (the same defence as the rest of this module). The
     * dialog is core/modal_save_cancel, which exists unchanged on Moodle 5.1, 5.2 and 5.3. If it cannot be
     * built nothing is submitted: the review step is the safeguard, so it must not be skipped silently.
     */
    function openApplyModal() {
        var list = document.createElement('ul');
        list.className = 'list-unstyled mb-3';
        list.setAttribute('data-change-list', '');
        pendingChanges.forEach(function(newState, key) {
            var row = document.querySelector('[data-flag-key="' + (window.CSS ? CSS.escape(key) : key) + '"]');
            var oldState = row ? row.dataset.flagTriState : 'unknown';
            list.appendChild(buildChangeListItem(key, oldState, newState));
        });

        var intro = document.createElement('p');
        intro.className = 'text-muted small';
        intro.textContent = 'Every change is recorded in the audit log. An optional reason helps future-you remember why.';

        var label = document.createElement('label');
        label.className = 'form-label';
        label.setAttribute('for', 'ap-reason-field');
        label.textContent = 'Reason (optional)';

        var reason = document.createElement('input');
        reason.type = 'text';
        reason.className = 'form-control';
        reason.id = 'ap-reason-field';
        reason.maxLength = 255;
        reason.placeholder = 'e.g. Disabling AI assistant during vendor outage';

        var body = document.createElement('div');
        body.appendChild(intro);
        body.appendChild(list);
        body.appendChild(label);
        body.appendChild(reason);

        ModalSaveCancel.create({
            title: 'Review changes',
            body: body.innerHTML,
            removeOnClose: true
        }).then(function(modal) {
            modal.setSaveButtonText('Apply changes');
            // The reason field is read inside submitChanges() while the dialog is still in the DOM: the
            // save event fires before the dialog is closed and removed.
            modal.getRoot().on(ModalEvents.save, function() {
                submitChanges();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Serialise the pending changes to JSON, copy the reason field into
     * the hidden input, submit the form. Mustache's POST handler in
     * admin/switchboard.php applies each change via feature_flags::set().
     */
    function submitChanges() {
        var form = document.querySelector(SELECTORS.form);
        var payload = document.querySelector(SELECTORS.changesInput);
        var reasonField = document.querySelector(SELECTORS.reasonField);
        var reasonHidden = document.querySelector(SELECTORS.reasonInput);
        if (!form || !payload) {
            return;
        }

        var obj = {};
        pendingChanges.forEach(function(v, k) {
            obj[k] = v;
        });
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
        var form = document.querySelector(SELECTORS.form);
        if (!form) {
            return;
        }
        form.addEventListener('click', function(event) {
            var btn = event.target.closest('button[data-action]');
            if (!btn) {
                return;
            }
            var action = btn.dataset.action;
            var row = btn.closest(SELECTORS.flagRow);

            if (action === 'toggle-on' && row) {
                applyToggle(row, 'on');
            } else if (action === 'toggle-off' && row) {
                applyToggle(row, 'off');
            } else if (action === 'toggle-default' && row) {
                applyToggle(row, 'default');
            }
        });

        // Banner buttons live outside the form.
        document.addEventListener('click', function(event) {
            var btn = event.target.closest('button[data-action]');
            if (!btn) {
                return;
            }
            if (btn.dataset.action === 'open-apply') {
                openApplyModal();
            } else if (btn.dataset.action === 'discard') {
                discardChanges();
            }
        });

        refreshBanner();
    }

    // Public API — RequireJS init() is the entry point called from
    // the Switchboard Mustache template.
    return {
        init: function() {
            bind();
        }
    };
});
