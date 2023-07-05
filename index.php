<!DOCTYPE html>
<html lang="en">
<head>
<?php 
    
    $pageTitle = "Airpay Payment Services - India’s first integrated omnichannel financial services platform";$pageDesc = "Accept 140+ payment instruments across all sales points. A dynamic and versatile payment gateway enabling you to accept various payment instruments like Credit Cards, Debit Cards, Net Banking, RTGS/IMPS/NEFT, Bharat QR, UPI, Cash, Corporate Cards, Loyalty Cards, Wallets, and Prepaid Cards across multiple sales channels. Airpay is among top payment gateway solutions providers in India. We offer online payment processing services, ecommerce payment solutions &amp; credit card processing options with no hidden charges &amp; no setup cost.";$pageKeywords = "airpay, payment, gateway, wallet, credit card, debit card, airpay, airpay payment gateway service, airpay payment service, online payment gateway in india, payment processing services india, payment gateway solutions providers, airpay online payment processing service, credit card processing options, payment solutions for ecommerce, EDC, POS, ";include 'layout/header.php';?>
</head>
<body class="homepage">
<?php 

include 'layout/menu.php';

if (!file_exists('./config.php')) {
    header('Location: install.php');
    die;
}

require_once('config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');

redirect_if_major_upgrade_required();

$urlparams = array();
if (!empty($CFG->defaulthomepage) &&
        ($CFG->defaulthomepage == HOMEPAGE_MY || $CFG->defaulthomepage == HOMEPAGE_MYCOURSES) &&
        optional_param('redirect', 1, PARAM_BOOL) === 0
) {
    $urlparams['redirect'] = 0;
}
$PAGE->set_url('/', $urlparams);
$PAGE->set_pagelayout('frontpage');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');

// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
$PAGE->set_cacheable(false);

require_course_login($SITE);

$hasmaintenanceaccess = has_capability('moodle/site:maintenanceaccess', context_system::instance());

// If the site is currently under maintenance, then print a message.
if (!empty($CFG->maintenance_enabled) and !$hasmaintenanceaccess) {
    print_maintenance_message();
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect($CFG->wwwroot .'/'. $CFG->admin .'/index.php');
}

// If site registration needs updating, redirect.
\core\hub\registration::registration_reminder('/index.php');
if (get_home_page() != HOMEPAGE_SITE) {
    // Redirect logged-in users to My Moodle overview if required.
    $redirect = optional_param('redirect', 1, PARAM_BOOL);
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && $redirect === 1) {
        // At this point, dashboard is enabled so we don't need to check for it (otherwise, get_home_page() won't return it).
        redirect($CFG->wwwroot .'/my/');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MYCOURSES) && $redirect === 1) {
        redirect($CFG->wwwroot .'/my/courses.php');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
        $frontpagenode = $PAGE->settingsnav->find('frontpage', null);
        if ($frontpagenode) {
            $frontpagenode->add(
                get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        } else {
            $frontpagenode = $PAGE->settingsnav->add(get_string('frontpagesettings'), null, navigation_node::TYPE_SETTING, null);
            $frontpagenode->force_open();
            $frontpagenode->add(get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING);
        }
    }
}

// Trigger event.
course_view(context_course::instance(SITEID));

$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('');
$editing = $PAGE->user_is_editing();
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_secondary_active_tab('coursehome');

$courserenderer = $PAGE->get_renderer('core', 'course');

if ($hassiteconfig) {
    $editurl = new moodle_url('/course/view.php', ['id' => SITEID, 'sesskey' => sesskey()]);
    $editbutton = $OUTPUT->edit_button($editurl);
    $PAGE->set_button($editbutton);
}

echo $OUTPUT->header();

$siteformatoptions = course_get_format($SITE)->get_format_options();
$modinfo = get_fast_modinfo($SITE);
$modnamesused = $modinfo->get_used_module_names();
?>
<div class="wrap">
	<div class="loader"></div>

		<div class="section sec-hbanner">
			<div class="data-container">
				<div class="data-wrap">
					<div class="databox">
                            <h2 class="sec-th">A comprehensive & hybrid learning platform</h2>
							<p class="sectx">airpay academy is an extensive training & development programme designed to improve your abilities in the financial services sector</p>
							<!--<a href="#" target="_blank" class="btn mb-3">Read More</a>-->
					</div>
				</div>
				<div class="data-img">
					<img src="resources/images/bannerimg.png">
				</div>
			</div>
			<div class="halfround">
				<!--<img src="resources/images/bannertop.png">-->
			</div>
        </div>
		
		<div class="section sec-hsec2">
			<div class="container">		
				<h2 class="sec-th text-center">Three Pillars of Learning and Empowerment</h2>
				<div class="row">
					<div class="col-md-4 lside">
						<div class="fewrap">
							<i><img src="resources/images/fe01.jpg"></i>
							<h4>Employability & Entrepreneurial Skills</h4>
						</div>
					</div>
					<div class="col-md-4 cside">
						<div class="fewrap">
							<i><img src="resources/images/fe02.jpg"></i>
							<h4>Business</br>Training</h4>
						</div>					
					</div>
					<div class="col-md-4 rside">
						<div class="fewrap">
							<i><img src="resources/images/fe03.jpg"></i>
							<h4>Financial </br>Education</h4>
						</div>					
					</div>
				</div>
			</div>
		</div>
		
		<div class="section sec-hsec2">
			<div class="container">
			<div class="row align-items-center">
				<div class="col-lg-6 col-xl-5 data-left-xl">
					<h2 class="sec-th">Employability and Entrepreneurial Skills</h2>
					<p class="sectx">The employability and entrepreneurial skills course will equip you with the below essential skills required for professional success in business and the workplace</p>
					<ul class="list listcol2">
						<li>Communication</li>
						<li>Adaptability</li>
						<li>Time Management</li>
						<li>Leadership</li>
						<li>Critical Thinking</li>
					</ul>
				</div>
				<div class="col-lg-6 col-xl-7 data-imgcenter">
					<img src="resources/images/employability.png">
				</div>
			</div>
			</div>
		</div>
		
		<div class="section sec-hsec3">
			<div class="container">
			<div class="row rowbar-lg align-items-center">
				<div class="col-lg-6 col-xl-5">
					<h2 class="sec-th">Business Training</h2>
					<p class="sectx">The business training course focuses on practical and in-demand industry-specific skills </p>
					<ul class="list listcol2">
						<li>Digital Literacy</li>
						<li>Sales</li>
						<li>Financial Management</li>
						<li>Project Management</li>
						<li>Marketing</li>
					</ul>
				</div>
				<div class="col-lg-6 col-xl-7">
					<img src="resources/images/business02.png">
				</div>
			</div>
			</div>
		</div>
		
		<div class="section sec-hsec4">
			<div class="container">
			<div class="row align-items-center">
				<div class="col-lg-6 col-xl-5 data-left-xl">
					<h2 class="sec-th">Financial Education</h2>
					<p class="sectx">The financial education course will provide you necessary knowledge to make informed decisions about your personal finances and investments</p>
					<ul class="list listcol2">
						<li>Banking</li>
						<li>Insurance</li>
						<li>Digital Payments</li>
						<li>Investment</li>
						<li>Retirement and Pension Planning</li>
					</ul>
				</div>
				<div class="col-lg-6 col-xl-7 data-imgcenter">
					<img src="resources/images/education03.png">
				</div>
			</div>
			</div>
		</div>
		
		
		<div class="section sec-vyaapaaris">
			<div class="data-container">
				<div class="data-imgwrap">
					<div class="data-roundimg"></div>
					<div class="data-img">
						<img src="resources/images/vyaapaaris.png">
					</div>
				</div>
				<div class="data-wrap">
					<div class="databox">
                            <h2 class="sec-th">Unlock success by boosting your skills Stay Ahead... Stay ahead, and More!... and more!</h2>
							<p class="sectx">Stay Ahead of the game with training tailored for YOUR future. Explore the Perfect Program for Professionals, Entrepreneurs, Small Businesses, Women Entrepreneurs, Farmers, Students, and More!</p>
					</div>
				</div>
			</div>
        </div>
		
		<div class="section sec-hsec5">
			<div class="container">
			<div class="row">
				<div class="col-md-6">
					<img src="resources/images/advantage.png">
				</div>
				<div class="col-md-6">					
					<img src="resources/images/course-offering.png">
				</div>
			</div>
			</div>
		</div>
		
		<div class="section sec-faq">
			<div class="container">
				<h2 class="sec-th text-center">Frequently asked questions</h2>
				
				<div class="accordion" id="accordionfaq">
				
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
						1. Who can benefit from airpay academy?
					  </button>
					</h2>
					<div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ airpay academy is a valuable resource for anyone looking to improve their skills and knowledge. Whether you are a professional looking to advance your career, an entrepreneur looking to start or grow your business, a small business owner looking to improve your operations, a woman entrepreneur looking to break through the glass ceiling, a farmer looking to increase your yields, or a student looking to get ahead, airpay academy has something to offer you.
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
						2. What courses will be available on airpay academy?
					  </button>
					</h2>
					<div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						airpay academy offers a wide range of courses, covering a variety of topics, including:
						<strong>Business skills, Technical skills, Leadership skills, Communication skills, Problem-solving skills, Creativity skills, Innovation skills, Entrepreneurship skills, And much more! </strong></div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
						3. Is it necessary to have prior knowledge or experience to take a course on airpay academy?
					  </button>
					</h2>
					<div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						No, airpay academy courses are designed for individuals of all levels, from beginners to experienced professionals. airpay academy has something to offer everyone.
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
						4. Can I access the courses on airpay academy from any device?
					  </button>
					</h2>
					<div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ Yes, you can access the courses on airpay academy from any device with an internet connection. The academy portal is available 24/7, so you can learn at your own pace and on your own time.
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
						5. How long do I have access to the courses on airpay academy?
					  </button>
					</h2>
					<div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ ​ Once you sign up for airpay academy, you will have access to courses based on their availability. This means that you can learn and grow at your own pace, and you never have to worry about missing out on a course.
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
						6. Are there any prerequisites for enrolling in a course on airpay academy?
					  </button>
					</h2>
					<div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ ​ No, there are no prerequisites for enrolling in a course on airpay academy. However, some courses may have recommended prerequisites or suggest that you complete certain other courses first in order to get the most out of the course. These recommendations are usually provided for your benefit and are meant to help you get the most out of the course. 
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7" aria-expanded="false" aria-controls="collapse7">
						7. Is there a certificate of completion available for courses on airpay academy?
					  </button>
					</h2>
					<div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ ​Certificates of completion are available for some courses based on the content and duration. Certificates will be awarded to learners who successfully complete a course and meet the necessary criteria. Certificates can be used to demonstrate your skills and knowledge to potential employers or clients.
					  </div>
					</div>
				  </div>
				  
				  <div class="accordion-item">
					<h2 class="accordion-header">
					  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8" aria-expanded="false" aria-controls="collapse8">
						8. How can I learn more about airpay academy?
					  </button>
					</h2>
					<div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#accordionfaq">
					  <div class="accordion-body">
						​ ​​ To learn more about airpay academy, please visit our website or contact us at <a class="bluetext" href="mailto:academy@airpay.co.in ">academy@airpay.co.in</a> 
					  </div>
					</div>
				  </div>
				  
				  
				</div>
			</div>
		</div>
		
		
		
</div>
<!-- ./wrapper -->

<?php include 'layout/footer.php';?>
</body>
</html>
