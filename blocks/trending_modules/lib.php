<?php
function block_trending_modules_output_fragment_get_trending_popup($args){
	global $OUTPUT, $PAGE;
	$lib = new block_trending_modules\lib();
	$args = new stdClass();
	$args->limitfrom = 0;
	$args->limitnum = 3;
	$args->rateWidth = 12;
	$data = $lib->user_trending_modules($args);
	$enableviewmore = count($data);
	$renderer = $PAGE->get_renderer('block_trending_modules');
	$checkbox = $renderer->show_preference_setting_user();
	$content = $OUTPUT->render_from_template('block_trending_modules/block_content', array('records'=> $data, 'enableviewmore' => $enableviewmore, 'checkbox'=> $checkbox, 'enableDesc' => False));
	ob_start();
	$o = $content;
	ob_end_clean();
	return $o;
}
function block_trending_modules_render_navbar_output(){
	global $PAGE;
	$PAGE->requires->js_call_amd('block_trending_modules/trending_modules', 'load', array());
	$PAGE->requires->js_call_amd('block_trending_modules/trending_modules', 'init');
	$PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
   // $PAGE->requires->js_call_amd('local_catalog/courseinfo', 'load', array());
}
