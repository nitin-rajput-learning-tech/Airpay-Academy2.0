<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase G.1 (2026-05-08) — evaluation TEMPLATE export.
//
// Streams a JSON file containing the evaluation form + questions
// (no responses). Format is reversible by import_template.php.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_evaluation:manage', $context);

$id = required_param('id', PARAM_INT);

$eval = $DB->get_record('local_sentientia_evaluation', ['id' => $id], '*', MUST_EXIST);

$payload = \local_sentientia_evaluation\evaluation_manager::export_template($id);

$slug = preg_replace('/[^A-Za-z0-9_-]+/', '_',
    strtolower((string) $eval->name)) ?: 'evaluation';
$filename = 'eval-template-' . $slug . '-' . date('Ymd-His', time()) . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
