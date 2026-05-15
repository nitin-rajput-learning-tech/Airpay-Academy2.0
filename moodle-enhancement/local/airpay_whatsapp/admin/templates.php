<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * DLT template manager — Phase A1 iter 2.
 *
 * Site-admin page for viewing the 5 seeded templates + manually
 * transitioning their status (pending → submitted → approved/rejected).
 * The DLT portal's nightly sync (iter 2+) automates the status flip;
 * this page is for the initial manual workflow before that ships.
 *
 * @package local_airpay_whatsapp
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $OUTPUT, $PAGE;

$PAGE->set_url('/local/airpay_whatsapp/admin/templates.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('templates_pagetitle', 'local_airpay_whatsapp'));
$PAGE->set_heading(get_string('templates_heading', 'local_airpay_whatsapp'));

// Handle status transitions via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $template_id  = required_param('template_id', PARAM_INT);
    $new_status   = required_param('new_status',
        PARAM_ALPHA);
    $rejection    = optional_param('rejection_reason', '', PARAM_TEXT);
    $dlt_id       = optional_param('dlt_id', '', PARAM_ALPHANUMEXT);

    try {
        // If admin is submitting a DLT ID for an approved row, update it
        // before transitioning status (so the row carries the operator's
        // template_id when status flips to approved).
        if ($new_status === 'approved' && $dlt_id !== '') {
            global $DB;
            $DB->set_field('local_airpay_dlt_templates', 'dlt_id', $dlt_id,
                ['id' => $template_id]);
        }

        \local_airpay_whatsapp\dlt_template_registry::transition_status(
            $template_id,
            $new_status,
            $rejection !== '' ? $rejection : null
        );

        redirect(
            new moodle_url('/local/airpay_whatsapp/admin/templates.php'),
            get_string('template_status_updated', 'local_airpay_whatsapp'),
            2,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect(
            new moodle_url('/local/airpay_whatsapp/admin/templates.php'),
            $e->getMessage(),
            5,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$templates = \local_airpay_whatsapp\dlt_template_registry::list_all();

// Group by template_key for display.
$grouped = [];
foreach ($templates as $t) {
    $grouped[$t->template_key][] = $t;
}

echo $OUTPUT->header();

?>
<div class="container-fluid">
    <p class="text-muted">
        <?= get_string('templates_intro', 'local_airpay_whatsapp') ?>
    </p>

    <table class="generaltable" style="width: 100%;">
        <thead>
            <tr>
                <th><?= get_string('th_template', 'local_airpay_whatsapp') ?></th>
                <th><?= get_string('th_channel', 'local_airpay_whatsapp') ?></th>
                <th><?= get_string('th_status', 'local_airpay_whatsapp') ?></th>
                <th><?= get_string('th_dlt_id', 'local_airpay_whatsapp') ?></th>
                <th><?= get_string('th_body', 'local_airpay_whatsapp') ?></th>
                <th><?= get_string('th_actions', 'local_airpay_whatsapp') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $t): ?>
                <?php
                $status_class = match ($t->status) {
                    'approved'  => 'badge-success',
                    'submitted' => 'badge-info',
                    'rejected'  => 'badge-danger',
                    default     => 'badge-secondary',
                };
                ?>
                <tr>
                    <td><code><?= s($t->template_key) ?></code></td>
                    <td><?= s($t->channel) ?></td>
                    <td>
                        <span class="badge <?= s($status_class) ?>">
                            <?= s($t->status) ?>
                        </span>
                        <?php if ($t->status === 'rejected' && $t->rejection_reason): ?>
                            <br><small class="text-danger"><?= s($t->rejection_reason) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $t->dlt_id ? '<code>' . s($t->dlt_id) . '</code>' : '—' ?></td>
                    <td>
                        <details>
                            <summary><?= get_string('show_body', 'local_airpay_whatsapp') ?></summary>
                            <pre style="white-space: pre-wrap; font-size: 12px; margin-top: 8px;"><?= s($t->body) ?></pre>
                        </details>
                    </td>
                    <td>
                        <form method="post" style="display: inline-flex; gap: 4px; flex-wrap: wrap; align-items: center;">
                            <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                            <input type="hidden" name="template_id" value="<?= (int) $t->id ?>">
                            <?php if ($t->status === 'pending'): ?>
                                <button type="submit" name="new_status" value="submitted" class="btn btn-sm btn-info">
                                    <?= get_string('btn_submit', 'local_airpay_whatsapp') ?>
                                </button>
                            <?php elseif ($t->status === 'submitted'): ?>
                                <input type="text" name="dlt_id" placeholder="DLT ID" size="14"
                                       class="form-control form-control-sm" style="display: inline-block; width: auto;">
                                <button type="submit" name="new_status" value="approved" class="btn btn-sm btn-success">
                                    <?= get_string('btn_approve', 'local_airpay_whatsapp') ?>
                                </button>
                                <button type="submit" name="new_status" value="rejected" class="btn btn-sm btn-danger">
                                    <?= get_string('btn_reject', 'local_airpay_whatsapp') ?>
                                </button>
                            <?php elseif ($t->status === 'rejected'): ?>
                                <button type="submit" name="new_status" value="pending" class="btn btn-sm btn-warning">
                                    <?= get_string('btn_redraft', 'local_airpay_whatsapp') ?>
                                </button>
                            <?php elseif ($t->status === 'approved'): ?>
                                <span class="text-muted small"><?= get_string('approved_ready', 'local_airpay_whatsapp') ?></span>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php

echo $OUTPUT->footer();
