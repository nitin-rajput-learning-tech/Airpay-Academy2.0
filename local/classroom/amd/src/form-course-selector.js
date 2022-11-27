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
 * course selector module.
 *
 * @module     tool_lp/form-course-selector
 * @class      form-course-selector
 * @package    tool_lp
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates'], function($, Ajax, Templates) {

    return /** @alias module:tool_lp/form-course-selector */ {

        processResults: function(selector, results) {
            var courses = [];
            $.each(results, function(index, course) {
                courses.push({
                    value: course.id,
                    label: course._label
                });
            });
            return courses;
        },

        transport: function(selector, query, success, failure) {
            var promise;
                contextid = parseInt($(selector).data('contextid'), 10),
                includes = $(selector).data('includes'),
                classroomid = $(selector).data('classroomid');


            promise = Ajax.call([{
                methodname: 'local_classroom_form_course_selector',
                args: {
                    query: query,
                    context: {contextid: contextid},
                    includes: includes,
                    classroomid: classroomid
                }
            }]);

            promise[0].then(function(results) {
                var promises = [],
                    i = 0;
                // Render the label.
                $.each(results.courses, function(index, course) {
                    promises.push(Templates.render('local_classroom/form-course-selector-suggestion', course));
                });
                // Apply the label to the results.
                return $.when.apply($.when, promises).then(function() {
                    var args = arguments;
                    $.each(results.courses, function(index, course) {
                        course._label = args[i];
                        i++;
                    });
                    success(results.courses);
                    return;
                });

            }).catch(failure);
        }

    };

});
