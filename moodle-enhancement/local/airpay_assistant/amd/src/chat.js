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
 * Airpay AI Assistant chat bubble — client-side controller.
 *
 * Wires the chat_bubble.mustache markup into an interactive assistant:
 *
 *  - Toggle button opens/closes the panel (auto-focuses the input on open)
 *  - Send button + Enter key submits to local_airpay_assistant_ask
 *  - Cmd+K / Ctrl+K opens the panel from anywhere (manifesto §4.1)
 *  - Escape closes the panel and returns focus to the toggle
 *  - Quick-action chips populate the input then submit
 *  - Typing indicator + skeleton appear while the model thinks
 *  - User and assistant turns are rendered with XSS-safe DOM
 *
 * Every motion respects prefers-reduced-motion via the CSS tokens.
 *
 * @module     local_airpay_assistant/chat
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SELECTORS = {
    ROOT:       '#airpay-assistant',
    TOGGLE:     '#airpay-assistant-toggle',
    PANEL:      '#airpay-assistant-panel',
    MINIMIZE:   '#airpay-assistant-minimize',
    MESSAGES:   '#airpay-assistant-messages',
    INPUT:      '#airpay-assistant-input',
    SEND:       '#airpay-assistant-send',
    QUICK:      '.airpay-assistant__quick-actions button',
};

const CLASSES = {
    HIDDEN:  'airpay-assistant__panel--hidden',
    VISIBLE: 'airpay-assistant__panel--visible',
};

// Allow-list of tags that markdown produces (after format_text + FORMAT_MARKDOWN
// + purify_html on the server). Anything outside this allow-list is dropped
// during the safe-insert pass below — defense-in-depth on top of the server's
// HTMLPurifier sanitisation.
const ALLOWED_TAGS = new Set([
    'P', 'STRONG', 'EM', 'B', 'I', 'CODE', 'PRE', 'BR',
    'UL', 'OL', 'LI', 'A', 'BLOCKQUOTE', 'HR', 'SPAN',
]);
const ALLOWED_ATTRS = new Set(['href', 'title', 'lang', 'dir']);

let initialised = false;
let panelOpen = false;
let sending = false;

/**
 * Open the chat panel. Focus the input. Update aria-expanded on the toggle.
 */
const openPanel = () => {
    const panel = document.querySelector(SELECTORS.PANEL);
    const toggle = document.querySelector(SELECTORS.TOGGLE);
    const input = document.querySelector(SELECTORS.INPUT);
    if (!panel || !toggle) {
        return;
    }
    panel.style.display = 'flex';
    // Force a reflow so the transition fires on the class change.
    // eslint-disable-next-line no-unused-expressions
    panel.offsetWidth;
    panel.classList.remove(CLASSES.HIDDEN);
    panel.classList.add(CLASSES.VISIBLE);
    toggle.setAttribute('aria-expanded', 'true');

    // Swap the icons on the fab.
    const iconOpen = toggle.querySelector('.airpay-assistant__icon-open');
    const iconClose = toggle.querySelector('.airpay-assistant__icon-close');
    if (iconOpen) {
        iconOpen.style.display = 'none';
    }
    if (iconClose) {
        iconClose.style.display = '';
    }

    // Auto-focus the input so keyboard users can type immediately.
    if (input) {
        // setTimeout so the animation completes before focus jumps the
        // viewport — feels less jarring on mobile.
        window.setTimeout(() => input.focus(), 50);
    }
    panelOpen = true;
};

/**
 * Close the panel. Return focus to the toggle so keyboard users keep place.
 */
const closePanel = () => {
    const panel = document.querySelector(SELECTORS.PANEL);
    const toggle = document.querySelector(SELECTORS.TOGGLE);
    if (!panel || !toggle) {
        return;
    }
    panel.classList.remove(CLASSES.VISIBLE);
    panel.classList.add(CLASSES.HIDDEN);
    toggle.setAttribute('aria-expanded', 'false');

    const iconOpen = toggle.querySelector('.airpay-assistant__icon-open');
    const iconClose = toggle.querySelector('.airpay-assistant__icon-close');
    if (iconOpen) {
        iconOpen.style.display = '';
    }
    if (iconClose) {
        iconClose.style.display = 'none';
    }

    // Wait for the close transition, then hide the panel from layout.
    window.setTimeout(() => {
        if (!panelOpen) {
            panel.style.display = 'none';
        }
    }, 300);

    toggle.focus();
    panelOpen = false;
};

/**
 * Walk a parsed DOM node and recreate it as a sanitised tree.
 *
 * Bot responses arrive as HTML that was already sanitised server-side by
 * format_text() + HTMLPurifier. We add this client-side allow-list as
 * defense-in-depth so any bypass on the server can't introduce script,
 * iframe, style, or event-handler attributes here.
 *
 * @param {Node} node  source node (from DOMParser output)
 * @return {Node|null} a freshly-created clone of node with only allow-listed
 *                    tags + attributes, or null if the node should be dropped
 */
const sanitiseNode = (node) => {
    // Text nodes — always safe (browsers escape).
    if (node.nodeType === Node.TEXT_NODE) {
        return document.createTextNode(node.textContent);
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return null;
    }
    if (!ALLOWED_TAGS.has(node.tagName)) {
        // Tag not in allow-list — drop the wrapper but preserve text-only
        // content so we don't lose information.
        const frag = document.createDocumentFragment();
        node.childNodes.forEach((child) => {
            const c = sanitiseNode(child);
            if (c) {
                frag.appendChild(c);
            }
        });
        return frag;
    }
    const safe = document.createElement(node.tagName);
    Array.from(node.attributes).forEach((attr) => {
        if (!ALLOWED_ATTRS.has(attr.name)) {
            return;
        }
        // Block javascript: / data: URL schemes on hrefs.
        if (attr.name === 'href') {
            const v = (attr.value || '').trim().toLowerCase();
            if (v.startsWith('javascript:') || v.startsWith('data:')) {
                return;
            }
        }
        safe.setAttribute(attr.name, attr.value);
    });
    node.childNodes.forEach((child) => {
        const c = sanitiseNode(child);
        if (c) {
            safe.appendChild(c);
        }
    });
    return safe;
};

/**
 * Parse + sanitise + append HTML into the target element. Uses DOMParser
 * (script tags in the parsed document are inert — they don't execute) and
 * an allow-list pass to filter out anything not in the markdown tag set.
 *
 * @param {Element} target  where to append
 * @param {string} html     server-sanitised HTML
 */
const safeAppendHtml = (target, html) => {
    const doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
    const body = doc.body;
    if (!body) {
        target.textContent = String(html);
        return;
    }
    Array.from(body.childNodes).forEach((node) => {
        const safe = sanitiseNode(node);
        if (safe) {
            target.appendChild(safe);
        }
    });
};

/**
 * Render a single message into the conversation log.
 *
 * @param {string} text     the message text or HTML
 * @param {string} role     "user" or "bot"
 * @param {boolean} isHtml  pass-through HTML (default false). When true, the
 *                          server has already run format_text + HTMLPurifier;
 *                          we still pass it through safeAppendHtml as a
 *                          second defense-in-depth filter.
 */
const renderMessage = (text, role, isHtml = false) => {
    const messages = document.querySelector(SELECTORS.MESSAGES);
    if (!messages) {
        return;
    }
    const wrap = document.createElement('div');
    wrap.className = 'airpay-assistant__msg airpay-assistant__msg--' + role;
    if (isHtml) {
        safeAppendHtml(wrap, text);
    } else {
        const p = document.createElement('p');
        p.textContent = text;
        p.style.margin = '0';
        wrap.appendChild(p);
    }
    messages.appendChild(wrap);
    messages.scrollTop = messages.scrollHeight;
};

/**
 * Show a typing indicator while the assistant is thinking.
 */
const showTyping = () => {
    const messages = document.querySelector(SELECTORS.MESSAGES);
    if (!messages) {
        return null;
    }
    const wrap = document.createElement('div');
    wrap.className = 'airpay-assistant__msg airpay-assistant__msg--typing';
    wrap.setAttribute('aria-label', 'Assistant is typing');
    for (let i = 0; i < 3; i++) {
        const dot = document.createElement('span');
        dot.className = 'airpay-assistant__dot';
        wrap.appendChild(dot);
    }
    messages.appendChild(wrap);
    messages.scrollTop = messages.scrollHeight;
    return wrap;
};

/**
 * Submit the current input value to the AI and render the response.
 *
 * @param {string} [overrideText]  optional — used by quick-action chips
 */
const submitMessage = async (overrideText) => {
    if (sending) {
        return;
    }
    const input = document.querySelector(SELECTORS.INPUT);
    const send = document.querySelector(SELECTORS.SEND);
    if (!input || !send) {
        return;
    }

    const text = (overrideText || input.value || '').trim();
    if (!text) {
        return;
    }

    sending = true;
    send.disabled = true;
    input.disabled = true;
    input.value = '';

    renderMessage(text, 'user');
    const typing = showTyping();

    try {
        const result = await Ajax.call([{
            methodname: 'local_airpay_assistant_ask',
            args: { query: text },
        }])[0];

        if (typing) {
            typing.remove();
        }
        const responseText = (result && result.response) || 'Sorry, I had trouble understanding that.';
        renderMessage(responseText, 'bot', true);
    } catch (err) {
        if (typing) {
            typing.remove();
        }
        // Notification.exception is Moodle's standard error pipeline —
        // shows a toast and logs to console. The chat continues, the
        // user can retry.
        Notification.exception(err);
        renderMessage('I had trouble reaching the assistant. Please try again in a moment.', 'bot');
    } finally {
        sending = false;
        send.disabled = false;
        input.disabled = false;
        input.focus();
    }
};

/**
 * Initialise the chat bubble. Idempotent — repeated calls are no-ops.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    const root = document.querySelector(SELECTORS.ROOT);
    if (!root) {
        // The bubble isn't on this page (e.g. login). Nothing to do.
        return;
    }
    initialised = true;

    const toggle = root.querySelector(SELECTORS.TOGGLE);
    const minimize = root.querySelector(SELECTORS.MINIMIZE);
    const input = root.querySelector(SELECTORS.INPUT);
    const send = root.querySelector(SELECTORS.SEND);

    if (toggle) {
        toggle.addEventListener('click', () => {
            if (panelOpen) {
                closePanel();
            } else {
                openPanel();
            }
        });
    }

    if (minimize) {
        minimize.addEventListener('click', closePanel);
    }

    if (input) {
        // Enable Send only when the input has content.
        input.addEventListener('input', () => {
            if (send) {
                send.disabled = input.value.trim().length === 0;
            }
        });
        // Enter to submit (Shift+Enter for newline-not-supported in
        // a single-line input, so we just always submit).
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                submitMessage();
            }
        });
    }

    if (send) {
        send.addEventListener('click', () => submitMessage());
    }

    // Quick-action chips populate the input and submit.
    root.querySelectorAll(SELECTORS.QUICK).forEach((btn) => {
        btn.addEventListener('click', () => {
            const query = btn.dataset.query || btn.textContent.trim();
            submitMessage(query);
        });
    });

    // Global keyboard shortcuts — manifesto §4.1.
    document.addEventListener('keydown', (ev) => {
        // Cmd+K (Mac) or Ctrl+K (everyone else) opens the panel.
        // We deliberately don't fight any other Cmd+K handlers — if the
        // command palette ships later, this can be moved behind a feature
        // flag. For now nothing else owns Cmd+K in our codebase.
        if (ev.key === 'k' && (ev.metaKey || ev.ctrlKey) && !ev.shiftKey && !ev.altKey) {
            ev.preventDefault();
            if (!panelOpen) {
                openPanel();
            } else {
                // Toggle off — second Cmd+K closes it. Feels right for the
                // muscle-memory pattern.
                closePanel();
            }
            return;
        }
        // Escape closes the panel from anywhere on the page.
        if (ev.key === 'Escape' && panelOpen) {
            ev.preventDefault();
            closePanel();
        }
    });
};

export default { init };
