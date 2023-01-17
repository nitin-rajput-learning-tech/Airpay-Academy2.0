<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_epsilon\output;

use moodle_url;
use html_writer;
use get_string;
use context_system;
use core_component;
use context_course;

use coding_exception;
use tabobject;
use tabtree;
use custom_menu_item;
use custom_menu;
use block_contents;
use navigation_node;
use action_link;
use stdClass;
use preferences_groups;
use action_menu;
use help_icon;
use single_button;
use pix_icon;

use paging_bar;
use context_user;
use context_coursecat;
use action_menu_filler;
use action_menu_link_secondary;
use core_text;
use user_picture;
use costcenter;
use theme_config;
defined('MOODLE_INTERNAL') || die;

/**
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_epsilon
 * @copyright  2012 Bas Brands, www.basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {

    private $enable_edit_switch = true;
    /**
     * Returns HTML to display a "Turn editing on/off" button in a form.
     *
     * @param moodle_url $url The URL + params to send through when clicking the button
     * @param string $method
     * @return string HTML the button
     */
    public function edit_button(moodle_url $url, string $method = 'post') {

        if ($this->page->theme->haseditswitch) {
            return;
        }
        $url->param('sesskey', sesskey());
        if ($this->page->user_is_editing()) {
            $url->param('edit', 'off');
            $editstring = get_string('turneditingoff');
        } else {
            $url->param('edit', 'on');
            $editstring = get_string('turneditingon');
        }
        $button = new \single_button($url, $editstring, $method, ['class' => 'btn btn-primary']);
        return $this->render_single_button($button);
    }
    public function seteditswtich_display($status){
        $this->enable_edit_switch = $status;
    }
    /**
     * Create a navbar switch for toggling editing mode.
     *
     * @return string Html containing the edit switch
     */
    public function edit_switch() {
        if ($this->page->user_allowed_editing() && $this->enable_edit_switch) {

            $temp = (object) [
                'legacyseturl' => (new moodle_url('/editmode.php'))->out(false),
                'pagecontextid' => $this->page->context->id,
                'pageurl' => $this->page->url,
                'sesskey' => sesskey(),
            ];
            if ($this->page->user_is_editing()) {
                $temp->checked = true;
            }
            return $this->render_from_template('core/editswitch', $temp);
        }
    }
    /**
     * Renders the "breadcrumb" for all pages in epsilon.
     *
     * @return string the HTML for the navbar.
     */
    public function navbar(): string {
        $newnav = new \theme_epsilon\epsilonnavbar($this->page);
        return $this->render_from_template('core/navbar', $newnav);
    }

    public function custom_language_menu(){
        $langs = get_string_manager()->get_list_of_translations();
        if(count($langs) > 1){
            $select = (new \core\output\language_menu($this->page))->export_for_single_select($this);
            return $this->render_from_template('core/single_select', $select);
        }
    }
    /**
     * Renders the context header for the page.
     *
     * @param array $headerinfo Heading information.
     * @param int $headinglevel What 'h' level to make the heading.
     * @return string A rendered context header.
     */
    public function context_header($headerinfo = null, $headinglevel = 1): string {
        global $DB, $USER, $CFG, $SITE;
        require_once($CFG->dirroot . '/user/lib.php');
        $context = $this->page->context;
        $heading = null;
        $imagedata = null;
        $subheader = null;
        $userbuttons = null;

        // Make sure to use the heading if it has been set.
        if (isset($headerinfo['heading'])) {
            $heading = $headerinfo['heading'];
        } else {
            $heading = $this->page->heading;
        }

        // The user context currently has images and buttons. Other contexts may follow.
        if ((isset($headerinfo['user']) || $context->contextlevel == CONTEXT_USER) && $this->page->pagetype !== 'my-index') {
            if (isset($headerinfo['user'])) {
                $user = $headerinfo['user'];
            } else {
                // Look up the user information if it is not supplied.
                $user = $DB->get_record('user', array('id' => $context->instanceid));
            }

            // If the user context is set, then use that for capability checks.
            if (isset($headerinfo['usercontext'])) {
                $context = $headerinfo['usercontext'];
            }

            // Only provide user information if the user is the current user, or a user which the current user can view.
            // When checking user_can_view_profile(), either:
            // If the page context is course, check the course context (from the page object) or;
            // If page context is NOT course, then check across all courses.
            $course = ($this->page->context->contextlevel == CONTEXT_COURSE) ? $this->page->course : null;

            if (user_can_view_profile($user, $course)) {
                // Use the user's full name if the heading isn't set.
                if (empty($heading)) {
                    $heading = fullname($user);
                }

                $imagedata = $this->user_picture($user, array('size' => 100));

                // Check to see if we should be displaying a message button.
                if (!empty($CFG->messaging) && has_capability('moodle/site:sendmessage', $context)) {
                    $userbuttons = array(
                        'messages' => array(
                            'buttontype' => 'message',
                            'title' => get_string('message', 'message'),
                            'url' => new moodle_url('/message/index.php', array('id' => $user->id)),
                            'image' => 'message',
                            'linkattributes' => \core_message\helper::messageuser_link_params($user->id),
                            'page' => $this->page
                        )
                    );

                    if ($USER->id != $user->id) {
                        $iscontact = \core_message\api::is_contact($USER->id, $user->id);
                        $contacttitle = $iscontact ? 'removefromyourcontacts' : 'addtoyourcontacts';
                        $contacturlaction = $iscontact ? 'removecontact' : 'addcontact';
                        $contactimage = $iscontact ? 'removecontact' : 'addcontact';
                        $userbuttons['togglecontact'] = array(
                                'buttontype' => 'togglecontact',
                                'title' => get_string($contacttitle, 'message'),
                                'url' => new moodle_url('/message/index.php', array(
                                        'user1' => $USER->id,
                                        'user2' => $user->id,
                                        $contacturlaction => $user->id,
                                        'sesskey' => sesskey())
                                ),
                                'image' => $contactimage,
                                'linkattributes' => \core_message\helper::togglecontact_link_params($user, $iscontact),
                                'page' => $this->page
                            );
                    }

                    $this->page->requires->string_for_js('changesmadereallygoaway', 'moodle');
                }
            } else {
                $heading = null;
            }
        }

        $prefix = null;
        if ($context->contextlevel == CONTEXT_MODULE) {
            if ($this->page->course->format === 'singleactivity') {
                $heading = $this->page->course->fullname;
            } else {
                $heading = $this->page->cm->get_formatted_name();
                $imagedata = $this->pix_icon('monologo', '', $this->page->activityname, ['class' => 'activityicon']);
                $purposeclass = plugin_supports('mod', $this->page->activityname, FEATURE_MOD_PURPOSE);
                $purposeclass .= ' activityiconcontainer';
                $purposeclass .= ' modicon_' . $this->page->activityname;
                $imagedata = html_writer::tag('div', $imagedata, ['class' => $purposeclass]);
                $prefix = get_string('modulename', $this->page->activityname);
            }
        }


        $contextheader = new \context_header($heading, $headinglevel, $imagedata, $userbuttons, $prefix);
        return $this->render_context_header($contextheader);
    }

     /**
      * Renders the header bar.
      *
      * @param context_header $contextheader Header bar object.
      * @return string HTML for the header bar.
      */
    protected function render_context_header(\context_header $contextheader) {

        // Generate the heading first and before everything else as we might have to do an early return.
        if (!isset($contextheader->heading)) {
            $heading = $this->heading($this->page->heading, $contextheader->headinglevel, 'h2');
        } else {
            $heading = $this->heading($contextheader->heading, $contextheader->headinglevel, 'h2');
        }

        // All the html stuff goes here.
        $html = html_writer::start_div('page-context-header');

        // Image data.
        if (isset($contextheader->imagedata)) {
            // Header specific image.
            $html .= html_writer::div($contextheader->imagedata, 'page-header-image mr-2');
        }

        // Headings.
        if (isset($contextheader->prefix)) {
            $prefix = html_writer::div($contextheader->prefix, 'text-muted text-uppercase small line-height-3');
            $heading = $prefix . $heading;
        }
        $html .= html_writer::tag('div', $heading, array('class' => 'page-header-headings'));

        // Buttons.
        if (isset($contextheader->additionalbuttons)) {
            $html .= html_writer::start_div('btn-group header-button-group');
            foreach ($contextheader->additionalbuttons as $button) {
                if (!isset($button->page)) {
                    // Include js for messaging.
                    if ($button['buttontype'] === 'togglecontact') {
                        \core_message\helper::togglecontact_requirejs();
                    }
                    if ($button['buttontype'] === 'message') {
                        \core_message\helper::messageuser_requirejs();
                    }
                    $image = $this->pix_icon($button['formattedimage'], $button['title'], 'moodle', array(
                        'class' => 'iconsmall',
                        'role' => 'presentation'
                    ));
                    $image .= html_writer::span($button['title'], 'header-button-title');
                } else {
                    $image = html_writer::empty_tag('img', array(
                        'src' => $button['formattedimage'],
                        'role' => 'presentation'
                    ));
                }
                $html .= html_writer::link($button['url'], html_writer::tag('span', $image), $button['linkattributes']);
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * See if this is the first view of the current cm in the session if it has fake blocks.
     *
     * (We track up to 100 cms so as not to overflow the session.)
     * This is done for drawer regions containing fake blocks so we can show blocks automatically.
     *
     * @return boolean true if the page has fakeblocks and this is the first visit.
     */
    public function firstview_fakeblocks(): bool {
        global $SESSION;

        $firstview = false;
        if ($this->page->cm) {
            if (!$this->page->blocks->region_has_fakeblocks('side-pre')) {
                return false;
            }
            if (!property_exists($SESSION, 'firstview_fakeblocks')) {
                $SESSION->firstview_fakeblocks = [];
            }
            if (array_key_exists($this->page->cm->id, $SESSION->firstview_fakeblocks)) {
                $firstview = false;
            } else {
                $SESSION->firstview_fakeblocks[$this->page->cm->id] = true;
                $firstview = true;
                if (count($SESSION->firstview_fakeblocks) > 100) {
                    array_shift($SESSION->firstview_fakeblocks);
                }
            }
        }
        return $firstview;
    }

    /**
     * Displays Leftmenu links added from respective plugins using the function in lib.php as "plugintype_pluginname_leftmenunode()
     * The links are injected in the left menu.
     *
     * @return HTML
     */
    public function left_navigation_quick_links(){
        global $DB, $CFG, $USER, $PAGE;
        $systemcontext = context_system::instance();
        $core_component = new core_component();
        $block_content = '';
        $local_pluginlist = $core_component::get_plugin_list('local');
        $block_pluginlist = $core_component::get_plugin_list('block');

        $block_content .= html_writer::start_tag('ul', array('class'=>'pull-left row-fluid user_navigation_ul'));
            //======= Dasboard link ========//
            $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard'));
                $button1 = html_writer::link($CFG->wwwroot.'/my/dashboard.php', '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('leftmenu_dashboard', 'theme_epsilon').'</span>', array('class'=>'user_navigation_link'));
                $block_content .= $button1;
            $block_content .= html_writer::end_tag('li');

            //=======Leader Dasboard link ========//
//             $gamificationb_plugin_exist = $core_component::get_plugin_directory('block', 'gamification');
//             $gamificationl_plugin_exist = $core_component::get_plugin_directory('local', 'gamification');
//             if($gamificationl_plugin_exist && $gamificationb_plugin_exist && (has_capability('local/gamification:view
// ',$systemcontext) || is_siteadmin() )){
//                 $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_gamification_leaderboard', 'class'=>'pull-left user_nav_div notifications'));
//                 $gamification_url = new moodle_url('/blocks/gamification/dashboard.php');
//                 $gamification = html_writer::link($gamification_url, '<i class="fa fa-trophy"></i><span class="user_navigation_link_text">'.get_string('leftmenu_gmleaderboard','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
//                 $block_content .= $gamification;
//                 $block_content .= html_writer::end_tag('li');
//             }

            $pluginnavs = array();
            foreach($local_pluginlist as $key => $local_pluginname){
                if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                    $functionname = 'local_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                       // $data = $functionname();
                        $data = (array)$functionname();
                         foreach($data as $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }
            // ksort($pluginnavs);
            // foreach($pluginnavs as $pluginnav){
            //     foreach($pluginnav  as $key => $value){
            //             $data = $value;
            //             $block_content .= $data;
            //     }
            // }

            foreach($block_pluginlist as $key => $local_pluginname){
                 if(file_exists($CFG->dirroot.'/blocks/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/blocks/'.$key.'/lib.php');
                    $functionname = 'block_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                    // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                        $data = $functionname();
                        foreach($data as $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    // $block_content .= html_writer::end_tag('li');
                    }
                }
            }

            $tool_certificate = $core_component::get_plugin_directory('tool', 'certificate');
            if($tool_certificate){
                if(file_exists($CFG->dirroot.'/admin/tool/certificate/lib.php')){
                    require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');
                    $functionname = 'tool_certificate_leftmenunode';
                    if(function_exists($functionname)){
                        $data = $functionname();
                        foreach($data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }

            ksort($pluginnavs);
            foreach($pluginnavs as $pluginnav){
                foreach($pluginnav  as $key => $value){
                        $data = $value;
                        $block_content .= $data;
                }
            }
            /*Site Administration Link*/
            if(is_siteadmin()){
                $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/admin/search.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-cogs"></i><span class="user_navigation_link_text">'.get_string('leftmenu_adminstration','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');
            }
        $block_content .= html_writer::end_tag('ul');

        return $block_content;
    }
    /**
     * returns the link of the costcenter scheme css file to load in header of every layout
     * MAY BE CHANGED IN THE COMING VERSIONS
     *
     * @return URL
     */
    function get_costcenter_scheme_css(){
        global $CFG;
        require_once($CFG->dirroot.'/theme/epsilon/lib.php');

        $return = false;
        if(file_exists($CFG->dirroot . '/local/costcenter/lib.php')){
            require_once($CFG->dirroot . '/local/costcenter/lib.php');
            $costcenter = new costcenter();
            $costcenter_scheme = $costcenter->get_costcenter_theme();
            return $costcenter_scheme;
            //$costcenter_scheme_url = get_css_for_costcenter_scss($costcenter_scheme);
            // if(!empty($costcenter_scheme_url)){
            //     $return = html_writer::empty_tag('link', array('href' => $costcenter_scheme_url, "rel"=> "stylesheet", "type" => "text/css"));
            // }
        }
        return $return;
    }
	 /**
	     * returns the scheme names for theme and costcenter
	     *
	     * @return string
	     */
	    function get_my_scheme(){
		global $PAGE, $CFG;

		$return = '';
		$theme_schemename = $PAGE->theme->settings->theme_scheme;
		if(!empty($theme_schemename)){
		    $return .= ' theme_'.$theme_schemename;
		}
		if(file_exists($CFG->dirroot . '/local/costcenter/lib.php')){
		    require_once($CFG->dirroot . '/local/costcenter/lib.php');
		    $costcenter = new costcenter();
		    $costcenter_schemename = $costcenter->get_costcenter_theme();
		    if(!empty($costcenter_schemename)){
		        $return .= ' organization_'.$costcenter_schemename;
		    }
		}

		return $return;
    }
    /**
     * Whether we should display the logo in the navbar.
     *
     * We will when there are no main logos, and we have compact logo.
     *
     * @return bool
     */
    public function should_display_navbar_logo() {
        global $USER, $DB;
        if(!empty($USER->open_costcenterid)){
            $costcenterid = $DB->get_field('local_costcenter', 'costcenter_logo', array('id'=>$USER->open_costcenterid));
        }
        if(!empty($costcenterid)){
            $logopath = costcenter_logo($costcenterid);
        }
        //print_r($logopath); exit;
        if(empty($logopath)) {
            $logopath = $this->get_compact_logo_url();
           //  if(empty($logopath)){
           //      $default_logo = $this->image_url('default_logo', 'theme_epsilon');
           //      $logopath = $default_logo;
           //  }
        }
        return !empty($logopath);
    }
    /*
     * Returns logo url to be displayed throughout the site
     * @author Rizwana
     *
     * @return logo url
    */
    public function get_custom_logo() {
        global $USER, $DB;
        if(!empty($USER->open_costcenterid)){
             $costcenterid = $DB->get_field('local_costcenter', 'costcenter_logo', array('id'=>$USER->open_costcenterid));
         }
         if(!empty($costcenterid)){
             $logopath = costcenter_logo($costcenterid);
         }

         if(empty($logopath)) {
             $logopath = $this->get_compact_logo_url();
            //  if(empty($logopath)){
            //      $default_logo = $this->image_url('default_logo', 'theme_epsilon');
            //      $logopath = $default_logo;
            //  }
         }
         return $logopath;
     }
    /**
     * returns the login logo url if uploaded in theme settings else returns false
     *
     * @return URL
     */
    function carousellogo(){
        $carousellogo = $this->page->theme->setting_file_url('carousellogo', 'carousellogo');
        if(empty($carousellogo)){
            $carousellogo = $this->image_url('carousel_logo', 'theme_epsilon');
        }
        return $carousellogo;

    }
    function loginlogo(){

        $loginlogo = $this->page->theme->setting_file_url('loginlogo', 'loginlogo');
        if(empty($loginlogo)){
            $loginlogo = $this->image_url('login_logo', 'theme_epsilon');
        }
        return $loginlogo;
    }
    function logintext(){

        $logintext = $this->page->theme->settings->logindesc;
        if(empty($logintext)){
            $logintext = '';
        }

        if (strlen($logintext) > 600) {
                //truncate string
                $stringCut = substr($logintext, 0, 600);
                $logintext = $stringCut.'...';
            }
        return $logintext;
    }
    function loginordering($value='') {
        $loginordering = $order = '';
        $order = get_config('theme_epsilon', 'loginorder');
        if($order == 0) {
            $loginordering = false;
        }else {
            $loginordering = true;
        }
        return $loginordering;
    }
    /*
     * returns the images slider for the login page.
     * @author Raghuvaran Komati.
     *
     * @return URL
    */
    public function loginslider(){
        global $CFG;
        if(isloggedin()){
            return false;
        }
        $loginslider = '';
        $loginslider .='<script> function loginpopup(test) {
                            $("#div_loginpopup_"+test).toggleClass("open");
                            }
                            function closeonclick(test){
                                $("#div_loginpopup_"+test).toggleClass("open");
                            }

                        </script>';

        $img1_url = $this->page->theme->setting_file_url('slider1', 'slider1');
        if(empty($img1_url)){
            $img1_url = $this->image_url('slides/slide1', 'theme_epsilon');
        }
        $img2_url = $this->page->theme->setting_file_url('slider2', 'slider2');
        if(empty($img2_url)){
            $img2_url = $this->image_url('slides/slide2', 'theme_epsilon');
        }
        $img3_url = $this->page->theme->setting_file_url('slider3', 'slider3');
        if(empty($img3_url)){
            $img3_url = $this->image_url('slides/slide3', 'theme_epsilon');
        }
        $img4_url = $this->page->theme->setting_file_url('slider4', 'slider4');
        if(empty($img4_url)){
            $img4_url = $this->image_url('slides/slide4', 'theme_epsilon');
        }
        $img5_url = $this->page->theme->setting_file_url('slider5', 'slider5');
        if(empty($img5_url)){
            $img5_url = $this->image_url('slides/slide5', 'theme_epsilon');
        }
        $slider_context = [
            "img1_url" => $img1_url,
            "img2_url" => $img2_url,
            "img3_url" => $img3_url,
            "img4_url" => $img4_url,
            "img5_url" => $img5_url,
        ];
        $loginslider .= $this->render_from_template('theme_epsilon/slider', $slider_context);
        return $loginslider;
    }

    /**
     * returns the login desc text given in theme settings
     *
     * @return HTML
     */
    function welcometext(){

        $welcometext = $this->page->theme->settings->welcometext;
        if(empty($welcometext)){
            $welcometext = ' ';
        }

        if (strlen($welcometext) > 15) {
                //truncate string
                $stringCut = substr($welcometext, 0, 15);
                $welcometext = $stringCut.' ';
            }

        return $welcometext;
    }


    function captiontext(){

        $captiontext = $this->page->theme->settings->logocaption;
        if(empty($captiontext)){
            $captiontext = '';
        }

        if (strlen($captiontext) > 80) {
                //truncate string
                $stringCut = substr($captiontext, 0, 80);
                $captiontext = $stringCut.'...';
            }
        return $captiontext;
    }
    /**
     * Returns the Help button text of the given helpdesc in theme settings.
     *
     * @return HTML
     */
    public function helpbtn() {
        $helptext = $this->page->theme->settings->helpdesc;
        if(!empty($helptext)){
            $helpbtn = $helptext;
        }else{
            $helpbtn = '';
        }
        return $helpbtn;
    }

    /**
     * Returns the About button text of the given aboutus in theme settings.
     *
     * @return HTML
     */
    public function aboutbtn() {
        $aboutustext = $this->page->theme->settings->aboutus;
        if(!empty($aboutustext)){
            $aboutusbtn = $aboutustext;
        }else{
            $aboutusbtn = '';
        }
        return $aboutusbtn;
    }

    /**
     * Returns the Contact button text of the given contact in theme settings.
     *
     * @return HTML
     */
    public function contactbtn() {
        $contactustext = $this->page->theme->settings->contact;
        if(!empty($contactustext)){
            $contactusbtn = $contactustext;
        }else{
            $contactusbtn = '';
        }
        return $contactusbtn;
    }

    /**
     *Function for copyright text
     *
     * @return string.
     */
    public function get_copyright_text() {
         return format_text($this->page->theme->settings->copyright, FORMAT_HTML);
    }
    /**
     * Secure login info.
     *
     * @return string
     */
    public function secure_login_info() {
        return $this->login_info(false);
    }

    /**
     *Function for footer social links
     * Returns a social links.
     *
     * @return social links.
     */
    public function footer_social_icons() {
        $hasfacebook    = (empty($this->page->theme->settings->facebook)) ? false : $this->page->theme->settings->facebook;
        $hastwitter     = (empty($this->page->theme->settings->twitter)) ? false : $this->page->theme->settings->twitter;
        $haslinkedin    = (empty($this->page->theme->settings->linkedin)) ? false : $this->page->theme->settings->linkedin;
        $hasyoutube     = (empty($this->page->theme->settings->youtube)) ? false : $this->page->theme->settings->youtube;
        $hasinstagram   = (empty($this->page->theme->settings->instagram)) ? false : $this->page->theme->settings->instagram;

        $socialcontext = [

            // If any of the above social networks are true, sets this to true.
            'hassocialnetworks' => ($hasfacebook || $hastwitter
                 || $haslinkedin  || $hasyoutube ||  $hasinstagram
                 ) ? true : false,

            'socialicons' => array(
                    'facebook' => $hasfacebook,
                    'twitter'  => $hastwitter,
                    'linkedin' => $haslinkedin,
                    'youtube'    => $hasyoutube,
                    'instagram'  => $hasinstagram,
            )
        ];
        return $this->render_from_template('theme_epsilon/socialicons', $socialcontext);
    }

    /*
     * returns the Navigtion links for the quick information.
     * @author Raghuvaran Komati
     *
     * @return URL
    */
    public function get_quickLinks() {
        $quickinfo1 = $quickinfo2 = $quickinfo3 = $quickinfo4 = $quickinfo5 = '';
        $quickinfo1 = (empty($this->page->theme->settings->quickinfo1)) ? false : $this->page->theme->settings->quickinfo1;
        $quickinfo2 = (empty($this->page->theme->settings->quickinfo2)) ? false : $this->page->theme->settings->quickinfo2;
        $quickinfo3 = (empty($this->page->theme->settings->quickinfo3)) ? false : $this->page->theme->settings->quickinfo3;
        $quickinfo4 = (empty($this->page->theme->settings->quickinfo4)) ? false : $this->page->theme->settings->quickinfo4;
        $quickinfo5 = (empty($this->page->theme->settings->quickinfo5)) ? false : $this->page->theme->settings->quickinfo5;
        $quickInfo = [
            'quicklinksEnable' => ($this->page->theme->settings->quickinfo == 'no') ? false : true,
            'hasquicklinks' => ($quickinfo1 || $quickinfo2
                 || $quickinfo3  || $quickinfo4 ||  $quickinfo4
                 ) ? true : false,
            'quicklinks' => array(
                'quickinfo1' => $quickinfo1,
                'quickinfo2' => $quickinfo2,
                'quickinfo3' => $quickinfo3,
                'quickinfo4' => $quickinfo4,
                'quickinfo5' => $quickinfo5,
            )
        ];
        // print_object($this->page->theme->settings->quickinfo);
        return $this->render_from_template('theme_epsilon/quickinfo', $quickInfo);
    }
    /**
     * Renders the login form.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $CFG, $SITE, $OUTPUT;

        $context = $form->export_for_template($this);

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->sitename = format_string($SITE->fullname, true,
            ['context' => context_course::instance(SITEID), "escape" => false]);
        $context->output = $OUTPUT;
        $helptext = $this->page->theme->settings->helpdesc;
        $contactustext = $this->page->theme->settings->contact;
        $aboutustext = $this->page->theme->settings->aboutus;
        if(!empty($helptext)||(!empty($contactustext))||(!empty($aboutustext))){
            $context->helptext = $helptext;
            $context->contactustext = $contactustext;
            $context->aboutustext = $aboutustext;
        }else{
            $context->helptext = '';
            $context->contactustext = '';
            $context->aboutustext = '';
        }
        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Renders the otplogin form.
     *
     * @param \core_auth\output\otplogin $form The renderable.
     * @return string
     */
    public function render_otplogin(\core_auth\output\otplogin $form) {
        global $CFG, $SITE, $OUTPUT;

        $context = $form->export_for_template($this);

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);

        $context->sitename = format_string($SITE->fullname, true,
            ['context' => \context_course::instance(SITEID), "escape" => false]);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->output = $OUTPUT;
        $context->loginlogo = $this->loginlogo();
        $context->loginslider = $this->loginslider();

        return $this->render_from_template('core/otploginform', $context);
    }
    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        $data = $this->custom_secured_redirection();
        $pagetype = $this->page->pagetype;
        $homepage = get_home_page();
        $homepagetype = null;
        $context = $this->page->context;
        $courseid = $this->page->course->id;
        // Add a special case since /my/courses is a part of the /my subsystem.
        if ($homepage == HOMEPAGE_MY || $homepage == HOMEPAGE_MYCOURSES) {
            $homepagetype = 'my-index';
        } else if ($homepage == HOMEPAGE_SITE) {
            $homepagetype = 'site-index';
        }
        if ($this->page->include_region_main_settings_in_header_actions() &&
                !$this->page->blocks->is_block_present('settings')) {
            // Only include the region main settings if the page has requested it and it doesn't already have
            // the settings block on it. The region main settings are included in the settings block and
            // duplicating the content causes behat failures.
            $this->page->add_header_action(html_writer::div(
                $this->region_main_settings_menu(),
                'd-print-none',
                ['id' => 'region-main-settings-menu']
            ));
        }
        $show_course_header = false;
        if (($context->contextlevel == CONTEXT_COURSE) && $courseid > 1) {
            $course_extended_menu = $this->course_context_header_settings_menu();
            $show_course_header = true;
            
        }else{
            $course_extended_menu = $this->context_header_settings_menu();
        }
        $header = new stdClass();
        $header->settingsmenu = $course_extended_menu;

        // if(!$data->hideheader)
        $header->contextheader = $this->context_header();
        $header->hasnavbar = empty($this->page->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();
        $header->headeractions = $this->page->get_header_actions();
        if (!empty($pagetype) && !empty($homepagetype) && $pagetype == $homepagetype) {
            $header->welcomemessage = \core_user::welcome_message();
        }
        return $this->render_from_template($show_course_header? 'theme_epsilon/course_full_header' : 'theme_epsilon/full_header', $header);
    }
        /**
     * return custom course page header buttons to show only on course pages
     *
     * @return HTML
     */
    public function course_context_header_settings_menu(){
        global $PAGE, $COURSE, $DB, $USER;

        $courseid = $COURSE->id;
        $sesskey = sesskey();
        if($courseid < 2){
            return '';
        }
        $PAGE->requires->js_call_amd('local_courses/courseAjaxform', 'init');
        $return = '';

        // $systemcontext = context_system::instance();
        $systemcontext = \context_course::instance($courseid);
         if(has_capability('local/courses:view', $systemcontext) || has_capability('local/courses:manage', $systemcontext) || is_siteadmin()) {
            $admin_default_menu = true;
        }
        $useredit = '';
        if ($PAGE->user_is_editing() && $PAGE->user_allowed_editing()) {
            $useredit = 'off';
        }else{
            $useredit = 'on';
        }
        if($this->page->pagetype!='local-catalog-courseinfo') {
            $departments = explode(',', $COURSE->open_departmentid);
            $manage = true;
            if(!(is_siteadmin() || has_any_capability(['moodle/course:view'], $systemcontext)) && count($departments) > 1){
                $manage = false;
                $USER->editing = 0;
            }
            if ($PAGE->user_allowed_editing() && $manage){
                    $categorycontext = context_coursecat::instance($COURSE->category);
                    $allow_editing = true;
                $editing_url = new moodle_url('/course/view.php', array('id' => $courseid, 'sesskey'=> $sesskey, 'edit'=>$useredit));
            }
            if((has_capability('moodle/course:create',$systemcontext) || is_siteadmin() ||
                                            has_capability('local/courses:enrol', $systemcontext)) && $manage) {
                $is_courseedit_icon = true;
                $course_reports =  true;
                $course_complition = true;
            }

            if((has_capability('moodle/backup:backupcourse',$systemcontext) || is_siteadmin()) && $manage) {
                $coursebackup = true;
            }
            if(is_siteadmin() || has_capability('enrol/manual:manage', $systemcontext)) {
                $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $courseid ,'enrol' => 'manual'));
                $userenrollment = true;
            }
        }
        // if($this->page->pagetype === 'blocks-gamification-index'){
        //     $gamificationpage = true;
        // }else{
        //     $gamificationpage = false;
        // }
        $challenge_plugin_exist = \core_component::get_plugin_directory('local', 'challenge');
        $challenge_element = false;
        $enabled =  (int)get_config('', 'local_challenge_enable_challenge');
        if($enabled){
            if(!empty($challenge_plugin_exist)){
                $render_class = $PAGE->get_renderer('local_challenge');
                if(method_exists($render_class, 'render_challenge_object')){
                    $element = $render_class->render_challenge_object('local_courses', $courseid);
                    $challenge_element = $element;
                }
            }
        }
        $gamification_plugin_exist = \core_component::get_plugin_directory('block', 'gamification');
        $gamification_element = false;
        if(!empty($gamification_plugin_exist)){
            $gamification_element = true;
        }


        $course_context = [
            "courseid" => $courseid,
            "admin_default_menu" => $admin_default_menu,
            "default_menu" => $this->context_header_settings_menu(),
            "allow_editing" => $allow_editing,
            "editing_url" => $editing_url,
            "useredit" => $useredit,
            "is_courseedit_icon" => $is_courseedit_icon,
            "course_reports" => $course_reports,
            "course_complition" => $course_complition,
            "coursebackup" => $coursebackup,
            "enrolid" => $enrolid,
            "userenrollment" => $userenrollment,
            "categorycontextid" =>$categorycontext->id,
            // "gamificationpage" => $gamificationpage,
            "challenge_element" => $challenge_element,
            "gamification_element" => $gamification_element,
            "manage" => $manage,
            'isenrolled' => is_enrolled(context_course::instance($COURSE->id)),
        ];

        if(!is_siteadmin()){
            $switchedrole = $USER->access['rsw']['/1'];
            if($switchedrole){
                $userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
            }else{
                $userrole = null;
            }

//            if(is_null($userrole) || $userrole == 'user'){
             if(is_null($userrole) || $userrole == 'employee'){
                $core_component = new core_component();
                $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
                if($certificate_plugin_exist){
                    if(!empty($COURSE->open_certificateid)){
                        $course_context['certificate_exists'] = true;
                        $sql = "SELECT id
                                FROM {course_completions}
                                WHERE course = :courseid AND userid = :userid
                                AND timecompleted IS NOT NULL ";

                        $completed = $DB->record_exists_sql($sql, array('courseid'=>$COURSE->id, 'userid'=>$USER->id));
                        if($completed){

            $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$COURSE->id,'userid'=>$USER->id,'templateid'=>$COURSE->open_certificateid,'moduletype'=>'course'));
                            $course_context['certificate_download'] = true;
                            $course_context['certificateid'] = $certcode; //$COURSE->open_certificateid;
                            $course_context['moduletype'] = 'course';
                            $course_context['moduleid'] = $COURSE->id;
                        }else{
                            $course_context['certificate_download'] = false;
                        }
                    }
                }
            }
        }

        return $this->render_from_template('theme_epsilon/course_context_header', $course_context);
    }
    function theme_epsilon_user_get_user_navigation_info($user, $page, $options = array()) {
        global $OUTPUT, $DB, $SESSION, $CFG;

        $returnobject = new stdClass();
        $returnobject->navitems = array();
        $returnobject->metadata = array();

        $course = $page->course;

        // Query the environment.
        $context = context_course::instance($course->id);

        // Get basic user metadata.
        // $returnobject->metadata['userid'] = $user->id;
        // $returnobject->metadata['userfullname'] = fullname($user, true);
        // $returnobject->metadata['userprofileurl'] = new moodle_url('/local/user/profile.php', array(
        //     'id' => $user->id
        // ));

        $avataroptions = array('link' => false, 'visibletoscreenreaders' => false);
        if (!empty($options['avatarsize'])) {
            $avataroptions['size'] = $options['avatarsize'];
        }
        $returnobject->metadata['useravatar'] = $OUTPUT->user_picture (
            $user, $avataroptions
        );
        // Build a list of items for a regular user.

        // Query MNet status.
        if ($returnobject->metadata['asmnetuser'] = is_mnet_remote_user($user)) {
            $mnetidprovider = $DB->get_record('mnet_host', array('id' => $user->mnethostid));
            $returnobject->metadata['mnetidprovidername'] = $mnetidprovider->name;
            $returnobject->metadata['mnetidproviderwwwroot'] = $mnetidprovider->wwwroot;
        }

        // Did the user just log in?
        if (isset($SESSION->justloggedin)) {
            // Don't unset this flag as login_info still needs it.
            if (!empty($CFG->displayloginfailures)) {
                // Don't reset the count either, as login_info() still needs it too.
                if ($count = user_count_login_failures($user, false)) {

                    // Get login failures string.
                    $a = new stdClass();
                    $a->attempts = html_writer::tag('span', $count, array('class' => 'value'));
                    $returnobject->metadata['userloginfail'] =
                        get_string('failedloginattempts', '', $a);

                }
            }
        }

        // Links: Dashboard.
        $myhome = new stdClass();
        $myhome->itemtype = 'link';
        $myhome->url = new moodle_url('/my/dashboard.php');
        $myhome->title = get_string('mymoodle', 'admin');
        $myhome->titleidentifier = 'mymoodle,admin';
        // $myhome->pix = "i/dashboard";
        $returnobject->navitems[] = $myhome;

        // Links: My Profile.
        $myprofile = new stdClass();
        $myprofile->itemtype = 'link';
        $myprofile->url = new moodle_url('/local/users/profile.php', array('id' => $user->id));
        $myprofile->title = get_string('profile');
        $myprofile->titleidentifier = 'profile,moodle';
        // $myprofile->pix = "i/user";
        $returnobject->navitems[] = $myprofile;

        $returnobject->metadata['asotherrole'] = false;

        // Before we add the last items (usually a logout + switch role link), add any
        // custom-defined items.
        $customitems = user_convert_text_to_menu_items($CFG->customusermenuitems, $page);
        foreach ($customitems as $item) {
            $returnobject->navitems[] = $item;
        }


        if ($returnobject->metadata['asotheruser'] = \core\session\manager::is_loggedinas()) {
            $realuser = \core\session\manager::get_realuser();

            // Save values for the real user, as $user will be full of data for the
            // user the user is disguised as.
            $returnobject->metadata['realuserid'] = $realuser->id;
            $returnobject->metadata['realuserfullname'] = fullname($realuser, true);
            $returnobject->metadata['realuserprofileurl'] = new moodle_url('/user/profile.php', array(
                'id' => $realuser->id
            ));
            $returnobject->metadata['realuseravatar'] = $OUTPUT->user_picture($realuser, $avataroptions);

            // Build a user-revert link.
            $userrevert = new stdClass();
            $userrevert->itemtype = 'link';
            $userrevert->url = new moodle_url('/course/loginas.php', array(
                'id' => $course->id,
                'sesskey' => sesskey()
            ));
            // $userrevert->pix = "a/logout";
            $userrevert->title = get_string('logout');
            $userrevert->titleidentifier = 'logout,moodle';
            $returnobject->navitems[] = $userrevert;

        } else {

            // Build a logout link.
            $logout = new stdClass();
            $logout->itemtype = 'link';
            $logout->url = new moodle_url('/login/logout.php', array('sesskey' => sesskey()));
            // $logout->pix = "a/logout";
            $logout->title = get_string('logout');
            $logout->titleidentifier = 'logout,moodle';
            $returnobject->navitems[] = $logout;
        }

        // Removed the default switch back to default role as we have custom level role switch.

        return $returnobject;
    }
    /**
     * Construct a user menu, returning HTML that can be echoed out by a
     * layout file.
     *
     * @param stdClass $user A user object, usually $USER.
     * @param bool $withlinks true if a dropdown should be built.
     * @return string HTML fragment.
     */
    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        if (is_null($user)) {
            $user = $USER;
        }
        // Note: this behaviour is intended to match that of core_renderer::login_info,
        // but should not be considered to be good practice; layout options are
        // intended to be theme-specific. Please don't copy this snippet anywhere else.
        if (is_null($withlinks)) {
            $withlinks = empty($this->page->layout_options['nologinlinks']);
        }

        // Add a class for when $withlinks is false.
        $usermenuclasses = 'usermenu';
        if (!$withlinks) {
            $usermenuclasses .= ' withoutlinks';
        }

        $returnstr = "";

        // If during initial install, return the empty return string.
        if (during_initial_install()) {
            return $returnstr;
        }

        $loginpage = $this->is_login_page();
        $loginurl = get_login_url();
        // If not logged in, show the typical not-logged-in string.
        if (!isloggedin()) {
            $returnstr = get_string('loggedinnot', 'moodle');
            if (!$loginpage) {
                $returnstr .= " (<a href=\"$loginurl\">" . get_string('login') . '</a>)';
            }
            return html_writer::div(
                html_writer::span(
                    $returnstr,
                    'login'
                ),
                $usermenuclasses
            );

        }

        // If logged in as a guest user, show a string to that effect.
        if (isguestuser()) {
            $returnstr = get_string('loggedinasguest');
            if (!$loginpage && $withlinks) {
                $returnstr .= " (<a href=\"$loginurl\">".get_string('login').'</a>)';
            }

            return html_writer::div(
                html_writer::span(
                    $returnstr,
                    'login'
                ),
                $usermenuclasses
            );
        }

        // Get some navigation opts.
        $opts = $this->theme_epsilon_user_get_user_navigation_info($user, $this->page, array('avatarsize' => 35));

        /*Start of the role Switch */
        $systemcontext = context_system::instance();
        $roles = \local_costcenter\lib\accesslib::get_user_roles_in_catgeorycontexts($USER->id);
        if (is_array($roles) && (count($roles) > 0)) {

            $switchrole = new stdClass(); /*Role for the Learner i.e user role */
            $switchrole->itemtype = 'link';
            $learner_record_sql = "SELECT id, name, shortname
                                    FROM {role}
                                    WHERE shortname = 'user' AND archetype = 'user' ";
            $learnerroleid = $DB->get_record_sql($learner_record_sql);
            $USER->access['rsw']['/1'] = $learnerroleid->id;
            $rolename = get_string('employee','theme_epsilon');


            $depths = [];
            // var_dump($roles);exit;
            array_values(array_filter(array_walk($roles, function(&$role, $rolekey)use(&$depths, &$roles){
                $categoryids = array_values(array_filter((explode('/', $role->path))));
                $category = \local_costcenter\lib\accesslib::get_category_info(end($categoryids), 'name');

                if(!in_array($role->depth.'_'.$categoryids[0].'_'.$role->roleid, $depths['depth'])){
                    $depths['depth'][$rolekey] = $role->depth.'_'.$categoryids[0].'_'.$role->roleid;
                    $role->categoryname = $category;
                    $role->highest_catid = $categoryids[0];
                    $roles[$rolekey] = $role;
                }else{
                    if($prevkey = array_search($role->depth.'_'.$categoryids[0].'_'.$role->roleid, $depths['depth'])){
                        $prevrole = $roles[$prevkey];
                        $prevrole->categoryname .= ", ".$category;
                        $roles[$prevkey] = $prevrole;
                        unset($roles[$rolekey]);
                    }
                }
            })));
            $user_ra_array = $roles;

            if(is_array($user_ra_array)){
                $highest_roleinfo = max($user_ra_array);
            }else{
                $highest_roleinfo = (object)['roleid' => 0, 'contextid' => SYSCONTEXTID];
            }

            $current_roleid = isset($USER->useraccess['currentroleinfo']['roleid']) ? $USER->useraccess['currentroleinfo']['roleid'] : $highest_roleinfo->roleid;

            $current_orgcatid = isset($USER->useraccess['currentroleinfo']['orgcatid']) ? $USER->useraccess['currentroleinfo']['orgcatid'] : $highest_roleinfo->highest_catid;

            $current_depth = isset($USER->useraccess['currentroleinfo']['depth']) ? $USER->useraccess['currentroleinfo']['depth'] : $highest_roleinfo->depth;

            if(!empty($learnerroleid)){
                if($learnerroleid->id == $current_roleid){
                    $disabled_role = 'user_role active_role';
                 }else{
                    $disabled_role = 'user_role';
                 }
                 $switchrole->url = new moodle_url('/my/switchrole.php', array('sesskey' => sesskey(),'confirm' => 1,'switchrole' => $learnerroleid->id));
                 $switchrole->pix = "i/user";
                 $switchrole->title = get_string('switchroleas','theme_epsilon').$rolename;
                 $switchrole->titleidentifier = 'switchrole_'.$rolename.',moodle';
                 $switchrole->class = $disabled_role;
                 $opts->navitems[] = $switchrole;
             }

            foreach($user_ra_array as $role){   /*Get all the roles assigned to the user for display */
                if(empty($role->rolename)){
                    $rolename =  $role->categoryname.' - '.$role->rolecode;
                }else{
                    $rolename =  $role->categoryname.' - '.$role->rolename;
                }

                $switchrole = new stdClass();
                $switchrole->itemtype = 'link';
                if($role->roleid == $current_roleid && $current_depth == $role->depth && $current_orgcatid == $role->highest_catid ){
                    $switchrole->url = new moodle_url('javascript:void(0)');
                    $disabled_role = 'user_role active_role';
                }else{
                    $switchrole->url = new moodle_url('/my/switchrole.php', array('sesskey' => sesskey(),'confirm' => 1,'switchrole' => $role->roleid, 'contextid' => $role->contextid));
                    $disabled_role = 'user_role';
                }
                $switchrole->pix = "i/switchrole";
                $switchrole->title = get_string('switchroleas','theme_epsilon').$rolename;
                $switchrole->titleidentifier = 'switchrole_'.$rolename.',moodle';
                $switchrole->class = $disabled_role;
                $opts->navitems[] = $switchrole;
            }
        }
        $highest_roleid = '';
        if((count($roles) > 0) && (!isset($USER->useraccess['currentroleinfo']) || empty($USER->useraccess['currentroleinfo'])) ){
            if($highest_roleinfo->roleid){
                $highest_roleid = $highest_roleinfo->roleid;
                $contextid = $highest_roleinfo->contextid;
                $this->role_switch_basedon_userroles($highest_roleid, false, $contextid);

            }
        // }elseif((isset($USER->access['rsw']) && $USER->access['rsw']) ){
        //     $highest_roleid = current($USER->access['rsw']);
        }

        // Build a logout link.
        $logout = new stdClass();
        $logout->itemtype = 'link';
        $logout->url = new moodle_url('/login/logout.php', array('sesskey' => sesskey()));
        $logout->pix = "a/logout";
        $logout->title = get_string('logout');
        $logout->titleidentifier = 'customlogout,moodle';
        $opts->navitems[] = $logout;


        $avatarclasses = "avatars";
        $avatarcontents = html_writer::span($opts->metadata['useravatar'], 'avatar current');
        $usertextcontents = isset($opts->metadata['userfullname']);

        // Other user.
        if (!empty($opts->metadata['asotheruser'])) {
            $avatarcontents .= html_writer::span(
                $opts->metadata['realuseravatar'],
                'avatar realuser'
            );
            $usertextcontents = $opts->metadata['realuserfullname'];
            $usertextcontents .= html_writer::tag(
                'span',
                get_string(
                    'loggedinas',
                    'moodle',
                    html_writer::span(
                        isset($opts->metadata['userfullname']),
                        'value'
                    )
                ),
                array('class' => 'meta viewingas')
            );
        }

        // Role.
        if (!empty($opts->metadata['asotherrole'])) {
            $role = core_text::strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['rolename'])));
            $usertextcontents .= html_writer::span(
                $opts->metadata['rolename'],
                'meta role role-' . $role
            );
        }

        // User login failures.
        if (!empty($opts->metadata['userloginfail'])) {
            $usertextcontents .= html_writer::span(
                $opts->metadata['userloginfail'],
                'meta loginfailures'
            );
        }

        // MNet.
        if (!empty($opts->metadata['asmnetuser'])) {
            $mnet = strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['mnetidprovidername'])));
            $usertextcontents .= html_writer::span(
                $opts->metadata['mnetidprovidername'],
                'meta mnet mnet-' . $mnet
            );
        }

        $returnstr .= html_writer::span(
            // html_writer::span($usertextcontents, 'usertext') .
            html_writer::span($avatarcontents, $avatarclasses),
            'userbutton'
        );

        // Create a divider (well, a filler).
        $divider = new action_menu_filler();
        $divider->primary = false;

        $am = new action_menu();
        $am->set_menu_trigger(
            $returnstr
        );
        $am->set_menu_left(action_menu::TR, action_menu::BR);
        $am->set_nowrap_on_items();
        if ($withlinks) {
            $navitemcount = count($opts->navitems);
            $idx = 0;
            foreach ($opts->navitems as $key => $value) {

                switch ($value->itemtype) {
                    case 'divider':
                        // If the nav item is a divider, add one and skip link processing.
                        $am->add($divider);
                        break;

                    case 'invalid':
                        // Silently skip invalid entries (should we post a notification?).
                        break;

                    case 'link':
                        // Process this as a link item.

                        $pix = null;
                        if (isset($value->pix) && !empty($value->pix)) {
                            $pix = new pix_icon($value->pix, $value->title, null, array('class' => 'iconsmall'));
                        } else if (isset($value->imgsrc) && !empty($value->imgsrc)) {
                                $value->title = html_writer::img(
                                $value->imgsrc,
                                $value->title,
                                array('class' => 'iconsmall')
                            ) . $value->title;
                        }
                        $stringtitleidentifier = $value->titleidentifier;
                        $component = explode(',', $stringtitleidentifier);
                        $component = $component[0];
                        if(($component == 'switchroleto') || ($component == 'logout')){
                            //do nothing
                        }elseif((strpos('switchrole_', $component) !== false)){
                            $al = new action_menu_link_secondary(
                                $value->url,
                                $pix,
                                $value->title,
                                array('class' => 'icon')
                            );
                            if (!empty($value->titleidentifier)) {
                                $al->attributes['data-title'] = $value->titleidentifier;
                            }
                            $al->attributes['class'] = $disabled_role;
                            $am->add($al);
                        }elseif((strpos('customlogout', $component) !== false)){
                            $al = new action_menu_link_secondary(
                                $value->url,
                                $pix,
                                $value->title,
                                array('class' => 'icon')
                            );
                            if (!empty($value->titleidentifier)) {
                                $al->attributes['data-title'] = $value->titleidentifier;
                            }
                            $am->add($al);
                        }else{
                            if(isset($value->class)){
                                $valueclass = $value->class;
                            }else{
                                $valueclass = '';
                            }
                            $al = new action_menu_link_secondary(
                                $value->url,
                                $pix,
                                $value->title,
                                //$value->class,
                                array('class' => 'icon '.$valueclass.'')
                            );
                            if (!empty($value->titleidentifier)) {
                                $al->attributes['data-title'] = $value->titleidentifier;
                            }
                            $am->add($al);
                        }

                        break;
                }

                $idx++;

                // Add dividers after the first item and before the last item.
                if ($idx == 1 || $idx == $navitemcount - 1) {
                    $am->add($divider);
                }
            }
        }

        return html_writer::div(
            parent::render($am),
            $usermenuclasses
        );
    }
    public function custom_secured_redirection(){
        global $USER, $CFG, $DB, $COURSE;
        $return = new stdClass();
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $pageurl = "https";
        else
            $pageurl = "http";
        $pageurl .= "://";
        $pageurl .= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $string = strpos($pageurl, '?');
        if($string)
            $newpageurl = substr($pageurl,0 , $string);
        else
            $newpageurl = $pageurl;

        if($newpageurl == $CFG->wwwroot.'/enrol/index.php' || $newpageurl == $CFG->wwwroot.'/enrol/'){
            redirect($CFG->wwwroot.'/my');
        }
        if($newpageurl == $CFG->wwwroot.'/course/management.php'){
            redirect($CFG->wwwroot.'/local/custom_category/index.php');//Category page redirection
        }
        if($newpageurl == $CFG->wwwroot.'/user/view.php' || $newpageurl == $CFG->wwwroot.'/user/profile.php'){
            if($_GET['id']){
                $id = $_GET['id'];
            }else{
                $id = $USER->id;
            }
            redirect($CFG->wwwroot."/local/users/profile.php?id=$id");
        }
        if($newpageurl == $CFG->wwwroot.'/course/index.php' || $newpageurl == $CFG->wwwroot.'/course'){
            redirect($CFG->wwwroot."/local/courses/courses.php");
        }
        $systemcontext = \context_system::instance();
        if(!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext))){
            $is_oh = has_capability('local/costcenter:manage_ownorganization', $systemcontext);
            $is_dh = has_capability('local/costcenter:manage_owndepartments', $systemcontext);
            if($newpageurl == $CFG->wwwroot.'/course/completion.php' || $newpageurl == $CFG->wwwroot.'/backup/backup.php'){/*for course completion settings and backup page*/
                $courseid = required_param('id',  PARAM_INT);
                $course = get_course($courseid);
                if($is_oh && $USER->open_costcenterid != $course->open_costcenterid){
                    redirect($CFG->wwwroot.'/local/courses/courses.php');
                }else if($is_dh && $USER->open_departmentid != $course->open_departmentid){
                    redirect($CFG->wwwroot.'/local/courses/courses.php');
                }
            }else if($newpageurl == $CFG->wwwroot.'/mod/quiz/edit.php' || $newpageurl == $CFG->wwwroot.'/mod/quiz/report.php'){/*for edit quiz page and quiz default report page*/
                if($COURSE->id == 1){
                    if($newpageurl == $CFG->wwwroot.'/mod/quiz/edit.php')
                        $cmid = $_GET['cmid'];
                    else
                        $cmid = $_GET['id'];

                    $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));
                    $onlinetest_sql = "SELECT lo.* FROM {local_onlinetests} AS lo
                        JOIN {course_modules} AS cm ON cm.instance=lo.quizid AND cm.module = {$quizmoduleid}
                        WHERE cm.id = :cmid";
                        // JOIN {quiz} AS q ON q.id=lo.quizid
                    $onlinetest = $DB->get_record_sql($onlinetest_sql, array('cmid' => $cmid));
                    if($onlinetest){
                        $return->hideheader = TRUE;
                        if($is_oh && $USER->open_costcenterid != $onlinetest->costcenterid){
                            redirect($CFG->wwwroot.'/local/onlinetests/index.php');
                        }else if($is_dh && $USER->open_departmentid != $onlinetest->departmentid){
                            redirect($CFG->wwwroot.'/local/onlinetests/index.php');
                        }
                    }else{
                        $return->hideheader = FALSE;
                    }
                }
            }else if($newpageurl == $CFG->wwwroot.'/mod/quiz/review.php' /*|| $newpageurl == $CFG->wwwroot.'/mod/quiz/attempt.php'*/){/*for quiz reviewpage and quiz attempt page*/
                if($COURSE->id == 1){
                    $attempt = $_GET['attempt'];
                    $onlinetest_sql = "SELECT lo.id, lo.costcenterid, lo.departmentid FROM {local_onlinetests} AS lo
                        JOIN {quiz_attempts} AS qa ON qa.quiz = lo.quizid
                        WHERE qa.id=:attemptid ";
                    $onlinetest = $DB->get_record_sql($onlinetest_sql, array('attemptid' => $attempt));
                    if($onlinetest){
                        $return->hideheader = TRUE;
                        if($is_oh && $USER->open_costcenterid != $onlinetest->costcenterid){
                            redirect($CFG->wwwroot.'/local/onlinetests/index.php');
                        }else if($is_dh && $USER->open_departmentid != $onlinetest->departmentid){
                            redirect($CFG->wwwroot.'/local/onlinetests/index.php');
                        }
                    }else{
                        $return->hideheader = FALSE;
                    }
                }
            }
        }
        return $return;
    }
    /**
     * Number of role switch based on user roles
     *
     * @param int $courseid A course object.
     * @param stdClass $context usually site context.
     * @return string HTML.
     */
    function role_switch_basedon_userroles($roleid, $purge, $contextid = 1){
        global $DB, $CFG, $USER;

        if(is_siteadmin($USER->id) || ($roleid <= 0) || $purge){
            return false;
        }

        $role = $DB->get_record('role', array('id' => $roleid));
        if(!$role){
            print_error('nopermission');
        }
        $context = \context::instance_by_id($contextid);
        $roles = get_user_roles($context, $USER->id);
        // $userroles = array();

        // foreach($roles as $r){
        //     $userroles[$r->roleid] = $r->shortname;
        // }

        $accessdata = get_empty_accessdata();
        if($this->roleswitch($roleid, $context, $accessdata)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * sitelevel roleswitch as buttons.
     *
     * @param int $courseid A course object.
     * @param stdClass $context usually site context.
     * @return string HTML.
     */
    function roleswitch($roleid, $context, &$accessdata){

        global $DB, $ACCESSLIB_PRIVATE, $USER;
        $USER->access['rsw'][$context->path] = $roleid;

        $costcenterpath = \local_costcenter\lib\accesslib::get_costcenterpath_context($context);

        $USER->useraccess['currentroleinfo']['roleid'] = $roleid;
        $categorypath = \local_costcenter\lib\accesslib::get_category_info($context->instanceid, 'path');
        $categoryids = array_values(array_filter((explode('/', $categorypath))));
        $USER->useraccess['currentroleinfo']['orgcatid'] = $categoryids[0];
        $USER->useraccess['currentroleinfo']['depth'] = $context->depth;
        $USER->useraccess['currentroleinfo']['contextinfo'] = [];
        $USER->useraccess['currentroleinfo']['contextinfo'][] = ['context' => $context,'costcenterpath' => $costcenterpath];
       /* Get the relevant rolecaps into rdef
        * - relevant role caps
        *   - at ctx and above
        *   - below this ctx
        */
        if (empty($context->path)) {
            // weird, this should not happen
            return;
        }
        //Fetching the category contexts where the role is assigned ans switching as user to those for achieving system level role switch starts.
        if($context->id == SYSCONTEXTID){
            $userroleid = $DB->get_field('role', 'id', array('archetype' => 'student'));
        }else{
            $userroleid = $DB->get_field('role', 'id', array('archetype' => 'user'));
        }
        // $assignedcontexts = array_map(function($cxtpath){
        //     return end(explode('/', $cxtpath));
        // }, array_unique(array_keys($USER->access['ra'])));
        $assignedroles = \local_costcenter\lib\accesslib::get_user_roles_in_catgeorycontexts($USER->id);
        $contextdepth = $context->__get('depth');
        foreach($assignedroles AS $assignedrole){
            if($assignedrole->contextid != $context->id && $assignedrole->contextid != 1){
                $othercontext = \context::instance_by_id($assignedrole->contextid);
                // considering only category level role switches.
                if($othercontext->__get('contextlevel') == CONTEXT_COURSECAT){
                    $othercategorypath = \local_costcenter\lib\accesslib::get_category_info($othercontext->instanceid, 'path');
                    $othercategoryids = array_values(array_filter((explode('/', $othercategorypath))));
                    if($contextdepth == $othercontext->__get('depth') && $othercategoryids[0] == $USER->useraccess['currentroleinfo']['orgcatid'] && $roleid == $assignedrole->roleid){//in_array($roleid, $USER->access['ra'][$othercontext->path])
                        if($this->role_capability_assignments($roleid, $othercontext, $accessdata)){
                            $USER->access['rsw'][$othercontext->path] = $roleid;
                            $othercostcenterpath = \local_costcenter\lib\accesslib::get_costcenterpath_context($othercontext);
                            $USER->useraccess['currentroleinfo']['contextinfo'][] = ['context' => $othercontext,'costcenterpath' => $othercostcenterpath];
                        }
                    }else{
                        if($this->role_capability_assignments($userroleid, $othercontext, $accessdata))
                            $USER->access['rsw'][$othercontext->path] = $userroleid;
                    }
                }
            }
        }
        //Fetching the category contexts where the role is assigned ans switching as user to those for achieving system level role switch ends.
        $this->role_capability_assignments($roleid, $context, $accessdata);
        return true;
    }
    private function role_capability_assignments($roleid, $context, &$accessdata){
        global $DB;
        list($parentsaself, $params) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'pc_');
        $params['roleid'] = $roleid;
        $params['childpath'] = $context->path.'/%';

        $sql = "SELECT ctx.path, rc.capability, rc.permission
                  FROM {role_capabilities} rc
                  JOIN {context} ctx ON (rc.contextid = ctx.id)
                 WHERE rc.roleid = :roleid AND (ctx.id $parentsaself OR ctx.path LIKE :childpath)
              ORDER BY rc.capability"; // fixed capability order is necessary for rdef dedupe
        $rs = $DB->get_recordset_sql($sql, $params);

        $newrdefs = array();
        foreach ($rs as $rd) {
            $k = $rd->path.':'.$roleid;
            if (isset($accessdata['rdef'][$k])) {
                continue;
            }
            $newrdefs[$k][$rd->capability] = (int)$rd->permission;
        }
        $rs->close();

        // share new role definitions
        foreach ($newrdefs as $k=>$unused) {
            if (!isset($ACCESSLIB_PRIVATE->rolepermissions[$k])) {
                $ACCESSLIB_PRIVATE->rolepermissions[$k] = $newrdefs[$k];
            }
            $accessdata['rdef'][$k] =& $ACCESSLIB_PRIVATE->rolepermissions[$k];
        }
        return true;
    }
    public function quickaccess_links() {
        global $DB, $CFG, $USER, $PAGE;
        $systemcontext = context_system::instance();
        $core_component = new core_component();
        $block_content = '';
        $local_pluginlist = $core_component::get_plugin_list('local');
        $block_pluginlist = $core_component::get_plugin_list('block');

        $block_content .= html_writer::start_tag('ul', array('class'=>'quickpop_over_ul'));
            //======= Dasboard link ========//
            // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard'));
            //     $button1 = html_writer::link($CFG->wwwroot, '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('leftmenu_dashboard', 'theme_epsilon').'</span>', array('class'=>'user_navigation_link'));
            //     $block_content .= $button1;
            // $block_content .= html_writer::end_tag('li');

            //=======Leader Dasboard link ========//
//             $gamificationb_plugin_exist = $core_component::get_plugin_directory('block', 'gamification');
//             $gamificationl_plugin_exist = $core_component::get_plugin_directory('local', 'gamification');
//             if($gamificationl_plugin_exist && $gamificationb_plugin_exist && (has_capability('local/gamification:view
// ',$systemcontext) || is_siteadmin() )){
//                 $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_gamification_leaderboard', 'class'=>'pull-left user_nav_div notifications'));
//                 $gamification_url = new moodle_url('/blocks/gamification/dashboard.php');
//                 $gamification = html_writer::link($gamification_url, '<i class="fa fa-trophy"></i><span class="user_navigation_link_text">'.get_string('leftmenu_gmleaderboard','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
//                 $block_content .= $gamification;
//                 $block_content .= html_writer::end_tag('li');
//             }
            $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_home', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/my/dashboard.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('home','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');
            $pluginnavs = array();
            foreach($local_pluginlist as $key => $local_pluginname){
                if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                    $functionname = 'local_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                        $data = $functionname();
                        foreach((array) $data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }
            // ksort($pluginnavs);
            // foreach($pluginnavs as $pluginnav){
            //     foreach($pluginnav  as $key => $value){
            //             $data = $value;
            //             $block_content .= $data;
            //     }
            // }

            foreach($block_pluginlist as $key => $local_pluginname){
                 if(file_exists($CFG->dirroot.'/blocks/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/blocks/'.$key.'/lib.php');
                    $functionname = 'block_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                    // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                        $data = $functionname();
                        foreach($data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    // $block_content .= html_writer::end_tag('li');
                    }
                }
            }
            if(file_exists($CFG->dirroot.'/admin/tool/certificate/lib.php')){
                require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');
                $functionname = 'tool_certificate_leftmenunode';
                if(function_exists($functionname)){
                // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                    $data = $functionname();
                    foreach($data as  $key => $val){
                        $pluginnavs[$key][] = $val;
                    }
                // $block_content .= html_writer::end_tag('li');
                }
            }

            ksort($pluginnavs);
            foreach($pluginnavs as $pluginnav){
                foreach($pluginnav  as $key => $value){
                        $data = $value;
                        $block_content .= $data;
                }
            }
            /*Site Administration Link*/
            if(is_siteadmin()){
                // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                //     $admin_url = new moodle_url('/repository/customfiles/file.php');
                //     $admin = html_writer::link($admin_url, '<span class="image_repository_icon dypatil_cmn_icon icon"></span><span class="user_navigation_link_text">'.get_string('repositoryfiles','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
                //     $block_content .= $admin;
                // $block_content .= html_writer::end_tag('li');
                $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/admin/search.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-cogs" aria-hidden="true"></i></span><span class="user_navigation_link_text">'.get_string('leftmenu_adminstration','theme_epsilon').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');

            }
        $block_content .= html_writer::end_tag('ul');

        return $block_content;
    }
// theme related setting

    public function get_primarycolor() {
        $primarycolor = '#25467a';
        $theme = theme_config::load('epsilon');
        $primarycolor= $theme->settings->primarycolor;
        $costcentercolor = $this->get_costcenter_scheme_css();
        if($costcentercolor && !empty($costcentercolor->button_color)){
            $primarycolor = $costcentercolor->button_color;
        }
        return $primarycolor;
    }
    public function get_secondarycolor() {
        $secondarycolor = '#006699';
        $theme = theme_config::load('epsilon');
        $secondarycolor= $theme->settings->secondarycolor;
        $costcentercolor = $this->get_costcenter_scheme_css();
        // var_dump($costcentercolors); exit;
        if($costcentercolor && !empty($costcentercolor->brand_color)){
            $secondarycolor = $costcentercolor->brand_color;
        }
        return $secondarycolor;
    }
    public function get_hovercolor() {
        $hovercolor = '#006699';
        $theme = theme_config::load('epsilon');
        $hovercolor= $theme->settings->hovercolor;
        $costcentercolor = $this->get_costcenter_scheme_css();
        //var_dump($costcentercolors); exit;
        if($costcentercolor && !empty($costcentercolor->hover_color)){
            $hovercolor = $costcentercolor->hover_color;
        }
        return $hovercolor;
    }
    public function getsitecolors_link(){
        global $CFG;
        return $CFG->wwwroot."/theme/epsilon/style/site_color.css?v=".date('Ymdhis');
    }


}
