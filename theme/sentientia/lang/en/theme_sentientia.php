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

/**
 * Language file.
 *
 * @package   theme_sentientia
 * @copyright 2026 Airpay Payment Services - Sentientia LMS
 * @author    Sentientia LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advancedsettings'] = 'Advanced settings';
$string['colorsettings'] = 'Color settings';

// White-label customer brand name. Used on the maintenance/error page, which renders
// with minimal context (during DB-down maintenance) where {{sitename}} is unavailable.
// Login + email source the name from the live site name ({{sitename}} / {{sitefullname}})
// so they white-label for free when a customer sets their site name; this string is the
// override point for surfaces that can't reach the site config. A new customer overrides
// it via Site admin → Language → Language customisation. (ADR-027 / Q1 white-label.)
$string['customername'] = 'Airpay Academy';

// P0 borrow #5 (Moodle 5.2) — OAuth2 button text localisable.
// Used by templates/core/loginform.mustache and identity-provider
// listings. Customer admins can override via Site Admin → Language
// customisation if they want a different phrasing.
$string['signinwithidentityprovider'] = 'or sign in with';
$string['backgroundimage'] = 'Background image';
$string['backgroundimage_desc'] = 'The image to display as a background of the site. The background image you upload here will override the background image in your theme preset files.';
$string['brandcolor'] = 'Brand colour';
$string['brandcolor_desc'] = 'The accent colour.';

$string['primarycolor'] = 'Site Primary Color';
$string['primarycolor_desc'] = 'Pick your site primary color for Button , Icons and Tabs';

$string['secondarycolor'] = 'Site Seconday Color';
$string['secondarycolor_desc'] = 'Pick your site seconday color';

$string['hovercolor'] = 'Site hover colour';
$string['hovercolor_desc'] = 'Pick your site hover colour';

$string['bootswatch'] = 'Bootswatch';
$string['bootswatch_desc'] = 'A bootswatch is a set of Bootstrap variables and css to style Bootstrap';
$string['choosereadme'] = 'Sentientia UX — standalone theme with full design system, per-customer branding, and responsive mobile experience.';
$string['currentinparentheses'] = '(current)';
$string['configtitle'] = 'Sentientia Academy UX';
$string['fontsize'] = 'Theme base fontsize';
$string['fontsize_desc'] = 'Enter a fontsize in %';
$string['generalsettings'] = 'General settings';
$string['loginbackgroundimage'] = 'Login page background image';
$string['loginbackgroundimage_desc'] = 'The image to display as a background for the login page.';
$string['nobootswatch'] = 'None';
$string['customsettings'] = 'Custom settings';
$string['pluginname'] = 'Sentientia UX';
$string['presetfiles'] = 'Additional theme preset files';
$string['presetfiles_desc'] = 'Preset files can be used to dramatically alter the appearance of the theme. See <a href="#">Sentientia presets</a> for information on creating and sharing your own preset files, and see the <a href="#">Presets repository</a> for presets that others have shared.';
$string['preset'] = 'Theme preset';
$string['preset_desc'] = 'Pick a preset to broadly change the look of the theme.';
$string['privacy:metadata'] = 'The Sentientia UX theme does not store any personal data about any user.';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'Use this field to provide SCSS or CSS code which will be injected at the end of the style sheet.';
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'In this field you can provide initialising SCSS code, it will be injected before everything else. Most of the time you will use this setting to define variables.';
$string['region-side-pre'] = 'Right';
$string['bodybgcolor'] = "Body bg color";
$string['bodybgcolor_desc'] = "The color you added here will be affects as Body's background color";
$string['showfooter'] = 'Show footer';
$string['unaddableblocks'] = 'Unneeded blocks';
$string['unaddableblocks_desc'] = 'The blocks specified are not needed when using this theme and will not be listed in the \'Add a block\' menu.';
$string['privacy:metadata:preference:draweropenblock'] = 'The user\'s preference for hiding or showing the drawer with blocks.';
$string['privacy:metadata:preference:draweropenindex'] = 'The user\'s preference for hiding or showing the drawer with course index.';
$string['privacy:metadata:preference:draweropennav'] = 'The user\'s preference for hiding or showing the drawer menu navigation.';
$string['privacy:drawerindexclosed'] = 'The current preference for the index drawer is closed.';
$string['privacy:drawerindexopen'] = 'The current preference for the index drawer is open.';
$string['privacy:drawerblockclosed'] = 'The current preference for the block drawer is closed.';
$string['privacy:drawerblockopen'] = 'The current preference for the block drawer is open.';
$string['privacy:drawernavclosed'] = 'The current preference for the navigation drawer is closed.';
$string['privacy:drawernavopen'] = 'The current preference for the navigation drawer is open.';

$string['customscss'] = 'Custom SCSS';

$string['welcometext'] = 'Welcome Text';
$string['welcometext_desc']= 'Welcome Text  will be displayed on the login welcome text  (Length should be not Morethan 15 Characters';


$string['logocaption'] = 'Caption Text'; 
$string['logocaptiondesc'] = 'The Caption text That you want show in logo (Length Should be 80 Characters.)';

$string['logo'] = 'Head Logo'; 
$string['logodesc'] = 'The Logo you uploaded here will be displayed as a Logo in Header.';

$string['loginorder'] = 'Login Form Order'; 
$string['loginorder_desc'] = 'You can make login form get placed left/right side based on your choice';

$string['carousellogo'] = 'Login Page Logo Right Side'; 
$string['carousellogo_desc'] = 'The Logo you uploaded here will be displayed on Login Page Slider Right Side.';

$string['loginlogo'] = 'Page Login Logo Left Side'; 
$string['loginlogo_desc'] = 'The Logo you uploaded here will be displayed on Login Page Life Side.';

$string['logindesc'] = 'Login Desc'; 
$string['logindesc_desc'] = 'The Text you you want to be displayed under the Login page in slider side (Length should be not more than 600 characters).';


$string['helpdesc'] = 'Help Desc';
$string['helpdesc_desc'] = 'The Text you uploaded here will be displayed when you click on Help button on the Login page';

$string['contact'] = 'Contact us Desc'; 
$string['contact_desc'] = 'The Text you uploaded here will be displayed when you click on Contact us button on the Login page';

$string['aboutus'] = 'About us Desc'; 
$string['aboutus_desc'] = 'The Text you uploaded here will be displayed when you click on About us button on the Login page';

//slider images
$string['slider1'] = 'Login Slider1';
$string['slider1desc'] = '<p class="m-0">Webp format  Image is preffered for better performance </p>The uploaded image will displayed as first slider for login page only';

$string['slider2'] = 'Login Slider2';
$string['slider2desc'] = '<p class="m-0">Webp format  Image is preffered for better performance </p>The uploaded image will displayed as second slider for login page only';

$string['slider3'] = 'Login Slider3';
$string['slider3desc'] = '<p class="m-0">Webp format  Image is preffered for better performance </p>The uploaded image will displayed as third slider for login page only';

$string['slider4'] = 'Login Slider4';
$string['slider4desc'] = '<p class="m-0">Webp format  Image is preffered for better performance </p>The uploaded image will displayed as fourth slider for login page only';

$string['slider5'] = 'Login Slider5';
$string['slider5desc'] = '<p class="m-0">Webp format  Image is preffered for better performance </p>The uploaded image will displayed as fifth slider for login page only';

/*favicon*/
$string['favicon'] = 'Favicon';
$string['favicondesc'] = 'Your site’s “favourite icon”. Here, you may insert the favicon for your site.';

$string['font'] = 'Font';
$string['font_desc'] = 'Selected font will be applied through out the LMS.';

$string['leftmenu_dashboard'] = 'Dashboard';
$string['leftmenu_adminstration'] = 'Administration';
$string['leftmenu_tm_dashboard'] = 'My Team';
$string['showhideblocks'] = 'Show/Hide blocks';
$string['leftmenu_gmleaderboard'] = 'Leaderboard';

$string['region-layerone_full'] = 'Layer one full width';
$string['region-layerone_one'] = 'Layer one-one';
$string['region-layerone_two'] = 'Layer one-two';
$string['region-layertwo_one'] = 'Layer two-one';
$string['region-layertwo_two'] = 'Layer two-two';
$string['region-layertwo_three'] = 'Layer two-three';
$string['region-layertwo_four'] = 'Layer two-four';
$string['region-teamoverview'] = 'Teamoverview';
$string['region-teamdetail_one'] = 'Teamdetail one';
$string['region-teamdetail_two'] = 'Teamdetail two';
$string['region-layerthree_one'] = 'Layer three-one';
$string['region-layerthree_two'] = 'Layer three-two';


$string['switchroleto'] = 'Switch role to:';
$string['switchroleas'] = 'Switch role as ';
$string['show_more_less'] = 'Show more/less';
$string['theme_scheme'] = 'Theme scheme';
$string['theme_scheme_desc'] = 'Theme scheme change this to bring lot of coloring changes to the site';
$string['scheme_1'] = 'Scheme 1';
$string['scheme_2'] = 'Scheme 2';
$string['scheme_3'] = 'Scheme 3';
$string['scheme_4'] = 'Scheme 4';
$string['scheme_5'] = 'Scheme 5';
$string['scheme_6'] = 'Scheme 6';
$string['square'] = 'Square';
$string['rounded'] = 'Circle';
$string['rounded-square'] = 'Rounded-square';



$string['customscheme'] = 'Use custom scheme';
$string['left_menu_requests'] = 'Manage Requests';

$string['facebook'] = "Facebook URL";
$string['facebookdesc'] = "The URL you added here will be the path for your Facebook page";

$string['twitter'] = "Twitter URL";
$string['twitterdesc'] = "The URL you added here will be the path for your Twitter page";

$string['linkedin'] = "linkedIn";
$string['linkedindesc'] = "The URL you added here will be the path for your linkedIn page";

$string['youtube'] = "Youtube URL";
$string['youtubedesc'] = "The URL you added here will be the path for your Youtube page";

$string['instagram'] = "Instagram URL";
$string['instagramdesc'] = "The URL you added here will be the path for your Instagram page";

$string['footerbgcolor'] = "Footer's bg color";
$string['footerbg_desc'] = "The color you added here will be affects as Footer's background color";
$string['copyright'] = 'Copyright';
$string['copyrightdesc'] = 'Whatever you add to this textarea will be displayed in the footer throughout your Sentientia LMS site, e.g. Copyright.';
$string['employee'] = "Employee";

$string['quickinfo'] = 'Quick Info';
$string['quickinfodesc'] = 'Quick Information';
$string['quickinfo1'] = 'Quick Info1';
$string['quickinfo2'] = 'Quick Info2';
$string['quickinfo3'] = 'Quick Info3';
$string['quickinfo4'] = 'Quick Info4';
$string['quickinfo5'] = 'Quick Info5';
$string['disable'] = 'Disable';
$string['enable'] = 'Enable';

$string['region-course-pre'] = 'Course Pre';

$string['quickaccess'] = 'Quick Access';
$string['home'] = 'Dashboard';
$string['colorschemes'] = 'Colorschemes';
$string['phonenumber'] = 'Mobile Number';
$string['learnerlogin'] ='Enter Mobile Number';

$string['enterotp'] = 'Enter OTP';
$string['resentotp'] = 'Resend OTP';
$string['login_submit'] = 'Log in';
$string['entermobileotp'] = 'Entered mobile number is not exists, please check';

// P0 borrow #14 (Moodle 5.2, 2026-05-23) — extra sort options on the
// block_myoverview "My Courses" dropdown. The template override at
// templates/block_myoverview/nav-sort-selector.mustache references
// these strings via {{#str}}.
$string['sortbystartdate'] = 'Course start date';
$string['sortbyenddate']   = 'Course end date';

// P0 #3 follow-up (2026-05-24, chip-B) — primary navbar i18n.
$string['nav_dashboard']  = 'Dashboard';
$string['nav_courses']    = 'My Courses';
$string['nav_catalog']    = 'Catalog';
$string['nav_profile']    = 'Profile';
$string['nav_home']       = 'Home';
$string['a11y_search']    = 'Search courses, people, content';
$string['a11y_usermenu']  = 'User menu';
$string['a11y_mobilemenu'] = 'Mobile menu';

// P0 #4 follow-up (2026-05-24, chip-B) — footer i18n.
$string['footer_privacy']   = 'Privacy';
$string['footer_terms']     = 'Terms';
$string['footer_help']      = 'Help';
$string['footer_contact']   = 'Contact';
$string['footer_copyright'] = '&copy; 2026 airpay payment services pvt. ltd.';
// B10/F-065 stabilization fix (2026-05-28) — i18n the 6 hardcoded English
// secondary labels the F-13 sweep missed on dashboard.mustache.
$string['dash_dark_mode_label']        = 'Dark Mode';
$string['dash_profile_settings']       = 'Profile &amp; Settings';
$string['dash_overall_completion']     = 'Overall Completion';
$string['dash_continue_learning']      = 'Continue Learning';
$string['dash_view_all']               = 'View all';
$string['dash_explore']                = 'Explore';

// F-13 (Platform Visual Audit 2026-05-24, chip-G) — dashboard welcome
// banner, chart titles, and compliance KPI labels. {$a} = first name.
$string['welcome_back_admin']      = 'Welcome back, {$a}';
$string['subtitle_admin']          = 'Platform overview and system health';
$string['welcome_manager']         = 'Welcome, {$a}';
$string['subtitle_manager']        = 'Team overview and compliance status';
$string['welcome_learner']         = 'Welcome back, {$a}!';
$string['subtitle_learner']        = 'Continue where you left off and keep building your skills';
$string['chart_enrolment_trend']   = 'Enrolment Trend';
$string['chart_course_distribution'] = 'Course Distribution';
$string['course_userenrolments']   = 'User enrolments:';
$string['course_usercompletion']   = 'User completion:';
$string['course_start']            = 'Start';
$string['kpi_mandatory_courses']   = 'Mandatory Courses';
$string['kpi_compliance_rate']     = 'Compliance Rate';
$string['kpi_overdue']             = 'Overdue';
$string['kpi_total_assigned']      = 'Total Assigned';

// App-shell chrome i18n (2026-08-04) — persona sidebar labels
// (classes/sidebar_navigation.php), shell course player
// (templates/course.mustache) and topbar chrome
// (core_renderer::airpay_shell_start). Core strings are reused where an
// exact match exists (myhome=Dashboard, reports, notifications, profile);
// the keys below cover the rest. a11y_* keys are aria-labels/titles only.
$string['a11y_activitiescompleted'] = '{$a->completed} of {$a->total} activities completed, {$a->percent}%';
$string['a11y_backtocourse'] = 'Back to {$a}';
$string['a11y_courseprogressbar'] = 'Course completion progress';
$string['a11y_courseprogressnav'] = 'Course progress and navigation';
$string['a11y_openmenu'] = 'Open menu';
$string['a11y_togglecoursesidebar'] = 'Toggle course sidebar';
$string['a11y_togglesidebar'] = 'Toggle sidebar';
$string['course_content'] = 'Course Content';
$string['course_next'] = 'Next: {$a}';
$string['course_percentcomplete'] = '{$a}% complete';
$string['nav_analytics'] = 'Analytics';
$string['nav_browseairpaylibrary'] = 'Browse Airpay Library';
$string['nav_certificates'] = 'Certificates';
$string['nav_classrooms'] = 'Classrooms';
$string['nav_compliance'] = 'Compliance';
$string['nav_coursesharerequests'] = 'Course-share Requests';
$string['nav_emails'] = 'Emails';
$string['nav_evaluations'] = 'Evaluations';
$string['nav_learningpaths'] = 'Learning Paths';
$string['nav_livesessions'] = 'Live Sessions';
$string['nav_managecourses'] = 'Manage Courses';
$string['nav_manageusers'] = 'Manage Users';
$string['nav_mycart'] = 'My Cart';
$string['nav_myrequests'] = 'My Requests';
$string['nav_myskills'] = 'My Skills';
$string['nav_onlineexams'] = 'Online Exams';
$string['nav_organisation'] = 'Organisation';
$string['nav_privacy'] = 'Privacy';
$string['nav_programs'] = 'Programs';
$string['nav_siteadmin'] = 'Site Admin';
$string['nav_skills'] = 'Skills';
$string['searchplaceholder'] = 'Search courses, people, content...';
