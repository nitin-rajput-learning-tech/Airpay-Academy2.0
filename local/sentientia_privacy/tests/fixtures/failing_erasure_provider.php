<?php
// This file is part of Sentientia LMS.

/**
 * Test doubles for privacy_manager_test: a privacy provider whose erasure
 * always fails, and a privacy_manager that asks it as well as the real ones.
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy\test;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Reports data in the system context for anyone, then fails to erase it -
 * what a provider with a broken query or a missing table looks like.
 */
class failing_erasure_provider {

    /** @var string The message the failure carries, asserted on by the test. */
    const MESSAGE = 'simulated provider failure';

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        throw new \coding_exception(self::MESSAGE);
    }
}

/**
 * The real privacy_manager, plus one provider that always fails.
 */
class privacy_manager_with_a_failing_provider extends \local_sentientia_privacy\privacy_manager {

    protected static function erasure_providers(): array {
        return parent::erasure_providers()
            + ['local_sentientia_failing' => failing_erasure_provider::class];
    }
}
