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
 * Functionality to Add Dashboard Courses
 *
 * @module     local_courses/coursedynamicforms
 * @copyright  2024 Moodle India Information Solutions Pvt Ltd.
 * @author     2024 Rizwana <rizwana.madire@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Modal from 'core/modal';
import * as Str from 'core/str';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';

const SELECTORS = {
    ADD_DASHBOARDCOURSES: '[data-action="adddashboardcourse"]',
};



/**
* Displays a modal form to add courses to dashboard
*
* @param {Event} e
*/
const AddDashboardCourses = function(e) {
    e.preventDefault();
    var id = $(e.currentTarget).attr('data-id');
    var headstring = $(e.currentTarget).attr('data-headstring');
    var contextid = $(e.currentTarget).attr('data-contextid');
    // var success = $(e.currentTarget).attr('data-added');
    // var updated = $(e.currentTarget).attr('data-updated');

    var modal = new ModalForm({
        formClass: 'local_courses\\form\\adddashboardcourse_form',
        args: {
            id: id,
            contextid: contextid,
        },
        modalConfig: {title: headstring, type: 'SAVE_CANCEL'},
        saveButtonText: Str.get_string('save', 'local_courses'),
        returnFocus: $(e.currentTarget),
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, function() {
        let messages = '';
        let type = '';
        if(e.detail.warnings !== null && e.detail.warnings != undefined) {
            type = 'error';
            messages = e.detail.warnings.map(warning => warning.message);
        } else {
            type = 'success';
        }
        Str.get_strings([
            {key: 'success_message', component: 'local_courses'},
        ]).done(function(s) {
            Notification.addNotification({
                type: type,
                message: id > 0 ? s[0] : s[0]
            });
        });
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    });

    // modal.addEventListener(modal.events.CANCEL_BUTTON_PRESSED, () => {
    //     window.location.reload();
    // });

    modal.show();

    // Add classes and animate when the form is loaded
    modal.addEventListener(modal.events.LOADED, function() {
        if (modal.modal) {
            const modalRoot = modal.modal.getRoot();
            modalRoot.addClass('openLMStransition local_adddashboardcoursesform');
            modalRoot.animate({"right": "0"}, 500); // Animate to bring it into view

            modalRoot.on(ModalEvents.hidden, () => {
                modalRoot.animate({"right": "-85%"}, 500);
                setTimeout(function() {
                    modal.modal.destroy();
                }, 1000);
                modal.modal.setBody('');
            });
        }
    });
};

/**
 * Initialise course actions
 */
export const init = () => {

    $(SELECTORS.ADD_DASHBOARDCOURSES).on('click', function(e) {
        e.preventDefault();
        AddDashboardCourses(e);
    });

};
