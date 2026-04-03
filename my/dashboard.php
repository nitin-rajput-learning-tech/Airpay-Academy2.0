<?php
// This file is part of Moodle - http://moodle.org/
//
// BizLMS compatibility shim: Production uses /my/dashboard.php as the
// dashboard URL. Moodle 4.5 uses /my/index.php. This file redirects
// to preserve all BizLMS navigation links that reference dashboard.php.
//
// @package    core
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../config.php');
redirect(new moodle_url('/my/index.php', $_GET));
