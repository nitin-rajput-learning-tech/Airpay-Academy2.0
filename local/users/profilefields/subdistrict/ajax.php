<?php
define('AJAX_SCRIPT', true);
use local_users\event;

require_once(dirname(__FILE__) . '/../../../../config.php');

$reason = required_param('reason',PARAM_TEXT);
global $DB;
switch ($reason) {

    case 'deletesubdistrictmodal':
        $id = required_param('componentid', PARAM_INT);
        $delid = $DB->delete_records('local_subdistrict', array('id' => $id));
        echo json_encode($delid);
    break;
}