// Shared Airpay admin datatable component.
// @module     theme_airpayux/datatable
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// USAGE
// -----
// HTML:
//   <div data-airpay-table
//        data-endpoint="local_airpay_users_list"
//        data-columns='[{"key":"fullname","label":"Name","sortable":true},...]'
//        data-search-placeholder="Search users…"
//        data-perpage="25">
//   </div>
//
// The endpoint web service must accept:
//   {search, sort, sortdir, page, perpage, filters}
// and return:
//   {total: int, rows: [{...}], page, perpage}

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const debounce = (fn, ms) => {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
};

const escapeHtml = (s) => {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
    }[c]));
};

class Datatable {
    constructor(root) {
        this.root = root;
        this.endpoint = root.dataset.endpoint;
        this.columns = JSON.parse(root.dataset.columns || '[]');
        this.perpage = parseInt(root.dataset.perpage || '25', 10);
        this.searchPlaceholder = root.dataset.searchPlaceholder || 'Search…';
        this.actionsHtml = root.dataset.rowActions || '';
        this.selectable = root.dataset.selectable === '1';

        const firstSortable = this.columns.find(c => c.sortable);
        this.state = {
            search: '',
            sort: firstSortable ? (firstSortable.sortkey || firstSortable.key) : '',
            sortcol: firstSortable ? firstSortable.key : '',
            sortdir: 'asc',
            page: 0,
            filters: {},
            total: 0,
            rows: [],
            loading: false,
            selected: new Set(),
        };

        this.render();
        this.attachHandlers();
        this.fetch();
    }

    /** External callers can ask for the selected row IDs. */
    getSelected() { return Array.from(this.state.selected); }
    clearSelection() {
        this.state.selected.clear();
        this.renderBody();
        this.fireSelectionChanged();
    }
    fireSelectionChanged() {
        this.root.dispatchEvent(new CustomEvent('airpay:datatable:selection', {
            bubbles: true,
            detail: {selected: Array.from(this.state.selected)},
        }));
    }

    render() {
        this.root.innerHTML = `
            <div class="airpay-datatable">
                <div class="airpay-datatable__toolbar d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <div class="airpay-datatable__search position-relative" style="flex: 1; max-width: 380px;">
                        <i class="fa fa-search position-absolute" style="top: 50%; left: 12px; transform: translateY(-50%); color: #888;"></i>
                        <input type="search" class="form-control"
                               placeholder="${escapeHtml(this.searchPlaceholder)}"
                               style="padding-left: 36px;"
                               data-airpay-table-search>
                    </div>
                    <div class="airpay-datatable__meta small text-muted" data-airpay-table-meta></div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead style="background: var(--ap-color-primary, #0066A7); color: #fff;">
                                <tr data-airpay-table-head></tr>
                            </thead>
                            <tbody data-airpay-table-body>
                                <tr><td colspan="99" class="text-center py-5 text-muted">
                                    <i class="fa fa-spinner fa-spin fa-2x d-block mb-2"></i> Loading…
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body bg-light border-top d-flex justify-content-between align-items-center"
                         data-airpay-table-pager style="display: none !important;">
                        <div class="small text-muted" data-airpay-table-pager-info></div>
                        <div class="btn-group btn-group-sm" data-airpay-table-pager-buttons></div>
                    </div>
                </div>
            </div>`;
        this.renderHead();
    }

    renderHead() {
        const head = this.root.querySelector('[data-airpay-table-head]');
        const selectCol = this.selectable
            ? '<th style="width: 36px; padding-left: 12px;"><input type="checkbox" data-airpay-table-select-all></th>'
            : '';
        head.innerHTML = selectCol + this.columns.map((c) => {
            const sortable = c.sortable ? 'cursor: pointer;' : '';
            const arrow = (c.key === this.state.sortcol)
                ? (this.state.sortdir === 'asc' ? ' <i class="fa fa-caret-up"></i>' : ' <i class="fa fa-caret-down"></i>')
                : '';
            return `<th class="ps-3" style="${sortable} font-weight: 600; font-size: 0.85rem;"
                        ${c.sortable ? `data-airpay-table-sort="${escapeHtml(c.key)}"` : ''}>
                        ${escapeHtml(c.label)}${arrow}
                    </th>`;
        }).join('') + (this.actionsHtml ? '<th class="text-end pe-3" style="font-weight: 600; font-size: 0.85rem;">Actions</th>' : '');
    }

    attachHandlers() {
        const search = this.root.querySelector('[data-airpay-table-search]');
        search.addEventListener('input', debounce((e) => {
            this.state.search = e.target.value;
            this.state.page = 0;
            this.fetch();
        }, 250));

        // Sort headers — delegated.
        this.root.addEventListener('click', (e) => {
            const sortHead = e.target.closest('[data-airpay-table-sort]');
            if (sortHead) {
                const colKey = sortHead.dataset.airpayTableSort;
                const col = this.columns.find(c => c.key === colKey);
                const sortBy = col?.sortkey || colKey;
                if (this.state.sortcol === colKey) {
                    this.state.sortdir = (this.state.sortdir === 'asc') ? 'desc' : 'asc';
                } else {
                    this.state.sortcol = colKey;
                    this.state.sort = sortBy;
                    this.state.sortdir = 'asc';
                }
                this.state.page = 0;
                this.renderHead();
                this.fetch();
                return;
            }

            const pageBtn = e.target.closest('[data-airpay-table-page]');
            if (pageBtn) {
                e.preventDefault();
                this.state.page = parseInt(pageBtn.dataset.airpayTablePage, 10);
                this.fetch();
            }
        });

        // Row selection: per-row checkbox.
        this.root.addEventListener('change', (e) => {
            const sel = e.target.closest('[data-airpay-table-select]');
            if (sel) {
                const id = parseInt(sel.dataset.airpayTableSelect, 10);
                if (sel.checked) {
                    this.state.selected.add(id);
                } else {
                    this.state.selected.delete(id);
                }
                this.fireSelectionChanged();
                return;
            }
            // Select-all toggle (current page only).
            const all = e.target.closest('[data-airpay-table-select-all]');
            if (all) {
                if (all.checked) {
                    this.state.rows.forEach((r) => this.state.selected.add(r.id));
                } else {
                    this.state.rows.forEach((r) => this.state.selected.delete(r.id));
                }
                this.renderBody();
                this.fireSelectionChanged();
            }
        });
    }

    async fetch() {
        if (this.state.loading) return;
        this.state.loading = true;

        const args = {
            search: this.state.search,
            sort: this.state.sort,
            sortdir: this.state.sortdir,
            page: this.state.page,
            perpage: this.perpage,
            filters: JSON.stringify(this.state.filters),
        };

        try {
            const response = await Ajax.call([{methodname: this.endpoint, args: args}])[0];
            this.state.total = response.total || 0;
            this.state.rows = response.rows || [];
            this.renderBody();
            this.renderPager();
            this.renderMeta();
            // Notify other modules (e.g. CRUD action handlers) that rows changed.
            this.root.dispatchEvent(new CustomEvent('airpay:datatable:rendered', {bubbles: true, detail: this.state}));
        } catch (e) {
            Notification.exception(e);
            this.root.querySelector('[data-airpay-table-body]').innerHTML =
                `<tr><td colspan="99" class="text-center py-4 text-danger">Failed to load data. ${escapeHtml(e.message || '')}</td></tr>`;
        } finally {
            this.state.loading = false;
        }
    }

    renderBody() {
        const body = this.root.querySelector('[data-airpay-table-body]');
        const totalCols = this.columns.length + (this.actionsHtml ? 1 : 0) + (this.selectable ? 1 : 0);
        if (this.state.rows.length === 0) {
            body.innerHTML = `<tr><td colspan="${totalCols}" class="text-center py-5 text-muted">
                <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                ${this.state.search ? `No results for "${escapeHtml(this.state.search)}"` : 'No records found'}
            </td></tr>`;
            return;
        }

        body.innerHTML = this.state.rows.map((row) => {
            const id = row.id;
            const isChecked = this.state.selected.has(id);
            const checkboxCell = this.selectable
                ? `<td style="padding-left: 12px;"><input type="checkbox" data-airpay-table-select="${escapeHtml(id)}" ${isChecked ? 'checked' : ''}></td>`
                : '';
            const cells = this.columns.map((c) => {
                let val = row[c.key];
                if (c.format === 'badge') {
                    const cls = row[c.key + '_class'] || 'badge-secondary';
                    val = `<span class="badge ${escapeHtml(cls)}">${escapeHtml(val)}</span>`;
                } else if (c.format === 'html') {
                    val = val || '';
                } else {
                    val = escapeHtml(val);
                }
                return `<td class="ps-3">${val}</td>`;
            }).join('');

            let actionCell = '';
            if (this.actionsHtml || row.actions) {
                const html = row.actions || this.actionsHtml.replace(/\{\{(\w+)\}\}/g,
                    (m, key) => escapeHtml(row[key] ?? ''));
                actionCell = `<td class="text-end pe-3">${html}</td>`;
            }
            return `<tr data-row-id="${escapeHtml(id ?? '')}" ${isChecked ? 'class="table-active"' : ''}>${checkboxCell}${cells}${actionCell}</tr>`;
        }).join('');
    }

    renderMeta() {
        const meta = this.root.querySelector('[data-airpay-table-meta]');
        const start = this.state.page * this.perpage + 1;
        const end = Math.min(start + this.state.rows.length - 1, this.state.total);
        meta.textContent = this.state.total > 0
            ? `${start}–${end} of ${this.state.total.toLocaleString()}`
            : '';
    }

    renderPager() {
        const pager = this.root.querySelector('[data-airpay-table-pager]');
        const info = this.root.querySelector('[data-airpay-table-pager-info]');
        const buttons = this.root.querySelector('[data-airpay-table-pager-buttons]');

        const totalPages = Math.ceil(this.state.total / this.perpage);
        if (totalPages <= 1) {
            pager.style.display = 'none';
            return;
        }
        pager.style.display = '';

        info.textContent = `Page ${this.state.page + 1} of ${totalPages}`;

        const cur = this.state.page;
        const window_size = 2;
        const pages = [];
        pages.push({label: '«', page: 0, disabled: cur === 0});
        pages.push({label: '‹', page: Math.max(0, cur - 1), disabled: cur === 0});
        const start = Math.max(0, cur - window_size);
        const end = Math.min(totalPages, cur + window_size + 1);
        for (let p = start; p < end; p++) {
            pages.push({label: String(p + 1), page: p, active: p === cur});
        }
        pages.push({label: '›', page: Math.min(totalPages - 1, cur + 1), disabled: cur >= totalPages - 1});
        pages.push({label: '»', page: totalPages - 1, disabled: cur >= totalPages - 1});

        buttons.innerHTML = pages.map((p) => `
            <button type="button" class="btn btn-${p.active ? 'primary' : 'outline-secondary'} btn-sm"
                    data-airpay-table-page="${p.page}" ${p.disabled ? 'disabled' : ''}>
                ${escapeHtml(p.label)}
            </button>`).join('');
    }

    /** Public API: trigger a refetch without changing state (for after CRUD ops). */
    refresh() {
        this.fetch();
    }

    /** Public API: set a filter and refetch. */
    setFilter(key, value) {
        if (value === '' || value === null || value === undefined) {
            delete this.state.filters[key];
        } else {
            this.state.filters[key] = value;
        }
        this.state.page = 0;
        this.fetch();
    }
}

const instances = new WeakMap();

export const init = () => {
    document.querySelectorAll('[data-airpay-table]').forEach((root) => {
        if (instances.has(root)) return;
        instances.set(root, new Datatable(root));
    });
};

/** Get the Datatable instance for a root element, for external CRUD module integration. */
export const getInstance = (root) => instances.get(root);
