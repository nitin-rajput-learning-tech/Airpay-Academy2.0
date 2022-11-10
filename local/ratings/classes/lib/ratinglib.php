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
namespace local_ratings\lib;

class ratinglib {

    public function get_specific_rating_info($itemid, $ratearea){
        global $DB, $OUTPUT;
        $consolidated_sql = "SELECT rating, COUNT(id) as count FROM {local_rating} WHERE itemid = :itemid AND ratearea LIKE :ratearea  GROUP BY rating";
        $consolidated = $DB->get_records_sql($consolidated_sql,  array('itemid' => $itemid, 'ratearea' => $ratearea));
        $total_ratings = $DB->count_records('local_rating', array('itemid' => $itemid, 'ratearea' => $ratearea)); 
        $return = array();
        foreach($consolidated as $data){
            $innerdata = array();
            $innerdata['rateheader'] = get_string('specificstar', 'local_ratings', $data->rating);
            $innerdata['bar_class'] = 'bar-'.$data->rating;
            $innerdata['ratedusers_count'] = $data->count;
            $innerdata['rating_perc'] = round(($data->count/$total_ratings)*100);
            $innerdata['rating'] = $data->rating;
            $return[$data->rating-1] = $innerdata;
        }

        for($i=1; $i<=5; $i++){
            if(!isset($return[$i-1])){
                $return[$i-1]['rateheader'] = get_string('specificstar', 'local_ratings', $i);
                $return[$i-1]['bar_class'] = 'bar-'.$i;
                $return[$i-1]['ratedusers_count'] = 0;
                $return[$i-1]['rating_perc'] = 0;
            }
        }
        ksort($return);
        // return  $OUTPUT->render_from_template('local_ratings/detailed_info', array('rows' => $return));
        return $return;
    }
    public function get_ratings_content($defaults, $filters){
        global $DB, $PAGE;
        $PAGE->set_context(\context_system::instance());
        $selectsql = "SELECT lc.id, lc.comment, lc.userid, ll.likestatus, lr.rating ";
        $countsql = "SELECT count(lc.id) ";
        $conditional_sql = " FROM {local_comment} AS lc
            LEFT JOIN {local_like} AS ll ON ll.likearea = lc.commentarea AND ll.itemid = lc.itemid AND ll.userid= lc.userid 
            LEFT JOIN {local_rating} AS lr  ON ll.likearea = lr.ratearea AND ll.itemid = lr.itemid AND ll.userid = lr.userid 
            WHERE lc.itemid = :itemid AND lc.commentarea LIKE :commentarea ";
        $params = array('itemid' => $defaults->itemid, 'commentarea' => $defaults->commentarea);
        $ratings = $DB->get_records_sql($selectsql.$conditional_sql, $params, $defaults->start, $defaults->length);
        $total = $DB->count_records_sql($countsql.$conditional_sql, $params);
        $fields = \user_picture::fields();
        $data = array();
        // print_object($ratings);
        foreach($ratings AS $rating){
            $list = array();
            $userobject = \core_user::get_user($rating->userid, $fields);
            $user_picture = new \user_picture($userobject, array('size' => 20, 'class' => 'userpic', 'link'=>false));
            $user_picture = $user_picture->get_url($PAGE);
            $userpic = $user_picture->out();
            $list['id'] = $rating->id;
            $list['userpic'] = $userpic;
            $list['userfullname'] = fullname($userobject);
            $list['rating'] = $rating->rating ? $rating->rating : 'N/A';
            // if($rating->likestatus == 1){
            //     $list['likestatus'] =  'Liked';
            // }else if ($rating->likestatus == 2){
            //     $list['likestatus'] = 'Disliked';                
            // }else{
            //     $list['likestatus'] = 'N/A';
            // }

            $list['likestatus'] = $rating->likestatus == 1 ? 'Liked' : ($rating->likestatus == 2 ? 'Disliked' : 'N/A' );
            $list['comment'] = $rating->comment ? $rating->comment : 'N/A';
            $data[] = $list;
        }
        return array('records' => $data, 'totalrecords' => $total);
    }
}