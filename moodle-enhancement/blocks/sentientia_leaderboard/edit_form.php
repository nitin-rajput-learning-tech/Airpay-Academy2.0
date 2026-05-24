<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Block instance config form.
 *
 * One selector: which board to render. Defaults to "first visible board"
 * which is what the block does when boardid is 0 (or missing).
 *
 * @package block_sentientia_leaderboard
 */

defined('MOODLE_INTERNAL') || die();

class block_sentientia_leaderboard_edit_form extends block_edit_form {

    protected function specific_definition($mform): void {
        global $USER;

        $mform->addElement('header', 'configheader',
            get_string('blocksettings', 'block'));

        // Build the list of boards visible to the current user.
        $context = \context_system::instance();
        $can_view_all = has_capability(
            'local/sentientia_leaderboard:viewall', $context);
        $viewer_root = class_exists('\\local_airpay_core\\tenant')
            ? \local_airpay_core\tenant::root_for_current_user() : 0;
        $boards = \local_sentientia_leaderboard\board_manager::list_visible(
            $viewer_root, $can_view_all);

        $options = [
            0 => get_string('block_choose',
                'local_sentientia_leaderboard'),
        ];
        foreach ($boards as $b) {
            $options[(int) $b->id] = format_string($b->name)
                . ' (' . get_string('type_' . $b->type,
                    'local_sentientia_leaderboard') . ')';
        }

        $mform->addElement('select', 'config_boardid',
            get_string('block_title', 'block_sentientia_leaderboard'),
            $options);
        $mform->setDefault('config_boardid', 0);
        $mform->setType('config_boardid', PARAM_INT);
    }
}
