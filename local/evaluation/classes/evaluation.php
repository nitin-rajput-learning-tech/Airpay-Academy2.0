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
 */
namespace local_evaluation;
class evaluation {
    public static function evaluations_by_status($status = 'inprogress', $mobile = false, $plugin = 'site', $search = '', $page = 0, $perpage = 10, $id = 0, $instance = false) {
        global $DB,$USER;
        if ($status == 'inprogress') {
            $sqlquery = "SELECT a.*, eu.creatorid, eu.timemodified as joinedate, 0 as completedon ";
            $sqlcount = "SELECT COUNT(DISTINCT a.id) ";
            $sql = " FROM {local_evaluations} a , {local_evaluation_users} eu
                WHERE a.plugin = '{$plugin}' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
                AND instance = 0 AND a.visible = 1
                AND a.id NOT IN (SELECT evl.id from {local_evaluations} evl, {local_evaluation_completed} lec WHERE lec.evaluation = evl.id AND lec.userid = {$USER->id})
                AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 ";

            if(!empty($search)){
                $sql .= " AND a.name LIKE '%%{$search}%%' ";
            }
            $sql .= " order by eu.timecreated DESC";
            if ($mobile) {
                $inprogress_evaluations = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
                $count = $DB->count_records_sql($sqlcount . $sql);
                return array($inprogress_evaluations, $count);
            } else {
                $inprogress_evaluations = $DB->get_records_sql($sqlquery . $sql);
                return $inprogress_evaluations;
            }
        } else if ($status == 'completed') {
            $sqlquery = "SELECT a.*, eu.timemodified as joinedate, ec.timemodified as completedon ";
            $sqlcount = "SELECT COUNT(DISTINCT a.id) ";
            $sql = " from {local_evaluations} a, {local_evaluation_completed} ec, {local_evaluation_users} eu where a.plugin = '{$plugin}' AND ec.evaluation = a.id AND ec.userid = {$USER->id} AND a.id = ec.evaluation AND eu.userid = {$USER->id} AND a.evaluationmode LIKE 'SE' AND a.deleted != 1  ";
            if(!empty($search)){
                $sql .= " AND a.name LIKE '%%{$search}%%'";
            }
            $sql .= " order by ec.timemodified DESC";

            if ($mobile) {
                $completed_evaluations = $DB->get_records_sql($sqlquery . $sql);

                $count = $DB->count_records_sql($sqlcount . $sql);
                return array($completed_evaluations, $count);
            } else {
                $completed_evaluations = $DB->get_records_sql($sqlquery . $sql);
                return $completed_evaluations;
            }
        } else {
            $sqlquery = "SELECT e.*, eu.creatorid, eu.timemodified as joinedate, ec.timemodified as completedon ";
            $sqlcount = "SELECT COUNT(DISTINCT e.id) ";
            $pluginsql = "";
            if (!$instance) {
                $pluginsql = " e.plugin = '{$plugin}' AND ";
            }
            $sql = " FROM {local_evaluations} e
                     JOIN {local_evaluation_users} eu ON eu.evaluationid = e.id
                     LEFT JOIN {local_evaluation_completed} ec ON ec.evaluation = e.id AND ec.userid = {$USER->id}
                     WHERE $pluginsql eu.userid = {$USER->id} AND e.evaluationmode LIKE 'SE' AND e.deleted != 1  ";
            if (!empty($search)) {
                $sql .= " AND e.name LIKE '%%{$search}%%'";
            }
            if ($id > 0) {
                $sql .= " AND e.id = {$id}";
            }
            $sql .= " order by eu.timecreated DESC";

            if ($mobile) {

                $evaluations = $DB->get_records_sql($sqlquery . $sql, array(), $page * $perpage, $perpage);
                $count = $DB->count_records_sql($sqlcount . $sql);

                return array($evaluations, $count);
            } else {
                $evaluations = $DB->get_records_sql($sqlquery . $sql);
                return $evaluations;
            }
        }
    }
}
