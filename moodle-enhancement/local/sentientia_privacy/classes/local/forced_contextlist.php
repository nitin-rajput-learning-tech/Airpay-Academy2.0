<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * A contextlist whose id array can be set directly, to build a list with a key hole on purpose.
 *
 * core's contextlist_base::set_contextids() is array_unique() without a reindex
 * (privacy/classes/local/request/contextlist_base.php:66), so force([x, x, y]) stores {0: x, 2: y}. Its
 * iterator stops at the first missing key (valid() is isset() on the position, :170), so foreach yields x
 * only. That is the core defect erasure_completeness repairs. This class lets the runtime self-check
 * (erasure_completeness::stock_approval_truncates()) and the PHPUnit unit layer build such a list without
 * depending on how a database happens to order rows.
 *
 * It calls the same protected set_contextids() every core add path ends in, so the stored array is exactly
 * what core would store for the same input.
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forced_contextlist extends \core_privacy\local\request\contextlist {

    /**
     * Replace the stored ids with $contextids, through core's own set_contextids().
     *
     * @param array $contextids Context ids, duplicates allowed (they become key holes).
     * @return self
     */
    public function force(array $contextids): self {
        $this->set_contextids($contextids);
        return $this;
    }
}
