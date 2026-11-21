<?php
// This file is part of the tool_certificate plugin for Moodle - http://moodle.org/
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
 * Handles verifying the code for a certificate.
 *
 * @package   tool_certificate
 * @copyright 2017 Mark Nelson <markn@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file does not need require_login because capability to verify can be granted to guests, skip codechecker here.
// @codingStandardsIgnoreLine
require_once('../../../config.php');

$code = optional_param('code', '', PARAM_ALPHANUM); // The code for the certificate we are verifying.

if (!\tool_certificate\permission::can_verify()) {
    throw new moodle_exception('verifynotallowed', 'tool_certificate');
}

$pageurl = new moodle_url('/admin/tool/certificate/index.php');

if ($code) {
    $pageurl->param('code', $code);
}

$heading = get_string('verifycertificates', 'tool_certificate');

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(format_string($heading));
$PAGE->set_heading($SITE->fullname);

$PAGE->navbar->add($heading);

// SENTIENTIA-CORE-MOD: post the form to the bare URL. Given $pageurl, formslib prints its ?code= as a second
// input named "code", and the client-side "required" check then reads an empty value. See
// moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md
$form = new \tool_certificate\verify_certificate_form(
    new moodle_url('/admin/tool/certificate/index.php'),
    null,
    'post',
    '',
    ['class' => 'mt-3 mb-5 p-4 bg-light']
);
// END SENTIENTIA-CORE-MOD

if ($code) {
    $form->set_data(['code' => $code]);
}

$PAGE->set_heading($heading);
echo $OUTPUT->header();
$form->display();
// SENTIENTIA-CORE-MOD: verify on arrival. A link that carries ?code= (certificate QR code, gallery "Verify", LinkedIn)
// shows the result without a second press of Verify. Read-only, same lookup as a submit; see the record named above.
if ($form->get_data() || ($code && !$form->is_submitted())) {
// END SENTIENTIA-CORE-MOD
    $result = \tool_certificate\certificate::verify($code);
    $results = new \tool_certificate\output\verify_certificate_results($result);
    echo $OUTPUT->render($results);
}
echo $OUTPUT->footer();
