// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Opens dropdowns that only carry the Bootstrap 5 attribute data-bs-toggle="dropdown".
 *
 * The theme ships Bootstrap 4.6, whose data-api reads data-toggle="dropdown". Core templates of Moodle
 * 5.x (action menus such as the row menu of the certificate templates, moremenu, bulk actions, ...) carry
 * only data-bs-toggle, so on a page where nothing else brought Bootstrap 5's own data-api in, clicking
 * them did nothing.
 *
 * This module never competes with another handler. For a click on a toggle that carries
 * data-bs-toggle="dropdown" and NOT data-toggle="dropdown" it
 *
 *  1. records the open/closed state of the toggle while the event travels DOWN (window, capture phase,
 *     so before every Bootstrap 4 or Bootstrap 5 handler has run), and
 *  2. looks at the state again when the event has travelled back UP to window (bubble phase, after every
 *     document level handler has run). If the state changed, somebody (core's Bootstrap 5 data-api on a
 *     page that loaded it) already toggled the menu and nothing more is done. If it did not change, the
 *     element is handed to the theme's Bootstrap 4 dropdown, exactly once.
 *
 * Who handles a click, by attributes and by whether core loaded its Bootstrap 5 data-api on the page
 * (core does on pages that use a search combobox, a dropdown dialog or the participants initials bar):
 *
 *  - data-toggle only: the theme's Bootstrap 4 data-api. Core's Bootstrap 5 never matches it.
 *  - data-bs-toggle only: core's Bootstrap 5 where it is loaded, otherwise this module hands it to Bootstrap 4.
 *  - both attributes: the theme's Bootstrap 4 data-api ONLY. Where core's Bootstrap 5 data-api is loaded it
 *    would match the same element and toggle it a second time (open, then closed at once: the FX-20 round 1
 *    notes in the version.php of local_sentientia_org and local_sentientia_evaluation), so for the length
 *    of that click this module takes data-bs-toggle off the element (capture phase, before any handler) and
 *    puts it back when the click is over. This module never selects such an element for its own fallback.
 *
 * When the fallback opens a toggle it also writes data-toggle="dropdown" onto it. Bootstrap 4 finds the
 * dropdowns to close on an outside click, Escape or Tab through that attribute only, so without it a menu
 * opened here would never close again. Later clicks on that element are then plain Bootstrap 4 clicks.
 * Only data-bs-toggle is bridged: the other data-bs-* options (offset, display, auto-close, ...) are not, nor
 * are Bootstrap 5's native events (shown.bs.dropdown ...): Bootstrap 4 triggers them through jQuery.
 *
 * @module     theme_sentientia/dropdown_bridge
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
// Makes sure $.fn.dropdown (Bootstrap 4) is registered before the first click is bridged.
import './bootstrap/dropdown';

/**
 * Toggles that are Bootstrap 5 only: data-bs-toggle without data-toggle, and not disabled
 * (Bootstrap 5 skips disabled toggles too).
 *
 * @type {string}
 */
const SELECTOR = '[data-bs-toggle="dropdown"]:not([data-toggle="dropdown"]):not(.disabled):not(:disabled)';

/**
 * Toggles that carry both attributes. Bootstrap 4 (data-toggle) owns them, see the docblock of this module.
 *
 * @type {string}
 */
const BOTH_SELECTOR = '[data-toggle="dropdown"][data-bs-toggle="dropdown"]';

/**
 * Click events travelling through window, with the toggle and its state before any other handler ran.
 * Keyed by the event so an event that is stopped on its way up leaves nothing behind.
 *
 * @type {WeakMap<Event, {toggle: Element, before: string}>}
 */
const inFlight = new WeakMap();

let initialised = false;

/**
 * A string that changes whenever a Bootstrap 4 or a Bootstrap 5 dropdown opens or closes.
 *
 * Bootstrap 4 marks the wrapper and the menu with .show and sets aria-expanded on the toggle,
 * Bootstrap 5 marks the toggle and the menu with .show and sets aria-expanded as well. Reading all of
 * them covers both.
 *
 * @param {Element} toggle
 * @return {string}
 */
const openState = (toggle) => {
    const wrapper = toggle.parentNode;
    return [
        toggle.getAttribute('aria-expanded'),
        toggle.classList.contains('show'),
        Boolean(wrapper && wrapper.classList && wrapper.classList.contains('show')),
        Boolean(wrapper && wrapper.querySelector && wrapper.querySelector('.dropdown-menu.show')),
    ].join('|');
};

/**
 * Keep core's Bootstrap 5 data-api away from a toggle that Bootstrap 4 handles, for the length of one click.
 *
 * Bootstrap 5 looks the toggles up by data-bs-toggle when the click reaches document, so an element without
 * the attribute is not its business. Every handler of the click runs inside the dispatch, so the attribute
 * can come back in the next task.
 *
 * @param {Element} toggle
 */
const hideFromBootstrap5 = (toggle) => {
    toggle.removeAttribute('data-bs-toggle');
    window.setTimeout(() => toggle.setAttribute('data-bs-toggle', 'dropdown'), 0);
};

/**
 * Window, capture phase: keep Bootstrap 5 off a toggle that carries both attributes; for a Bootstrap 5 only
 * toggle remember its state before anybody handles the click.
 *
 * @param {MouseEvent} event
 */
const onClickCapture = (event) => {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') {
        return;
    }
    const both = target.closest(BOTH_SELECTOR);
    if (both) {
        hideFromBootstrap5(both);
        return;
    }
    const toggle = target.closest(SELECTOR);
    if (toggle) {
        inFlight.set(event, {toggle, before: openState(toggle)});
    }
};

/**
 * Window, bubble phase: if no other handler toggled the menu, toggle it with Bootstrap 4.
 *
 * @param {MouseEvent} event
 */
const onClickBubble = (event) => {
    const seen = inFlight.get(event);
    if (!seen) {
        return;
    }
    inFlight.delete(event);

    const toggle = seen.toggle;
    if (!toggle.isConnected || openState(toggle) !== seen.before) {
        // Detached meanwhile, or core's Bootstrap 5 data-api already opened or closed it.
        return;
    }

    // From here on the Bootstrap 4 data-api owns this element (outside click, Escape, Tab, arrow keys).
    toggle.setAttribute('data-toggle', 'dropdown');

    // Bootstrap 5 right-aligns a menu with .dropdown-menu-end, Bootstrap 4 with .dropdown-menu-right.
    const menu = toggle.parentNode && toggle.parentNode.querySelector('.dropdown-menu');
    if (menu && menu.classList.contains('dropdown-menu-end')) {
        menu.classList.add('dropdown-menu-right');
    }

    // The toggles are mostly links with href="#".
    event.preventDefault();
    $(toggle).dropdown('toggle');
};

/**
 * Start bridging. Safe to call more than once.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    window.addEventListener('click', onClickCapture, true);
    window.addEventListener('click', onClickBubble, false);
};
