<?php
require(__DIR__ . '/../../config.php');
$class = new block_trending_modules\querylib();
// $pretags = $class->get_my_tags_info();
$args = new stdClass();
// $args->limitfrom = 0;
// $args->limitnum = 10;
// $tags = $class->get_modules_data($args);
$data = $class->get_trending_modules_query();//'local_courses', 'courses'
// print_object($data);
// echo strtotime(date('d m Y', time()));
$todaytime = strtotime();
    	$lastweektime = $todaytime -(7*86400);
    	$dates = explode('-',\local_costcenter\lib::get_userdate('d m Y H:i', time()));
    	print_object($dates);
// print_object($CFG->dbtype);
// $newclass = new block_trending_modules\lib();
// $objectdata = $newclass->user_trending_modules($args);
// print_object($objectdata);

// // print_object(implode($tags));
// $str = '';
// foreach($tags AS $tag){
// 	$str .= implode(',', $tag); 
// }