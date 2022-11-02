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
 * @subpackage local_costcenter
 */
namespace local_costcenter;
class lib{
	public static function get_userdate($format, $timestamp = null, $timezone = 99, $fixday = false, $fixhour = true){
		// array('d', 'D', 'j', 'l', 'N', 'S', 'w', 'z', 'W', 'F', 'm', 'M', 'n', 't', 'L', 'o','Y', 'y', 'a', 'A', 'B', 'g', 'G', 'h', 'H', 'i', 's', 'u', 'e', 'I', 'O', 'P', 'T', 'Z', 'c', 'r', 'U');
		// $strftimeformatidentifiers = ['%a','%A','%d','%e','%u','%w','%W','%b','%h','%B','%m','%y','%Y', '%D', '%F', '%x', '%n', '%t', '%H', '%k', '%I', '%l', '%M', '%p', '%P', '%r', '%R', '%S', '%T', '%X', '%z', '%Z', '%c', '%s', '%%'];
  //       $formatidentifiers = ['D','l', 'd', 'j', 'N', 'w', 'W', 'M', 'M', 'F', 'm', 'y', 'Y', 'm/d/y', 'Y-m-d', 'm/d/y',"\n","\t", 'H', 'G', 'h', 'g', 'i', 'A', 'a', 'H:i A', 'H:i', 's', 'H:i', 'H:i', 'O', 'T',
  //           'D M j H:i Y', 'U',
  //           '%'];
		// $formatArray = array('d' => '%d', 'm' => '%m', 'y' => '%y', 'D' => '%D', 'M' => '%M', 'Y' => '%Y', 'H' => '%H', 'i' => '%M', 's' => '%S');
		// $format = str_replace($dateformatidentifiers, $strftimeformatidentifiers, $format);
		// strtr($format, $formatArray);
		$formatidentifiers = array('d', 'm', 'y', 'j', 'D', 'M', 'Y', 'H', 'i', 's', 'a', 'A', 'G', 'F', 'g', 'h');
		$strftimeformatidentifiers = array('%d', '%m', '%y', '%e',  '%D', '%b', '%Y', '%H', '%M', '%S', '%P', '%p', '%k', '%B', '%l', '%I');
		foreach($formatidentifiers AS $key => $identifier){
			$format = str_replace($identifier, $strftimeformatidentifiers[$key], $format);
		}
		if(is_null($timestamp)){
			 $timestamp = time();
		}
		return userdate($timestamp, $format, $timezone, $fixday, $fixhour);
	}
	public static function get_mail_userdate($user, $format, $timestamp = null, $timezone = 99, $fixday = true, $fixhour = true){
		$formatidentifiers = array('d', 'm', 'y', 'j', 'D', 'M', 'Y', 'H', 'i', 's', 'a', 'A', 'G', 'F', 'g', 'h');
		$strftimeformatidentifiers = array('%d', '%m', '%y', '%e',  '%D', '%b', '%Y', '%H', '%M', '%S', '%P', '%p', '%k', '%B', '%l', '%I');
		foreach($formatidentifiers AS $key => $identifier){
			$format = str_replace($identifier, $strftimeformatidentifiers[$key], $format);
		}
		if(is_null($timestamp)){
			 $timestamp = time();
		}
		return userdate($timestamp, $format, $timezone, $fixday, $fixhour);
	}
	public static function strip_tags_custom($content){
		// list($string, $format) = external_format_text($content, FORMAT_HTML, 1);
		// return strip_tags(($string));
		return mb_convert_encoding(clean_text(html_to_text($content)), 'UTF-8');
		//html_to_text
		// strip_tags
	}
	// public static function get_user_timezone_abbr(){
	// 	$timezone_id = get_user_timezone();

	//     if($timezone_id){
	//         $abb_list = timezone_abbreviations_list();

	//         $abb_array = array();
	//         foreach ($abb_list as $abb_key => $abb_val) {
	//             foreach ($abb_val as $key => $value) {
	//                 $value['abb'] = $abb_key;
	//                 array_push($abb_array, $value);
	//             }
	//         }

	//         foreach ($abb_array as $key => $value) {
	//             if($value['timezone_id'] == $timezone_id){
	//                 $return = strtoupper($value['abb']);
	//                 break;
	//             }
	//         }
	//     }
	//     $dateTime = new \DateTime();
	// 	$dateTime->setTimeZone(\core_date::get_user_timezone_object());
	// 	echo $dateTime->format('T');
	// 	$timezone = 'Pacific/Midway';
	// 	$dt = new \DateTime('now', \core_date::get_user_timezone_object());
	// 	echo $abbreviation = $dt->format('T');
	// 	$tz_date = date_create(date('d/m/Y'), \core_date::get_user_timezone_object());
 //            $formatted = $tz_date->format('T');
 //            return $formatted;
	//     print_object(date_default_timezone_get());exit;
	//     return $return;
	// }
}