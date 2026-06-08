<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_airpayux\output\traits;

// Imports the trait uses — required because traits live in their own
// file/namespace and don't inherit imports from the using class.
use moodle_url;
use html_writer;
use context_system;
use core_text;
use action_menu;
use action_menu_filler;
use action_menu_link_secondary;
use pix_icon;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * User-menu renderer.
 *
 * Extracted from `core_renderer.php` in Engineering 32 (decomposition
 * pass 7). The largest remaining method on the monolith — ~350
 * lines — moves here verbatim:
 *
 *   user_menu($user, $withlinks): string
 *     Builds the top-right user dropdown menu. Walks a long
 *     decision tree covering:
 *       - the not-logged-in / login-page / guest-user fallbacks,
 *       - the role-switcher (employee + manager-level roles from
 *         the BizLMS cost-center hierarchy via
 *         \local_sentientia_org\accesslib),
 *       - the logout link,
 *       - the "viewing as" + MNet + login-failures metadata
 *         decorations on the avatar,
 *       - the final action_menu construction with item-type
 *         dispatch on each nav item.
 *
 * Why this method is large
 * ------------------------
 * The user menu has the most interleaved business logic in the whole
 * renderer:
 *
 *   - tenant role-switching (depths/category iteration)
 *   - the "switch to learner" shortcut hard-wired to the employee role
 *   - the "auto-switch to highest available role on first visit" path
 *     that triggers a redirect()
 *   - the per-item css class assignments for active / disabled states
 *
 * Decomposing further would require also decomposing
 * theme_airpayux_user_get_user_navigation_info() (in lib.php) which is
 * the partner data builder. Out of scope for the trait extraction —
 * tracked separately.
 *
 * Dependencies on the using class
 * --------------------------------
 *   - $this->page                       (inherited from \core_renderer)
 *   - $this->is_login_page()            (inherited)
 *   - $this->theme_airpayux_user_get_user_navigation_info(...)
 *                                       (stays in core_renderer for now)
 *   - $this->role_switch_basedon_userroles(...)
 *                                       (stays in core_renderer)
 *   - parent::render($am)               (resolves to \core_renderer::render)
 *
 * `parent::render($am)` works inside a trait because PHP resolves the
 * `parent::` reference against the parent of the using class, not
 * against the trait itself. The trait has no parent — the class that
 * `use`s it has \core_renderer as its parent, and that's what
 * `parent::` here resolves to.
 *
 * @package theme_airpayux
 */
trait user_menu {

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
        $opts = $this->theme_airpayux_user_get_user_navigation_info($user, $this->page, array('avatarsize' => 35));

        /*Start of the role Switch */
        $systemcontext = context_system::instance();
        $roles = \local_sentientia_org\accesslib::get_user_roles_in_catgeorycontexts($USER->id);

        if (is_array($roles) && (count($roles) > 0)) {

            $switchrole = new stdClass(); /*Role for the Learner i.e user role */
            $switchrole->itemtype = 'link';
            $learner_record_sql = "SELECT id, name, shortname
                                    FROM {role}
                                    WHERE shortname = 'employee' AND archetype = 'student' ";
            $learnerroleid = $DB->get_record_sql($learner_record_sql);
            if(!empty($USER->access['rsw'])){
                $USER->access['rsw']['/1'] = $learnerroleid->id;
            }
            $rolename = get_string('employee','theme_airpayux');

            $depths = [];
            $depths['depth']=array();
            $user_ra_array = array_values(array_filter(array_map(function($role)use(&$depths){

                            $categoryids = array_values(array_filter((explode('/', $role->path))));
                            $pathname=end($categoryids);
                            $category = \local_sentientia_org\accesslib::get_category_info($pathname, 'name');
                                if(!in_array($role->depth.'_'.$categoryids[0], $depths['depth'])){
                                    $depths['depth'][] = $role->depth.'_'.$categoryids[0];
                                    $role->categoryname = $category;
                                    $role->highest_catid = $categoryids[0];
                                    return $role;
                                }

                        }, $roles)));

            if(!empty($user_ra_array) && is_array($user_ra_array)){
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
                 $switchrole->title = get_string('switchroleas','theme_airpayux').$rolename;
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
                $switchrole->title = get_string('switchroleas','theme_airpayux').$rolename;
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
                 redirect(new moodle_url('/'));

            }
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

    /**
     * Build the BizLMS role-switch options as a flat, template-ready array.
     *
     * Why this exists separately from user_menu()
     * -------------------------------------------
     * user_menu() above renders a Moodle action_menu dropdown designed to
     * live in a top-right navbar. The airpayux dashboard "shell" layout
     * (layout/dashboard.php → templates/dashboard.mustache, use_shell=true)
     * moved all user controls into the left sidebar and renders neither
     * navbar.mustache nor topbar.mustache — so the switcher built by
     * user_menu() was never surfaced (it sat unused in the topbar context).
     * Multi-role users (e.g. an L&D admin who is also a learner) therefore
     * had no visible way to switch roles in the shell, even though
     * /my/switchrole.php + \local_sentientia_org\accesslib work fine.
     *
     * This method returns just the switch *data* (no action_menu, no avatar,
     * no logout) so the sidebar can paint a native airpayux control. It is a
     * deliberate, isolated sibling of user_menu(): the role-resolution logic
     * is intentionally duplicated rather than shared, because user_menu() is
     * still invoked for the (currently dormant) topbar context and also
     * carries a first-visit redirect() side-effect — refactoring a shared
     * helper out of it would risk that live path. Tracked as a follow-up
     * de-duplication candidate. Keep the two in sync if the switch URL
     * contract changes.
     *
     * Backwards-compat: single-role users (the overwhelming majority —
     * ordinary learners) get hasoptions=false and the sidebar renders
     * nothing new, so their experience is unchanged.
     *
     * @return array {
     *     hasoptions:   bool   true only when 2+ distinct switch targets exist
     *     currentlabel: string human label of the currently-active role ('' if unknown)
     *     options:      array  list of [url, label, icon, active]
     * }
     */
    public function get_role_switch_options(): array {
        global $USER, $DB;

        $result = ['hasoptions' => false, 'currentlabel' => '', 'options' => []];

        // BizLMS-only capability. On a vanilla Moodle (a future non-BizLMS
        // Sentientia customer) the resolver is absent — fail closed, render
        // nothing. Mirrors the defensive open_path read in session_manager.
        if (!class_exists('\\local_sentientia_org\\accesslib')) {
            return $result;
        }

        $roles = \local_sentientia_org\accesslib::get_user_roles_in_catgeorycontexts($USER->id);
        if (!is_array($roles) || count($roles) === 0) {
            return $result;
        }

        // The hard-wired "switch to learner" shortcut — the BizLMS employee
        // role (archetype student). Same lookup user_menu() uses.
        $learnerrole = $DB->get_record_sql(
            "SELECT id, name, shortname
               FROM {role}
              WHERE shortname = 'employee' AND archetype = 'student'");

        // De-dupe the category-context roles by depth + top-category, exactly
        // as user_menu() does, so the sidebar list matches the dropdown.
        $depths = [];
        $userra = array_values(array_filter(array_map(function($role) use (&$depths) {
            $categoryids = array_values(array_filter(explode('/', $role->path)));
            if (empty($categoryids)) {
                return null;
            }
            $pathname = end($categoryids);
            $category = \local_sentientia_org\accesslib::get_category_info($pathname, 'name');
            $key = $role->depth . '_' . $categoryids[0];
            if (!in_array($key, $depths, true)) {
                $depths[] = $key;
                $role->categoryname = $category;
                $role->highest_catid = $categoryids[0];
                return $role;
            }
            return null;
        }, $roles)));

        // The currently-active role. $USER->useraccess['currentroleinfo'] is
        // written by two different paths with DIFFERENT key sets:
        //   - accesslib::set_user_role_switch()  (the /my/switchrole.php path)
        //         -> {roleid, contextid}                  (no orgcatid/depth)
        //   - core_renderer::role_switch_basedon_userroles() (first-visit
        //         auto-redirect) -> {roleid, orgcatid, depth, contextinfo}
        //                                                  (no top-level contextid)
        // roleid is the ONLY key both guarantee, so match primarily on roleid
        // and tighten with contextid / orgcatid only when they're present.
        // (The old triple roleid+depth+orgcatid match silently failed after a
        // switchrole.php switch, because depth/orgcatid were absent and fell
        // back to the highest role's values — VIS follow-up 2026-05-28.)
        if (!empty($userra)) {
            $highest = max($userra);
        } else {
            $highest = (object) ['roleid' => 0, 'highest_catid' => 0, 'depth' => 0];
        }
        $cri             = $USER->useraccess['currentroleinfo'] ?? [];
        $switchroleid    = (int) ($cri['roleid'] ?? 0);
        $switchcontextid = (int) ($cri['contextid'] ?? 0);
        $switchcatid     = (int) ($cri['orgcatid'] ?? 0);

        // Build a working list, decide which one is active, THEN render urls —
        // the active option is a non-clickable marker, not a switch link.
        $work = [];

        // Learner / employee shortcut — matched on roleid alone (it carries no
        // meaningful category/context).
        if (!empty($learnerrole)) {
            $work[] = [
                'label'      => get_string('employee', 'theme_airpayux'),
                'icon'       => 'fa-user',
                '_roleid'    => (int) $learnerrole->id,
                '_contextid' => 0,
                '_catid'     => 0,
                '_islearner' => true,
                'active'     => ($switchroleid > 0 && (int) $learnerrole->id === $switchroleid),
            ];
        }

        // One option per distinct category role the user holds.
        foreach ($userra as $role) {
            if (!empty($role->rolename)) {
                $label = $role->categoryname . ' - ' . $role->rolename;
            } else {
                $label = $role->categoryname . ' - ' . ($role->rolecode ?? '');
            }
            $active = ($switchroleid > 0
                && (int) $role->roleid === $switchroleid
                && ($switchcontextid === 0 || (int) $role->contextid === $switchcontextid)
                && ($switchcatid === 0 || (int) $role->highest_catid === $switchcatid));
            $work[] = [
                'label'      => $label,
                'icon'       => 'fa-user-circle-o',
                '_roleid'    => (int) $role->roleid,
                '_contextid' => (int) $role->contextid,
                '_catid'     => (int) $role->highest_catid,
                '_islearner' => false,
                'active'     => $active,
            ];
        }

        // Fallback: if nothing matched (a fresh session before any switch, or
        // a switch whose stored keys didn't line up), align the marker with
        // role_detector — the SAME source of truth that selects which dashboard
        // renders — so the highlighted role always matches what the user sees.
        $anyactive = false;
        foreach ($work as $w) {
            if ($w['active']) { $anyactive = true; break; }
        }
        if (!$anyactive && !empty($work) && class_exists('\\theme_airpayux\\role_detector')) {
            $tier = \theme_airpayux\role_detector::detect();
            $marked = false;
            if (!empty($tier['switched_to_employee'])) {
                foreach ($work as $i => $w) {
                    if ($w['_islearner']) { $work[$i]['active'] = true; $marked = true; break; }
                }
            }
            if (!$marked) {
                // Default: the highest category role — what the auto-redirect
                // selects on first visit and what the admin/manager dashboard
                // reflects.
                foreach ($work as $i => $w) {
                    if (!$w['_islearner']
                            && $w['_roleid'] === (int) $highest->roleid
                            && $w['_catid'] === (int) ($highest->highest_catid ?? 0)) {
                        $work[$i]['active'] = true; $marked = true; break;
                    }
                }
                if (!$marked) {
                    foreach ($work as $i => $w) {
                        if (!$w['_islearner']) { $work[$i]['active'] = true; break; }
                    }
                }
            }
        }

        // Render: active = non-clickable marker (empty url); others = links.
        foreach ($work as $w) {
            if ($w['active']) {
                $url = '';
            } else if ($w['_islearner']) {
                $url = (new moodle_url('/my/switchrole.php', [
                    'sesskey'    => sesskey(),
                    'confirm'    => 1,
                    'switchrole' => $w['_roleid'],
                ]))->out(false);
            } else {
                $url = (new moodle_url('/my/switchrole.php', [
                    'sesskey'    => sesskey(),
                    'confirm'    => 1,
                    'switchrole' => $w['_roleid'],
                    'contextid'  => $w['_contextid'],
                ]))->out(false);
            }
            $result['options'][] = [
                'url'    => $url,
                'label'  => $w['label'],
                'icon'   => $w['icon'],
                'active' => $w['active'],
            ];
            if ($w['active']) {
                $result['currentlabel'] = $w['label'];
            }
        }

        // Only surface the control when there's an actual choice to make.
        $result['hasoptions'] = count($result['options']) > 1;

        return $result;
    }
}
