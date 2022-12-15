<?php

use block_learnerscript\local\pluginbase;

class plugin_myclassrooms extends pluginbase {

    public function init() {
        $this->fullname = get_string('classroom_reportsdetails', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('myclassrooms');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

     // Data -> Plugin configuration data.
    // Row
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
           global $DB, $CFG;
       switch ($data->column) {
            case 'startdate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-M-Y',$row->{$data->column}) : 'NA';
            break;
            case 'enddate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-M-Y',$row->{$data->column}) : 'NA';
            break;
            case 'classroomstatus':
                if ($row->classroomstatus == 0) {
                    $row->{$data->column} = 'New';
                } else if ($row->classroomstatus == 1) {
                    $row->{$data->column} = 'Active';
                } else if ($row->classroomstatus == 2) {
                    $row->{$data->column} = 'Hold';
                } else if ($row->classroomstatus == 3) {
                    $row->{$data->column} = 'Cancel';
                } else if ($row->classroomstatus == 4) {
                    $row->{$data->column} = 'Completed';
                }
            break;
            case 'usercompletionstatus':
                $row->{$data->column} = ($row->{$data->column} == 1) ? 'Completed' : 'Not Completed';
                break;
            case 'usercompletiondate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-M-Y',$row->{$data->column}) : 'NA';
                break;
        }
        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}