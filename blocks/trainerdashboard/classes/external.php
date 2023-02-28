<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class block_trainerdashboard_external extends external_api {

	  /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_trainerslist_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_trainerslist(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_trainerslist_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = false;
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->start = $offset;
        $stable->length = $limit;
        $trainerslist=block_trainerdashboard_manager::trainerslist($stable,$filtervalues);
        $totalcount=$trainerslist['trainerslistcount'];
     

        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_trainerslist($trainerslist,$filtervalues));
        }else{
            $data['data']=array();
        }

        return [
            'totalcount' => $totalcount,
            'records' =>$data['data'],
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('notrainerslistdashboards','block_trainerdashboard'),
            'chartdata' =>json_encode($data['chartdata']),
            'traininer'=>$data['traininer']    
        ];
    }     
    /**
     * Returns description of method result value.
     */
    public static function  get_trainerslist_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'userpicture' => new external_value(PARAM_RAW, 'user picture', VALUE_OPTIONAL),
                                    'username' => new external_value(PARAM_RAW, 'user name', VALUE_OPTIONAL),
                                    'useremail' => new external_value(PARAM_RAW, 'user email', VALUE_OPTIONAL),
                                    'useropen_employeeid' => new external_value(PARAM_RAW, 'user open_employeeid', VALUE_OPTIONAL),
                                    'total_classroomtrainings' => new external_value(PARAM_INT, 'total classroom trainings', VALUE_OPTIONAL),
                                    'completed_classroomtrainings' => new external_value(PARAM_INT, 'completed classroom trainings', VALUE_OPTIONAL),
                                    'upcoming_classroomtrainings' => new external_value(PARAM_INT, 'upcoming classroom trainings', VALUE_OPTIONAL),
                                    'totaluserscovered' => new external_value(PARAM_INT, 'totaluserscovered', VALUE_OPTIONAL),
                                    'userid' => new external_value(PARAM_INT, 'user picture', VALUE_OPTIONAL),
                                    'viewmorestatus' => new external_value(PARAM_RAW, 'user picture', VALUE_OPTIONAL),
                                )
                            )
            ),
            'chartdata' => new external_value(PARAM_RAW, 'The chartdata for the service', VALUE_OPTIONAL),
            'traininer' => new external_value(PARAM_INT, 'traininer of trainerdashboards in result set'),
        ]);
    }
     /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_conductedtrainings_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_conductedtrainings(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_conductedtrainings_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->start = $offset;
        $stable->length = $limit;
        $conductedtrainings=block_trainerdashboard_manager::conductedtrainings($stable,$filtervalues);
        $totalcount=$conductedtrainings['conductedtrainingscount'];
    
        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_conductedtrainings($conductedtrainings,$filtervalues));
        }
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('noconductedtrainingsdashboards','block_trainerdashboard')
        ];
    }

    /**
     * Returns description of method result value.
     */
    public static function  get_conductedtrainings_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'id'),
                                    'classroomname' => new external_value(PARAM_RAW, 'classroom name', VALUE_OPTIONAL),
                                    'name' => new external_value(PARAM_RAW, 'name'),
                                    'date' => new external_value(PARAM_RAW, 'session startdate'),
                                    'starttime' => new external_value(PARAM_RAW, 'timelimit'),
                                    'endtime' => new external_value(PARAM_RAW, 'timelimit'),
                                    'link' => new external_value(PARAM_RAW, 'link'),
                                    'room' => new external_value(PARAM_RAW, 'room'),
                                    'status' => new external_value(PARAM_RAW, 'status'),
                                    'attendacecount' => new external_value(PARAM_RAW, 'attendacecount'),
                                    'trainer' => new external_value(PARAM_RAW, 'trainer'),
                                    'cfgwwwroot' => new external_value(PARAM_RAW, 'cfgwwwroot')
                                )
                            )
            )
        ]);
    }
      /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_depttrainingavg_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_depttrainingavg(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_depttrainingavg_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->start = $offset;
        $stable->length = $limit;
        $depttrainingavg=block_trainerdashboard_manager::depttrainingavg($stable,$filtervalues);
        $totalcount=$depttrainingavg['depttrainingavgcount'];
     

        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_depttrainingavg($depttrainingavg,$filtervalues));
        }
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('nodepttrainingavgdashboards','block_trainerdashboard')
        ];
    }

    /**
     * Returns description of method result value.
     */
    public static function  get_depttrainingavg_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'username' => new external_value(PARAM_RAW, 'name', VALUE_OPTIONAL)
                                )
                            )
            )
        ]);
    }
      /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_trainermanhours_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_trainermanhours(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_trainermanhours_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->start = $offset;
        $stable->length = $limit;
        $trainermanhours=block_trainerdashboard_manager::trainermanhours($stable,$filtervalues);
        $totalcount=$trainermanhours['trainermanhourscount'];
     

        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_trainermanhours($trainermanhours,$filtervalues));
        }
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('notrainermanhoursdashboards','block_trainerdashboard')
        ];
    }

    /**
     * Returns description of method result value.
     */
    public static function  get_trainermanhours_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'username' => new external_value(PARAM_RAW, 'name', VALUE_OPTIONAL)
                                )
                            )
            )
        ]);
    }
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_upcomingtrainings_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_upcomingtrainings(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_upcomingtrainings_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->start = $offset;
        $stable->length = $limit;
        $upcomingtrainings=block_trainerdashboard_manager::upcomingtrainings($stable,$filtervalues);
        $totalcount=$upcomingtrainings['upcomingtrainingscount'];
     

        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_upcomingtrainings($upcomingtrainings,$filtervalues));
        }
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('noupcomingtrainingsdashboards','block_trainerdashboard')
        ];
    }

    /**
     * Returns description of method result value.
     */
    public static function  get_upcomingtrainings_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'id'),
                                    'classroomname' => new external_value(PARAM_RAW, 'classroom name', VALUE_OPTIONAL),
                                    'name' => new external_value(PARAM_RAW, 'name'),
                                    'date' => new external_value(PARAM_RAW, 'session startdate'),
                                    'starttime' => new external_value(PARAM_RAW, 'timelimit'),
                                    'endtime' => new external_value(PARAM_RAW, 'timelimit'),
                                    'link' => new external_value(PARAM_RAW, 'link'),
                                    'room' => new external_value(PARAM_RAW, 'room'),
                                    'status' => new external_value(PARAM_RAW, 'status'),
                                    'attendacecount' => new external_value(PARAM_RAW, 'attendacecount'),
                                    'trainer' => new external_value(PARAM_RAW, 'trainer'),
                                    'cfgwwwroot' => new external_value(PARAM_RAW, 'cfgwwwroot'),
                                )
                            )
            )
        ]);
    }
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters.
     */
    public static function get_classrooms_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                 'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of trainerdashboards for the given criteria. The trainerdashboards
     * will be exported in a summaries format and won't include all of the
     * trainerdashboards data.
     *
     * @param int $userid Userid id to find trainerdashboards
     * @param int $contextid The context id where the trainerdashboards will be rendered
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @return array The list of trainerdashboards and total trainerdashboard count.
     */
    public static function get_classrooms(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        require_login();
        $PAGE->set_url('/blocks/trainerdashboard/dashboard.php', array());
        $PAGE->set_context($context);
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_classrooms_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'filterdata' => $filterdata
            ]
        );
        $data_object = (json_decode($dataoptions));
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->trainerdashboardstatus =$data_object->trainerdashboardstatus;
        $stable->search_query =$data_object->search_query;
        $stable->trainerid =$data_object->classroomid;
        $stable->triggertype =$data_object->triggertype;
        $stable->start = $offset;
        $stable->length = $limit;
        $classrooms=block_trainerdashboard_manager::get_classrooms($stable,$filtervalues);
        $totalcount=$classrooms['classroomscount'];
     

        $data = array();
        if($totalcount>0){
            $renderer = $PAGE->get_renderer('block_trainerdashboard');
            $data = array_merge($data,$renderer->get_classrooms($classrooms,$filtervalues));
        }
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'nodata' => get_string('noclassroomsdashboards','block_trainerdashboard')
        ];
    }

    /**
     * Returns description of method result value.
     */
    public static function  get_classrooms_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of trainerdashboards in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'nodata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'classroomname' => new external_value(PARAM_RAW, 'classroom name', VALUE_OPTIONAL),
                                    'sessioncount' => new external_value(PARAM_RAW, 'sessioncount'),
                                    'cfgwwwroot' => new external_value(PARAM_RAW, 'cfgwwwroot'),
                                )
                            )
            )
        ]);
    }

}