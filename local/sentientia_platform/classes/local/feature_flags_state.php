<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\local;

defined('MOODLE_INTERNAL') || die();

/**
 * What \local_sentientia_platform\feature_flags keeps between lookups: the
 * merged registry and the snapshot of the override table.
 *
 * Only feature_flags reads or writes these fields; nothing else should.
 * They were private statics of feature_flags until 2026-10-09. feature_flags
 * now fetches one instance of this class from the core DI container
 * (\core\di::get(), which returns the same instance until the container is
 * reset), because the container is the one piece of process state that core
 * resets after every PHPUnit test: phpunit_util::reset_all_data() ends with
 * \core\di::reset_container() (Moodle 4.4+). A static is never reset, so a
 * flag one test switched ON stayed ON in the next test whatever the database
 * said, unless that test called feature_flags::invalidate_caches() first.
 *
 * Outside the test frameworks (PHPUnit, Behat) nothing in core resets the
 * container: it is built once and kept for the life of the process, so this
 * object lives exactly as long as the statics did, and the SNAPSHOT_TTL rules
 * in the feature_flags docblock are unchanged.
 *
 * @package local_sentientia_platform
 */
final class feature_flags_state {

    /** @var array<string, array{default: bool, description: string}>|null Merged registry, null until first built. */
    public ?array $registry = null;

    /**
     * Override snapshot, keyed [flag_key][customer_id][tenant_id] => bool;
     * null until the first lookup (or after invalidate_caches()).
     *
     * @var array<string, array<int, array<int, bool>>>|null
     */
    public ?array $overrides = null;

    /** @var int \core\clock time at which $overrides was read. */
    public int $overridesloadedat = 0;
}
