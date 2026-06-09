<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Translation — Brand-override manager (Phase T.0 MVP).
 *
 * Admin flow:
 *   GET             : list existing per-customer brand overrides + add form
 *   POST action=add : add or update a (brand_source, targetlang, brand_target)
 *   POST action=del : delete an override row
 *
 * Brand overrides render a brand name in a target script (e.g. "Airpay"
 * -> the Kannada-script rendering). When no override exists for a brand
 * in a target language, the brand is preserved verbatim by the prompt
 * instruction (see brand_manager::get_protected_terms).
 *
 * @package local_sentientia_translate
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_translate\brand_manager;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_translate');
}

require_capability('local/sentientia_translate:manage_brands', $context);

admin_externalpage_setup('local_sentientia_translate_brands');

// Phase 1: single customer (Airpay).
$customerid = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHANUMEXT);

    if ($action === 'add') {
        $brandsource = trim(optional_param('brand_source', '', PARAM_TEXT));
        $targetlang  = optional_param('targetlang', '', PARAM_ALPHA);
        $brandtarget = trim(optional_param('brand_target', '', PARAM_TEXT));

        if ($brandsource !== '' && $brandtarget !== '' && brand_manager::is_supported_lang($targetlang)) {
            brand_manager::set_override($customerid, $brandsource, $targetlang, $brandtarget);
            redirect($PAGE->url, get_string('brand_saved', 'local_sentientia_translate'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, get_string('brand_invalid', 'local_sentientia_translate'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    } else if ($action === 'del') {
        $id = required_param('id', PARAM_INT);
        brand_manager::delete_override($id, $customerid);
        redirect($PAGE->url, get_string('brand_deleted', 'local_sentientia_translate'),
            null, \core\output\notification::NOTIFY_INFO);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('brands_page_heading', 'local_sentientia_translate'));
echo html_writer::div(get_string('brands_intro', 'local_sentientia_translate'), 'mb-3 text-muted');

// Protected (always-on) terms — informational.
$protected = brand_manager::get_protected_terms($customerid);
echo html_writer::div(
    html_writer::tag('strong', get_string('brands_protected_label', 'local_sentientia_translate') . ': ') .
        s(implode(', ', $protected)),
    'alert alert-light small');

// Existing overrides table.
$rows = brand_manager::list_for_customer($customerid);
if (empty($rows)) {
    echo html_writer::div(get_string('brands_empty', 'local_sentientia_translate'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('brand_source', 'local_sentientia_translate'),
        get_string('brand_lang', 'local_sentientia_translate'),
        get_string('brand_target', 'local_sentientia_translate'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($rows as $r) {
        $langlabel = get_string('lang_' . $r->targetlang, 'local_sentientia_translate');
        $delform = html_writer::start_tag('form', [
            'method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'd-inline',
        ]);
        $delform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $delform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'del']);
        $delform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$r->id]);
        $delform .= html_writer::tag('button', get_string('brand_delete', 'local_sentientia_translate'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']);
        $delform .= html_writer::end_tag('form');
        $table->data[] = [
            s($r->brand_source),
            s($langlabel),
            html_writer::tag('span', s($r->brand_target), ['lang' => s($r->targetlang)]),
            $delform,
        ];
    }
    echo html_writer::table($table);
}

// Add form.
echo html_writer::tag('h4', get_string('brands_add_heading', 'local_sentientia_translate'), ['class' => 'mt-4']);
echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'form-inline',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', get_string('brand_source', 'local_sentientia_translate'),
    ['for' => 'brand_source', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'brand_source', 'name' => 'brand_source',
    'class' => 'form-control', 'required' => 'required', 'maxlength' => 128,
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', get_string('brand_lang', 'local_sentientia_translate'),
    ['for' => 'targetlang', 'class' => 'form-label']);
$langoptions = [];
foreach (brand_manager::TARGET_LANGS as $code => $label) {
    $langoptions[$code] = get_string('lang_' . $code, 'local_sentientia_translate');
}
echo html_writer::select($langoptions, 'targetlang', 'hi', false,
    ['id' => 'targetlang', 'class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', get_string('brand_target', 'local_sentientia_translate'),
    ['for' => 'brand_target', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'brand_target', 'name' => 'brand_target',
    'class' => 'form-control', 'required' => 'required', 'maxlength' => 255,
]);
echo html_writer::end_div();

echo html_writer::tag('button', get_string('brand_add', 'local_sentientia_translate'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
