// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Agentic Copilot panel controller (P1.3).
 *
 * The LLM proposes, the platform authorises and executes. This client:
 *  - sends the learner's message to local_sentientia_assistant_agent_turn
 *  - renders the assistant reply (already sanitised server-side)
 *  - when the server returns a write PROPOSAL, shows a confirm/cancel UI;
 *    confirming calls local_sentientia_assistant_agent_confirm which runs
 *    the guarded tool. The client never executes anything itself.
 *
 * All assistant HTML comes back format_text()-sanitised; we still insert it
 * via a sandboxed container and never eval. Learner echoes are textContent.
 *
 * @module     local_sentientia_assistant/agent
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SEL = {
    ROOT: '.airpay-copilot',
    LOG: '#copilot-log',
    FORM: '#copilot-form',
    INPUT: '#copilot-input',
    PROPOSAL: '#copilot-proposal',
    PROPOSAL_TEXT: '#copilot-proposal-text',
    CONFIRM: '#copilot-confirm',
    CANCEL: '#copilot-cancel',
};

let pendingQuery = null;
let pendingTool = null;

/**
 * Append a turn to the log.
 *
 * @param {string} role 'learner' | 'assistant'
 * @param {string} html Sanitised HTML (assistant) or plain text (learner)
 * @param {boolean} isHtml Whether the content is server-sanitised HTML
 */
const appendTurn = (role, html, isHtml) => {
    const log = document.querySelector(SEL.LOG);
    if (!log) {
        return;
    }
    const row = document.createElement('div');
    row.className = 'airpay-copilot__turn airpay-copilot__turn--' + role;
    if (isHtml) {
        // Server already ran format_text() — render as sanitised HTML.
        row.innerHTML = html;
    } else {
        row.textContent = html;
    }
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
};

/** Hide the proposal/confirm region and clear pending state. */
const clearProposal = () => {
    const region = document.querySelector(SEL.PROPOSAL);
    if (region) {
        region.hidden = true;
    }
    pendingQuery = null;
    pendingTool = null;
};

/**
 * Handle a server turn response.
 *
 * @param {Object} resp WS response
 */
const handleResponse = (resp) => {
    if (resp.message) {
        appendTurn('assistant', resp.message, true);
    }
    if (resp.hasproposal) {
        // A write action awaits explicit learner confirmation.
        pendingTool = resp.proposaltool;
        const region = document.querySelector(SEL.PROPOSAL);
        const text = document.querySelector(SEL.PROPOSAL_TEXT);
        if (text) {
            text.textContent = resp.proposallabel;
        }
        if (region) {
            region.hidden = false;
        }
    } else {
        clearProposal();
    }
};

/** Send a turn (propose). */
const sendTurn = (query) => {
    pendingQuery = query;
    Ajax.call([{
        methodname: 'local_sentientia_assistant_agent_turn',
        args: {query: query},
    }])[0].then(handleResponse).catch(Notification.exception);
};

/** Confirm a pending write proposal (execute through the guard chain). */
const confirmTurn = () => {
    if (!pendingQuery || !pendingTool) {
        clearProposal();
        return;
    }
    const query = pendingQuery;
    const tool = pendingTool;
    clearProposal();
    Ajax.call([{
        methodname: 'local_sentientia_assistant_agent_confirm',
        args: {query: query, tool: tool},
    }])[0].then(handleResponse).catch(Notification.exception);
};

/** Initialise the panel. */
export const init = () => {
    const form = document.querySelector(SEL.FORM);
    const input = document.querySelector(SEL.INPUT);
    if (!form || !input) {
        return;
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const query = input.value.trim();
        if (!query) {
            return;
        }
        appendTurn('learner', query, false);
        input.value = '';
        clearProposal();
        sendTurn(query);
    });

    const confirmBtn = document.querySelector(SEL.CONFIRM);
    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmTurn);
    }
    const cancelBtn = document.querySelector(SEL.CANCEL);
    if (cancelBtn) {
        cancelBtn.addEventListener('click', clearProposal);
    }
};

export default {init};
