<?php
require('../config.php');
// $roleid = required_param('roleid', PARAM_INT);
// $categoryid = required_param('categoryid', PARAM_INT);

// $context = \context_coursecat::instance($categoryid);
// role_switch_basedon_userroles($roleid, false, $context);


// function role_switch_basedon_userroles($roleid, $purge, $context){
//     global $DB, $CFG, $USER;

//     if(is_siteadmin($USER->id) || ($roleid <= 0) || $purge){
//         return false;
//     }

//     $role = $DB->get_record('role', array('id' => $roleid));
//     if(!$role){
//         print_error('nopermission');
//     }
//     // $systemcontext = context_system::instance();
//     $roles = get_user_roles($context, $USER->id);
//     print_object($roles);
//     $userroles = array();

//     foreach($roles as $r){
//         $userroles[$r->roleid] = $r->shortname;
//     }

//     $accessdata = get_empty_accessdata();
//     if(roleswitch($roleid, $context, $accessdata)){
//         return true;
//     }else{
//         return false;
//     }
// }

// /**
//      * sitelevel roleswitch as buttons.
//      *
//      * @param int $courseid A course object.
//      * @param stdClass $context usually site context.
//      * @return string HTML.
//      */
//     function roleswitch($roleid, $context, &$accessdata){

//         global $DB, $ACCESSLIB_PRIVATE, $USER;
//         $USER->access['rsw'][$context->path] = $roleid;
//        /* Get the relevant rolecaps into rdef
//         * - relevant role caps
//         *   - at ctx and above
//         *   - below this ctx
//         */

//         if (empty($context->path)) {
//             // weird, this should not happen
//             return;
//         }

//         list($parentsaself, $params) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'pc_');
//         $params['roleid'] = $roleid;
//         $params['childpath'] = $context->path.'/%';

//         $sql = "SELECT ctx.path, rc.capability, rc.permission
//                   FROM {role_capabilities} rc
//                   JOIN {context} ctx ON (rc.contextid = ctx.id)
//                  WHERE rc.roleid = :roleid AND (ctx.id $parentsaself OR ctx.path LIKE :childpath)
//               ORDER BY rc.capability"; // fixed capability order is necessary for rdef dedupe
//         $rs = $DB->get_recordset_sql($sql, $params);

//         $newrdefs = array();
//         foreach ($rs as $rd) {
//             $k = $rd->path.':'.$roleid;
//             if (isset($accessdata['rdef'][$k])) {
//                 continue;
//             }
//             $newrdefs[$k][$rd->capability] = (int)$rd->permission;
//         }
//         $rs->close();

//         // share new role definitions
//         foreach ($newrdefs as $k=>$unused) {
//             if (!isset($ACCESSLIB_PRIVATE->rolepermissions[$k])) {
//                 $ACCESSLIB_PRIVATE->rolepermissions[$k] = $newrdefs[$k];
//             }
//             $accessdata['rdef'][$k] =& $ACCESSLIB_PRIVATE->rolepermissions[$k];
//         }
//         return true;
//     }

$open_path = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path');

echo $open_path ;

// $open_path = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path',$courseid = null, $datatype = 'lowerandsamepath');

// echo $open_path ;

