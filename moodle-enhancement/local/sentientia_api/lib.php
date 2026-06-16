<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library functions for local_sentientia_api.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Convenience wrapper: is the public API feature flag on?
 *
 * Thin shim over the platform feature_flags resolver so callers (and the
 * router/endpoint files) don't repeat the class_exists guard. When the
 * platform plugin is absent (standalone install), the API is treated as
 * OFF — fail safe.
 *
 * @return bool
 */
function local_sentientia_api_is_enabled(): bool {
    if (!class_exists('\local_sentientia_platform\feature_flags')) {
        return false;
    }
    return \local_sentientia_platform\feature_flags::is_enabled('sentientia.api.enabled');
}

/**
 * Convenience wrapper: is the LTI feature flag on?
 *
 * @return bool
 */
function local_sentientia_api_lti_is_enabled(): bool {
    if (!class_exists('\local_sentientia_platform\feature_flags')) {
        return false;
    }
    return \local_sentientia_platform\feature_flags::is_enabled('sentientia.api.lti.enabled');
}
