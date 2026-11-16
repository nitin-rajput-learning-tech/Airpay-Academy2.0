<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * What erasure_completeness keeps between the discovery and the delete (or export) of one data request.
 *
 * tool_dataprivacy discovers (process_data_request_task.php:93) and deletes (:151) in one execute() call, and
 * the user-expiry path does the same (expired_contexts_manager.php:518 then :528). Keeping the plan from
 * discovery lets the delete act on exactly the contexts the stock approval dropped, instead of discovering a
 * third time. erasure_completeness fetches this object from the core DI container (\core\di::get()), the same
 * pattern as local_sentientia_platform\local\feature_flags_state: the container is built once per process and
 * reset by PHPUnit after every test, so no plan, self-check answer or test seam can leak from one test into
 * the next.
 *
 * Only erasure_completeness reads or writes these fields.
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erasure_plan_store {

    /** Seconds a plan stays usable after discovery. Older plans are recomputed. */
    public const TTL = 600;

    /** @var array<int, erasure_plan> The last plan per subject userid. Discovery overwrites, delete/export consume. */
    public array $plans = [];

    /** @var bool|null Cached answer of erasure_completeness::stock_approval_truncates(), null until asked. */
    public ?bool $stocktruncates = null;

    /**
     * @var callable|null PHPUnit-only seam: fn(string $component, string $method, array $params, callable $real)
     *     wraps every component call erasure_completeness makes. Ignored unless PHPUNIT_TEST.
     */
    public $testcallback = null;
}
