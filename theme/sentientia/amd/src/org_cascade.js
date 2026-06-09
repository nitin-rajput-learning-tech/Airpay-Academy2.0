// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Org hierarchy cascade — the reusable 5-level org filter used by every
 * admin list page (Manage Users, Manage Courses, Manage Programs,
 * Classroom, Exams, Recompletion, etc.).
 *
 * BizLMS parity port (2026-05-15 audit): the legacy implementation lived
 * at `local/costcenter/amd/src/newcostcenter.js::changeElement` and used
 * Moodle's autocomplete form element with `ajax: 'local_costcenter/
 * form-options-selector'`. This version is plainer — vanilla `<select>`
 * elements bound by `[data-airpay-org-cascade]` markers, talking to the
 * `local_sentientia_org_list_children` web service we just added.
 *
 * Markup contract (one per level):
 *   <select data-airpay-org-cascade
 *           data-cascade-depth="1"
 *           data-cascade-parent="0"
 *           data-cascade-group="users-filter">
 *     <option value="0">All Organisations</option>
 *   </select>
 *
 * `data-cascade-group` lets multiple cascades coexist on the same page.
 *
 * Behaviour:
 *   - Depth 1 is loaded on init (parent = 0 → root tenants).
 *   - When a level changes, every deeper level in the same group is
 *     reset to "All" and repopulated (or disabled if no children).
 *   - The selection bubbles up via `airpay:org-cascade:changed` so the
 *     host page (e.g. datatable filter bar) can refresh.
 *
 * Security: every dynamic option is built with createElement +
 * textContent, never innerHTML, even with seemingly-controlled values.
 * Defence-in-depth — a regression elsewhere that lets an org name
 * contain `<` can't escalate to XSS here.
 *
 * @module theme_sentientia/org_cascade
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var LABEL_FOR_DEPTH = {
        1: 'All Organisations',
        2: 'All Departments',
        3: 'All Sub-Departments',
        4: 'All Level-4 Units',
        5: 'All Level-5 Units'
    };

    /**
     * Remove every child option of a <select>. Safer than innerHTML = ''
     * because it never invokes the HTML parser.
     */
    function clearOptions(select) {
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }
    }

    /**
     * Build a single <option> with text-content (no innerHTML).
     */
    function buildOption(value, label) {
        var opt = document.createElement('option');
        opt.value = String(value);
        opt.textContent = label;
        return opt;
    }

    /**
     * Hit local_sentientia_org_list_children and rebuild a select's options.
     */
    function loadLevel(select, parentid) {
        var depth = parseInt(select.dataset.cascadeDepth || '1', 10);
        var defaultLabel = LABEL_FOR_DEPTH[depth] || 'All';
        select.setAttribute('aria-busy', 'true');
        select.disabled = true;

        var request = Ajax.call([{
            methodname: 'local_sentientia_org_list_children',
            args: { parentid: parentid, visible_only: true }
        }])[0];

        return request.then(function(result) {
            clearOptions(select);
            select.appendChild(buildOption(0, defaultLabel));

            (result.rows || []).forEach(function(row) {
                var opt = buildOption(row.id, row.name);
                opt.dataset.path = row.path || '';
                opt.dataset.hasChildren = row.has_children ? '1' : '0';
                select.appendChild(opt);
            });

            // If there are no real options (only "All"), disable the select
            // so users don't think they can drill deeper.
            select.disabled = (result.rows || []).length === 0;
            select.removeAttribute('aria-busy');

            return result.rows || [];
        }).catch(function(err) {
            select.removeAttribute('aria-busy');
            select.disabled = false;
            // Soft fail — log + notify, don't break the host page.
            Notification.exception(err);
            return [];
        });
    }

    /**
     * Build the set of selects belonging to one cascade group, sorted by depth.
     */
    function getGroupSelects(groupName) {
        var safeGroup = (window.CSS && CSS.escape)
            ? CSS.escape(groupName) : groupName;
        var all = Array.prototype.slice.call(
            document.querySelectorAll('select[data-airpay-org-cascade]'
                + '[data-cascade-group="' + safeGroup + '"]'));
        all.sort(function(a, b) {
            return parseInt(a.dataset.cascadeDepth || '0', 10)
                 - parseInt(b.dataset.cascadeDepth || '0', 10);
        });
        return all;
    }

    /**
     * When a level changes, reset all deeper levels then load the next one.
     */
    function onLevelChange(changedSelect) {
        var group = changedSelect.dataset.cascadeGroup;
        var levels = getGroupSelects(group);
        var changedDepth = parseInt(changedSelect.dataset.cascadeDepth || '1', 10);
        var newParentId = parseInt(changedSelect.value || '0', 10);

        // Reset every deeper level visually.
        levels.forEach(function(sel) {
            var d = parseInt(sel.dataset.cascadeDepth || '1', 10);
            if (d > changedDepth) {
                clearOptions(sel);
                sel.appendChild(buildOption(0, LABEL_FOR_DEPTH[d] || 'All'));
                sel.disabled = (newParentId === 0);
                sel.dataset.cascadeParent = String(newParentId);
            }
        });

        // Load the next level (depth = changedDepth + 1) using the new value
        // as the parent. Only if there IS a next level select on the page.
        var nextDepth = changedDepth + 1;
        var nextSelect = levels.find(function(sel) {
            return parseInt(sel.dataset.cascadeDepth || '0', 10) === nextDepth;
        });
        if (nextSelect && newParentId > 0) {
            nextSelect.dataset.cascadeParent = String(newParentId);
            loadLevel(nextSelect, newParentId);
        }

        // Tell the host page the filter state changed.
        var detail = {};
        levels.forEach(function(sel) {
            var d = parseInt(sel.dataset.cascadeDepth || '1', 10);
            detail['level' + d] = parseInt(sel.value || '0', 10);
        });
        document.dispatchEvent(new CustomEvent('airpay:org-cascade:changed', {
            detail: { group: group, levels: detail }
        }));
    }

    /**
     * Init a cascade group. Loads depth-1 from the root, wires change events.
     */
    function initGroup(groupName) {
        var levels = getGroupSelects(groupName);
        if (levels.length === 0) { return; }

        // Wire change handlers (idempotent).
        levels.forEach(function(sel) {
            if (sel.dataset.airpayCascadeBound === '1') { return; }
            sel.dataset.airpayCascadeBound = '1';
            sel.addEventListener('change', function() {
                onLevelChange(sel);
            });
        });

        // Load the first level (root tenants).
        var first = levels[0];
        loadLevel(first, parseInt(first.dataset.cascadeParent || '0', 10));
    }

    return {
        /**
         * Boot every cascade group on the page. The host page calls this
         * via `theme_sentientia/org_cascade` AMD require.
         */
        init: function() {
            // Find every unique group name on the page.
            var groups = {};
            document.querySelectorAll('select[data-airpay-org-cascade]').forEach(function(sel) {
                var g = sel.dataset.cascadeGroup || 'default';
                groups[g] = true;
            });
            Object.keys(groups).forEach(initGroup);
        }
    };
});
