<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal SCIM filter parser (RFC 7644 §3.4.2.2) — the subset real IdPs send
 * on the Users resource: `<attr> eq "<value>"` for userName, externalId, id,
 * emails.value / emails[type eq "work"].value. Anything else is rejected with
 * 400 invalidFilter (advertised via ServiceProviderConfig).
 *
 * @package local_sentientia_api
 */
class filter {

    /** @var array<string,string> SCIM attribute path => internal key. */
    private const ATTRS = [
        'username'                       => 'username',
        'externalid'                     => 'externalid',
        'id'                             => 'id',
        'emails.value'                   => 'email',
        'emails[type eq "work"].value'   => 'email',
        'emails[primary eq true].value'  => 'email',
    ];

    /**
     * @param string $expr Raw filter string
     * @return array{attr:string,value:string}|null null when $expr is empty
     * @throws scim_exception
     */
    public static function parse(string $expr): ?array {
        $expr = trim($expr);
        if ($expr === '') {
            return null;
        }
        // <path> eq "<value>"   (value may be unquoted for id).
        if (!preg_match('/^(?<attr>[A-Za-z][A-Za-z0-9_.:\[\]" =]*?)\s+(?<op>eq)\s+(?<val>"(?:[^"\\\\]|\\\\.)*"|[A-Za-z0-9_@.\-]+)$/i', $expr, $m)) {
            throw new scim_exception(400, 'Unsupported filter; only "<attribute> eq <value>" is supported.', 'invalidFilter');
        }
        $attr = strtolower(trim($m['attr']));
        // Strip the core-schema URN prefix if present.
        $attr = preg_replace('/^urn:ietf:params:scim:schemas:core:2\.0:user:/', '', $attr);
        if (!isset(self::ATTRS[$attr])) {
            throw new scim_exception(400, 'Filtering on "' . $m['attr'] . '" is not supported.', 'invalidFilter');
        }
        $val = $m['val'];
        if ($val[0] === '"') {
            $val = stripcslashes(substr($val, 1, -1));
        }
        return ['attr' => self::ATTRS[$attr], 'value' => $val];
    }
}
