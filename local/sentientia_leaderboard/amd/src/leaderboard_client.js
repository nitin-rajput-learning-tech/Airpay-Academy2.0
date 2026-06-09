// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Leaderboard client — Phase L.0 (ES6 source for grunt).
 *
 * Hand-bundled ES5 version in amd/build/leaderboard_client.min.js.
 *
 * Subscribes to leaderboard SSE events and refreshes the leaderboard
 * table body on `leaderboard.recomputed`. Falls back to 30s short
 * polling when SSE is disabled OR the browser doesn't support
 * EventSource OR the connection drops too many times.
 *
 * Per ADR-014: the recompute event is the only one we DOM-patch on.
 * score_changed + position_changed are reserved for Phase L.1+ when we
 * ship incremental updates. Today, every recompute = full re-fetch of
 * the top-N rows via the get_board WS — that keeps the client logic
 * dumb and easy to reason about.
 *
 * @module local_sentientia_leaderboard/leaderboard_client
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

const POLL_INTERVAL_MS = 30000;
const SSE_RECONNECT_DELAY_MS = 5000;

const fetchBoard = (boardid) => {
    return Ajax.call([{
        methodname: 'local_sentientia_leaderboard_get_board',
        args: {boardid: boardid, topn: 25},
    }])[0];
};

const renderRows = (root, response) => {
    const tbody = root.querySelector('[data-rows-body]');
    if (!tbody) {
        return;
    }
    if (!response.rows || response.rows.length === 0) {
        // Replace the table with the empty-state marker.
        const table = root.querySelector('[data-rows-table]');
        if (table) {
            const empty = document.createElement('div');
            empty.className = 'airpay-lb__empty';
            empty.dataset.emptyState = '';
            empty.textContent = '';
            const strKey = 'no_entries';
            require(['core/str'], (Str) => {
                Str.get_string(strKey, 'local_sentientia_leaderboard').then((s) => {
                    empty.textContent = s;
                });
            });
            table.replaceWith(empty);
        }
        return;
    }
    // Build rows by re-rendering each tr from a sub-template-free
    // string. We don't use Templates.renderForPromise here for the
    // <tr> rows because each row is just three <td>s and pulling a
    // partial template would add a network round-trip — overkill.
    const fragments = response.rows.map((r) => {
        const tr = document.createElement('tr');
        tr.className = 'airpay-lb__row';
        tr.dataset.userid = r.userid;
        tr.innerHTML = `
            <td class="airpay-lb__rank">${r.rank}</td>
            <td class="airpay-lb__user"></td>
            <td class="airpay-lb__points text-end">${r.points}</td>
        `;
        // Text content (not HTML) for the user column — protect against
        // XSS even though the server format_string'd the name already.
        tr.children[1].textContent = r.fullname;
        return tr;
    });
    tbody.replaceChildren(...fragments);

    // Update the "your rank" line.
    const myRankNode = root.querySelector('[data-my-rank]');
    if (myRankNode && response.my_rank > 0) {
        require(['core/str'], (Str) => {
            Str.get_string('your_rank',
                'local_sentientia_leaderboard',
                response.my_rank).then((s) => {
                myRankNode.firstChild.textContent = s;
            });
        });
    }

    // Update the "last recomputed" caption.
    const updated = root.querySelector('[data-last-recomputed]');
    if (updated && response.last_recomputed) {
        const d = new Date(response.last_recomputed * 1000);
        updated.textContent = '↻ ' + d.toLocaleString();
    }
};

const startPolling = (boardid, root) => {
    const tick = () => {
        fetchBoard(boardid)
            .then((resp) => renderRows(root, resp))
            .catch((err) => {
                // Polling errors are non-fatal — the UI just won't update
                // until the next tick or the next page load.
                Notification.exception(err);
            });
    };
    // Don't fire immediately on init — the page already rendered the
    // initial state server-side. Wait one POLL_INTERVAL.
    return window.setInterval(tick, POLL_INTERVAL_MS);
};

const startSSE = (boardid, root) => {
    const wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot)
        ? M.cfg.wwwroot : '';
    const streamUrl = `${wwwroot}/local/sentientia_leaderboard/stream.php?boardid=${boardid}`;

    let es;
    try {
        es = new EventSource(streamUrl, {withCredentials: false});
    } catch (e) {
        return null;
    }

    // sync event — server tells us the current last_recomputed. We DON'T
    // refetch on sync; the initial server render is canonical.
    es.addEventListener('sync', () => {
        // No-op. The initial render is server-side; clients only refresh
        // on actual recomputes.
    });

    // recomputed event — refetch the top-N.
    es.addEventListener('leaderboard.recomputed', () => {
        fetchBoard(boardid)
            .then((resp) => renderRows(root, resp))
            .catch(() => {
                // Silent on error — the next event or poll-tick retries.
            });
    });

    // reconnect — server is rotating us off after 5 min. EventSource will
    // auto-reconnect; we don't need to do anything.
    es.addEventListener('reconnect', () => {
        // No-op — EventSource handles the reconnect.
    });

    es.addEventListener('error', () => {
        if (es.readyState === EventSource.CLOSED) {
            // Server forcibly closed (403 etc.) — switch to polling.
            window.setTimeout(() => {
                startPolling(boardid, root);
            }, SSE_RECONNECT_DELAY_MS);
        }
    });

    return es;
};

const init = (opts = {}) => {
    const boardid = opts.boardid;
    const realtime = opts.realtime !== false;

    if (!boardid) {
        return;
    }

    const root = document.querySelector(
        `.airpay-lb[data-boardid="${boardid}"]`);
    if (!root) {
        return;
    }

    if (realtime && typeof window.EventSource !== 'undefined') {
        // Toggle live indicator visibility.
        const onIndicator = root.querySelector('[data-live-on]');
        const offIndicator = root.querySelector('[data-live-off]');
        if (onIndicator) {
            onIndicator.style.display = '';
        }
        if (offIndicator) {
            offIndicator.style.display = 'none';
        }
        startSSE(boardid, root);
    } else {
        // SSE disabled OR EventSource unsupported — polling fallback.
        startPolling(boardid, root);
    }
};

export {init};
