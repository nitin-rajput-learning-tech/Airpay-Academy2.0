<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\compat;

defined('MOODLE_INTERNAL') || die();

/**
 * WF-016 anti-corruption shim — alias target for the retired BizLMS
 * `\local_costcenter\lib` class.
 *
 * The BizLMS vendor blocks we kept (learnerscript, userdashboard,
 * reportdashboard, achievements, my_event_calendar, masterinfo,
 * quick_navigation) still reference `\local_costcenter\lib::get_userdate()`
 * at ~20 call sites. The local_costcenter plugin does not exist on any
 * Sentientia tree, so each call is a class-not-found fatal the moment its
 * path executes. Rather than patching every vendor file (merge pain on
 * vendor updates), hook_callbacks::after_config() aliases the old name
 * onto this class every request.
 *
 * get_userdate() is a byte-faithful port of the original BizLMS
 * implementation (Moodle-Backup/01-production-codebase/html/local/
 * costcenter/classes/lib.php) — a date()-style → strftime-style format
 * translator delegating to core userdate(), preserving the caller's
 * timezone behaviour.
 *
 * @package local_sentientia_org
 */
class bizlms_lib {

    /**
     * Format a timestamp using a date()-style format string, timezone-aware.
     *
     * @param string $format date()-style format (e.g. 'd-M-Y H:i')
     * @param int|null $timestamp unix timestamp; null = now
     * @param int|float|string $timezone user timezone (99 = user default)
     * @param bool $fixday remove leading zero from day
     * @param bool $fixhour remove leading zero from hour
     * @return string formatted date
     */
    public static function get_userdate($format, $timestamp = null, $timezone = 99,
                                        $fixday = false, $fixhour = true) {
        $formatidentifiers = ['d', 'm', 'y', 'j', 'D', 'M', 'Y', 'H', 'i', 's',
            'a', 'A', 'G', 'F', 'g', 'h'];
        $strftimeformatidentifiers = ['%d', '%m', '%y', '%e', '%D', '%b', '%Y',
            '%H', '%M', '%S', '%P', '%p', '%k', '%B', '%l', '%I'];
        foreach ($formatidentifiers as $key => $identifier) {
            $format = str_replace($identifier, $strftimeformatidentifiers[$key], $format);
        }
        if (is_null($timestamp)) {
            $timestamp = time();
        }
        return userdate($timestamp, $format, $timezone, $fixday, $fixhour);
    }
}
