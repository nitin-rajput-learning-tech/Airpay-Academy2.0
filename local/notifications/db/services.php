<?php

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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    local evalaution
 * @copyright  sreenivas 2018
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_notifications_submit_create_notification_form' => array(
            'classname'   => 'local_notifications_external',
            'methodname'  => 'submit_create_notification_form',
            'classpath'   => 'local/notifications/externallib.php',
            'description' => 'Submit form',
            'type'        => 'write',
            'ajax' => true,
    ),
    'local_notifications_managenotifications_view' => array(
                'classname'   => 'local_notifications_external',
                'methodname'  => 'managenotificationsview',
                'classpath'   => 'local/notifications/externallib.php',
                'description' => 'Display the Notification Index Page',
                'type'        => 'write',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        )
);

