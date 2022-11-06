<?php

require_once('../../config.php');
require_once('lib.php');

$action = optional_param('action', '', PARAM_ALPHA);

require_login();

if (empty($CFG->usetags)) {
    print_error('tagdisabled');
}

if (isguestuser()) {
    print_error('noguest');
}

if (!confirm_sesskey()) {
    print_error('sesskey');
}

$usercontext = context_user::instance($USER->id);

switch ($action) {
    case 'addinterest':
        if (!local_tags_tag::is_enabled('core', 'user')) {
            print_error('tagdisabled');
        }
        $tag = required_param('tag', PARAM_TAG);
        local_tags_tag::add_item_tag('core', 'user', $USER->id, $usercontext, $tag);
        $tc = local_tags_area::get_collection('core', 'user');
        redirect(local_tags_tag::make_url($tc, $tag));
        break;

    case 'removeinterest':
        if (!local_tags_tag::is_enabled('core', 'user')) {
            print_error('tagdisabled');
        }
        $tag = required_param('tag', PARAM_TAG);
        local_tags_tag::remove_item_tag('core', 'user', $USER->id, $tag);
        $tc = local_tags_area::get_collection('core', 'user');
        redirect(local_tags_tag::make_url($tc, $tag));
        break;

    case 'flaginappropriate':

        $context =(new \local_tags\lib\accesslib())::get_module_context();

        require_capability('moodle/tag:flag', $context);
        $id = required_param('id', PARAM_INT);
        $tagobject = local_tags_tag::get($id, '*', MUST_EXIST);
        $tagobject->flag();
        redirect($tagobject->get_view_url(), get_string('responsiblewillbenotified', 'tag'));
        break;

    default:
        print_error('unknowaction');
        break;
}
