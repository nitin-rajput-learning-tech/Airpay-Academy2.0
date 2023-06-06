<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_request
 */
//require_once($CFG->libdir. '/coursecatlib.php');
?>

<style>
    select.my_dropdown{width:200px}
</style>

 <?php
 /**
  * Display a list of pending modules
  * for the Admin
  *
  */
function local_request_display_admin_list($pendinglist, $includerightpanel, $includeleftcheckbox, 
                                           $editcatavailable, $rightpaneltype) {

    global $CFG, $DB;

    $outputhtml = '';

    $page1_fieldname1 = $DB->get_field_select('local_crequest_config', 'value', "varname='page1_fieldname1'");
    $page1_fieldname2 = $DB->get_field_select('local_crequest_config', 'value', "varname='page1_fieldname2'");

    $counter = 1;

    foreach ($pendinglist as $rec) {
			// Get a list of all the lecturers
			$lecturerhtml = '';
            $req_values = $rec->req_values;

			if (!empty($req_values)) {
				if (! $course = $DB->get_record("course", array("id"=> $req_values))) {
					   // If the course doesn't exist anymore, just let the process continue..
					} else { // Otherwise, start the process
						    $context = get_context_instance(CONTEXT_COURSE, $course->id);
						    if ($managerroles = get_config('', 'coursemanager')) {
									$coursemanagerroles = explode(',', $managerroles);
									foreach ($coursemanagerroles as $roleid) {
									    $role = $DB->get_record('role', array('id'=>$roleid));
									    $canseehidden = has_capability('moodle/role:viewhiddenassigns', $context);
									    $roleid = (int) $roleid;
									    $namesarray = null;
									    if ($users = get_role_users($roleid, $context, true, '', 'u.lastname ASC', $canseehidden)) {

										    foreach ($users as $teacher) {
										    $fullname = fullname($teacher, has_capability('moodle/site:viewfullnames', $context));
										    $namesarray[] = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.
										                    $teacher->id.'&amp;course='.SITEID.'">'.$fullname.'</a>';
										}
									    }
									}
									if (!empty($namesarray)) {
									    $lecturerhtml =  implode(', ', $namesarray);

									}
						    }
					}
            } else {
				// Get the id from who created the record, and get their username

				$fullname = $DB->get_field('user', 'username', array('id'=>$rec->createdbyid));

				$lecturerhtml = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.
						                    $rec->createdbyid.'&amp;course='.SITEID.'">'.$fullname.'</a>';
			}

			//Get the latest comment
			$latestComment = '';
			$currentmodid = $rec->id;


			$wherequery = "instanceid = '$currentmodid'";
		 	$modrecords = $DB->get_recordset_select('local_crequest_comments', $wherequery);

		    foreach ($modrecords as $record) {

				$latestComment = $record->message;
				if (strlen($latestComment) > 55) {
					$latestComment = clean_text(substr($latestComment, 0, 55));
					$pagename = basename($_SERVER['PHP_SELF']);

					if ($pagename == 'module_manager.php') {
					    $latestComment .= '... <a href="comment.php?type=userq&id=' . $currentmodid . '">['.get_string('viewmore','local_crequest').']</a>';

					} else {
						$latestComment .= '... <a href="comment.php?type=adminq&id=' . $currentmodid . '">['.get_string('viewmore','local_crequest').']</a>';
					}
				}
		    }

			// Check if shortname exists
			$shortnameexists = $DB->record_exists('course', array('shortname'=>$rec->modcode));
			$shortnameexistsmode = $DB->record_exists('course', array('shortname'=>$rec->modcode . ' - ' . $rec->modmode));

			$disabledhtml = '';
			if ($shortnameexists == 1 || $shortnameexistsmode == 1) {
				$disabledhtml = 'disabled="disabled"';
			}
			$outputhtml .= '
			<div id="existingrequest" style="background: transparent;">
			<div style="float:left; padding-bottom:20px; width:100%">
			<p style="font-size:16px;"><br><b>[' . get_string('Request','local_crequest'). ' ID #'.$rec->id.']</b></p>


			';
			if ($includeleftcheckbox == true) {
					$outputhtml .= '<input type="checkbox" id="' . $rec->id . '" name="groupedcheck" onClick="addIdToList(' . $rec->id . ')" value="' . $rec->id . '" '.$disabledhtml.'/>
									<br>';
		    }

		 // ---------- Additional Controls -------------------------------
		 if ($includerightpanel == true) {

				if ($rightpaneltype == 'admin_queue') {
						$outputhtml .= '
						<div style="font-size:14px; padding-bottom:5px">
								<a href="#" onclick="quickApproveConfirm('. $rec->id .',\''.get_string('quickapprove_desc','local_crequest').'\')"><img src="icons/list/quick.png"/> ' . get_string('quickapprove','local_crequest'). '</a>

								<a href="admin/approve_course.php?id=' . $rec->id .'"><img src="icons/list/approve.png"/> ' . get_string('approve','local_crequest'). '</a>


								<a href="admin/deny_course.php?id=' . $rec->id .'"><img src="icons/list/deny.png"/> ' . get_string('deny','local_crequest'). '</a>

								<a href="course_request.php?mode=2&edit=' . $rec->id .'"><img src="icons/list/edit.png"/> ' . get_string('edit','local_crequest'). '</a>

								<a href="#" onclick="cancelConfirm('. $rec->id .',\''.get_string('configure_delete','local_crequest').'\')" href="#"><img src="icons/list/delete.png"/> ' . get_string('delete','local_crequest'). '</a>
								<a href="admin/comment.php?type=adminq&id=' . $rec->id . '"><img src="icons/list/comment.png"/> ' . get_string('addviewcomments','local_crequest'). '</a>
						</div>

						';

		}
        else if ($rightpaneltype == 'admin_arch') {
		        $outputhtml .= '
					<div style="float:left; font-size:14px; padding-bottom:5px">

							<a onclick="cancelConfirm('. $rec->id .', \'delete\')" href="#"><img src="icons/list/delete.png"/>' . get_string('delete','local_crequest'). '</a>

							<A href="admin/comment.php?type=adminarch&id=' . $rec->id . '"><img src="icons/list/comment.png"/>' . get_string('addviewcomments','local_crequest'). '</a>

					</div>
				';
		}
    	else if ($rightpaneltype == 'user_manager') {

    					$outputhtml .= '
    			<div style="float: left; font-size:14px; padding-bottom:5px">

    					<A href="view_summary.php?id=' . $rec->id .'"><img src="icons/list/open.png"/> '.get_string('view','local_crequest').'</a>

    					<A href="course_request.php?mode=2&edit=' . $rec->id .'"><img src="icons/list/edit.png"/> '.get_string('edit','local_crequest').'</a>

    					<a onclick="cancelConfirm('. $rec->id .',\''.get_string('requestConfirmCancel','local_crequest').'\')" href="#"><img src="icons/list/deny.png"/> '.get_string('cancel','local_crequest').'</a>

    					<A href="comment.php?type=userq&id=' . $rec->id . '"><img src="icons/list/comment.png"/> '.get_string('addviewcomments','local_crequest').'</a>


    			</div>		';
    	}
		else if ($rightpaneltype == 'user_history') {
			$outputhtml .= '
				<div style="float: left; font-size:14px; padding-bottom:5px">

						<a href="view_summary.php?id=' . $rec->id .'"> <img src="icons/list/open.png"/> '.get_string('view','local_crequest').'</a>

						<a href="comment.php?type=userarch&id=' . $rec->id . '"> <img src="icons/list/comment.png"/> '.get_string('addviewcomments','local_crequest').'</a>

				</div>';
		}
     }
	// ------------------ END admin controls ----------------------

			$outputhtml .='



			 <table width="100%" style="overflow:hidden;" cellpadding="3" >

				<tr>
					<td width="25%">
						<b>' . get_string('status','local_crequest'). ':</b>
					</td>
					<td style="width:100%">
						'. $rec->status . '
					</td>
				</tr>
				';

			// Check if shortname exists
			if ($rightpaneltype == 'admin_queue') {
						if ($shortnameexists == 1 || $shortnameexistsmode == 1) {
			    			$outputhtml .= '
			    			<tr>

								<td width="25%">
									<b><span style="color:red">' . get_string('displayListWarningTitle','local_crequest'). ':</span></b>
								</td>
								<td>
									<span style="color:red">'. get_string('displayListWarningSideText','local_crequest') . '</span>
								</td>
							</tr>
			    			';
						}
			}

			$outputhtml .= '
				<tr>
					<td width="25%">
						<b>' . get_string('creationdate','local_crequest'). ':</b>
					</td>
					<td>
						'. $rec->createdate . '
					</td>
				</tr>

				<tr>
					<td width="25%">
						<b>' . get_string('requesttype','local_crequest'). ':</b>
					</td>
					<td>
						'. $rec->req_type . '
					</td>
				</tr>

				<tr>
					<td width="25%">
						<b>' . $page1_fieldname1 . ':</b>
					</td>
					<td>
						'. $rec->modcode . '
					</td>
				</tr>

				<tr>
					<td width="25%">
						<b>'. $page1_fieldname2 .':</b>
					</td>
					<td>
						'. $rec->modname . '
					</td>
				</tr>';

				if (isset($rec->modmode)) {
				 $selectedmodname = $DB->get_field_select('local_crequest_config', 'value', "varname = 'page1_fielddesc3'");

				$outputhtml .= '
						<tr>
						<td width="25%">
							<b> ' . $selectedmodname. ': </b>
						</td>
						<td>
							'. $rec->modmode . '
						</td>
					</tr>';
				}

	 $catlisthtml = '';
	if ($editcatavailable == true) {
		$movetocategories = array();
        $notused = array();
        $movetocategories += core_course_category::make_categories_list();
      	$cateDrop =  html_writer::select($movetocategories, 'cat'.$rec->id, $rec->cate, null);
 	    $catlisthtml .= '<div id="catname" class="catname">'.$cateDrop . '';
		$catlisthtml .=  '<input id="clickMe" type="button" value="'.get_string('update','local_crequest').'" onclick="saveChangedCategory(\''.$rec->id.'\')" /></div>';

	} else {

		if (!empty($rec->cate)) {
			$catlisthtml .= $DB->get_field_select('course_categories', 'name', "id =" . $rec->cate);
		} else {
			$catlisthtml = '<i>None Selected</i>';
		}

	}
			   $outputhtml .= '
					<tr>
					<td width="25%">
						<b> ' . get_string('selectedcategory', 'local_crequest'). ': </b>
					</td>
					<td>
						'. $catlisthtml . '
					</td>
				</tr>';

				if 	(isset($rec->modkey)) {

					$outputhtml .= '
						<tr>
							<td width="25%">
								<b> ' . get_string('configure_EnrolmentKey','local_crequest'). ':</b>
							</td>
							<td>
								'. $rec->modkey . '
							</td>
						</tr>';

				}


				$outputhtml .= '
				' . local_crequest_generate_summary($rec->id, $rec->formid) . '

				<tr>
					<td width="25%">
						<b>' . get_string('originator','local_crequest'). ':</b>
					</td>
					<td>
						' . $lecturerhtml . '
					</td>

				</tr>


				<tr>
					<td width="25%">
					&nbsp;
					</td>
					<td>
					&nbsp;
					</td>

				</tr>

				<tr>
					<td width="25%">
						<b>' . get_string('comments','local_crequest'). ':</b>
					</td>
					<td>
						'. $latestComment . '
					</td>

				</tr>
			 </table>
			 </div>
			 ';



		$outputhtml .= '</div>';
		$counter++;
	}


return $outputhtml;
}



/**
* Generate a summary
*/
function local_crequest_generate_summary($recordid, $formid) {

    global $CFG, $DB;

    $generatedhtml = '';

    // Get the form fields from the database.
    $wherequery = "formid = '$formid'";

    $modrecords = $DB->get_records('local_crequest_formfields', array('formid'=>$formid), $sort='position ASC');

    $counter = 1;

    foreach ($modrecords as $record) {
        $fieldidname = 'c' . $counter;
        $generatedhtml .= '<tr>';
        $generatedhtml .= '  <td width="25%">';
        $generatedhtml .= '  <b>' . $record->lefttext . ': </b>';
        $generatedhtml .= ' </td>';
        $generatedhtml .= '	<td>';
        $generatedhtml .= $DB->get_field('local_crequest_records', $fieldidname, array('id'=>$recordid));
        $generatedhtml .= '	</td>';
        $generatedhtml .= '</tr>';

        $counter++;
    }


	return $generatedhtml;
}



