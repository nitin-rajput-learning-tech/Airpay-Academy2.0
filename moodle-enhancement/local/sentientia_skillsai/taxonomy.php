<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — canonical taxonomy + business-impact
 * mapping surface (P0.1.0 MVP).
 *
 * Lists the per-tenant canonical taxonomy (human-approved skills) and, when
 * the impact_map flag is ON, lets a curator attach skill -> business-impact
 * mappings (metric + weight) so the gap feed can be ranked by business
 * priority.
 *
 * Gated by the master flag + :manage_taxonomy capability + tenant scope.
 *
 * @package local_sentientia_skillsai
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_skillsai\taxonomy_manager;
use local_sentientia_skillsai\impact_manager;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_skillsai');
}

require_capability('local/sentientia_skillsai:manage_taxonomy', $context);

$PAGE->set_url('/local/sentientia_skillsai/taxonomy.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('taxonomy_page_title', 'local_sentientia_skillsai'));
$PAGE->set_heading(get_string('taxonomy_page_heading', 'local_sentientia_skillsai'));

$manageall = has_capability('local/sentientia_skillsai:manage_all', $context);
$tenantroot = taxonomy_manager::tenant_root_for($USER);

$impacton = class_exists('\\local_sentientia_platform\\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.impact_map');

// ── POST: add an impact mapping ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $impacton) {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'addimpact') {
        $taxonomyid = required_param('taxonomyid', PARAM_INT);

        // Tenant access guard on the taxonomy node before mapping it.
        $node = $DB->get_record(taxonomy_manager::TAXONOMY_TABLE, ['id' => $taxonomyid], '*', MUST_EXIST);
        if (!$manageall && class_exists('\\local_sentientia_platform\\tenant')) {
            \local_sentientia_platform\tenant::require_access((int)$node->costcenterid);
        }

        $metric = trim(required_param('metric_name', PARAM_TEXT));
        $detail = trim(optional_param('metric_detail', '', PARAM_TEXT));
        $weight = optional_param('weight', 3, PARAM_INT);

        if ($metric !== '') {
            impact_manager::create($taxonomyid, $metric, $detail, $weight, (int)$USER->id);
            redirect($PAGE->url, get_string('impact_added', 'local_sentientia_skillsai'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

// Site admins see all tenants; others see their own.
$nodes = $manageall
    ? array_values($DB->get_records(taxonomy_manager::TAXONOMY_TABLE,
        ['status' => taxonomy_manager::TAX_ACTIVE], 'costcenterid, category, name'))
    : taxonomy_manager::list_taxonomy($tenantroot);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('taxonomy_page_heading', 'local_sentientia_skillsai'));
echo html_writer::div(get_string('taxonomy_intro', 'local_sentientia_skillsai'), 'mb-3 text-muted');

if (!$impacton) {
    echo html_writer::div(get_string('impact_flag_off', 'local_sentientia_skillsai'),
        'alert alert-info', ['role' => 'status']);
}

if (empty($nodes)) {
    echo html_writer::div(get_string('taxonomy_empty', 'local_sentientia_skillsai'),
        'alert alert-info', ['role' => 'status']);
    echo $OUTPUT->footer();
    return;
}

foreach ($nodes as $node) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h5',
        format_string($node->name) .
        html_writer::tag('span', s($node->category), ['class' => 'badge bg-secondary ms-2']),
        ['class' => 'card-title']);
    if (!empty($node->description)) {
        echo html_writer::tag('p', format_text($node->description, FORMAT_PLAIN), ['class' => 'small text-muted']);
    }

    // Existing impact mappings.
    $impacts = impact_manager::list_for_node((int)$node->id);
    if (!empty($impacts)) {
        $items = '';
        foreach ($impacts as $im) {
            $items .= html_writer::tag('li',
                s($im->metric_name) .
                html_writer::tag('span',
                    get_string('impact_weight_badge', 'local_sentientia_skillsai', (int)$im->weight),
                    ['class' => 'badge bg-primary ms-2']));
        }
        echo html_writer::tag('ul', $items, ['class' => 'small']);
    }

    // Add-impact form (only when the impact flag is ON).
    if ($impacton) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false),
            'class' => 'row g-2 align-items-end']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addimpact']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'taxonomyid', 'value' => (int)$node->id]);

        echo html_writer::start_div('col-md-5');
        echo html_writer::tag('label', get_string('impact_metric', 'local_sentientia_skillsai'),
            ['class' => 'form-label small fw-bold']);
        echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'metric_name',
            'class' => 'form-control form-control-sm', 'maxlength' => 200]);
        echo html_writer::end_div();

        echo html_writer::start_div('col-md-4');
        echo html_writer::tag('label', get_string('impact_detail', 'local_sentientia_skillsai'),
            ['class' => 'form-label small fw-bold']);
        echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'metric_detail',
            'class' => 'form-control form-control-sm']);
        echo html_writer::end_div();

        echo html_writer::start_div('col-md-2');
        echo html_writer::tag('label', get_string('impact_weight', 'local_sentientia_skillsai'),
            ['class' => 'form-label small fw-bold']);
        echo html_writer::select([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], 'weight', 3, false,
            ['class' => 'form-control form-control-sm']);
        echo html_writer::end_div();

        echo html_writer::start_div('col-md-1');
        echo html_writer::tag('button', get_string('impact_add', 'local_sentientia_skillsai'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']);
        echo html_writer::end_div();

        echo html_writer::end_tag('form');
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
