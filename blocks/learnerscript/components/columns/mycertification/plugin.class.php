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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\pluginbase;

class plugin_mycertification extends pluginbase {

    public function init() {
        $this->fullname = get_string('mycertificates', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('mycertificates');
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
        global $CFG;
        switch ($data->column) {
            case 'startdate':
                $row->{$data->column} = !empty($row->{$data->column}) ? \local_costcenter\lib::get_userdate('d m Y H:i',$row->{$data->column}) : 'NA';
                break;
            case 'enddate':
                $row->{$data->column} = !empty($row->{$data->column}) ? \local_costcenter\lib::get_userdate('d m Y H:i',$row->{$data->column}) : 'NA';
                break;
            case 'certificatestatus':
                switch ($row->certificatestatus){
                    case 0:
                        $row->{$data->column} = get_string('new_certification','local_certification');
                        break;
                    case 1:
                        $row->{$data->column} = get_string('active_certification','local_certification');
                        break;
                    case 2:
                        $row->{$data->column} = get_string('hold_certification','local_certification');
                        break;
                    case 3:
                        $row->{$data->column} = get_string('cancel_certification','local_certification');
                        break;
                    case 4:
                        $row->{$data->column} = get_string('completed_certification','local_certification');
                        break;
                }
                break;
            case 'usercompletionstatus':
                $row->{$data->column} = ($row->{$data->column} == 1) ? 'Completed' : 'Not completed';
                break;
            case 'usercompletiondate':
                $row->{$data->column} = !empty($row->{$data->column}) ? \local_costcenter\lib::get_userdate('d m Y H:i',$row->{$data->column}) : 'NA';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
