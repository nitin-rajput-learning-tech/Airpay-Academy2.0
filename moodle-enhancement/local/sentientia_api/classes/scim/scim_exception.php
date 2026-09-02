<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * A SCIM protocol error: HTTP status + optional scimType (RFC 7644 §3.12).
 *
 * @package local_sentientia_api
 */
class scim_exception extends \Exception {

    /** @var int */
    public int $status;

    /** @var string|null e.g. invalidFilter, uniqueness, invalidValue, noTarget */
    public ?string $scimtype;

    public function __construct(int $status, string $detail, ?string $scimtype = null) {
        parent::__construct($detail, $status);
        $this->status = $status;
        $this->scimtype = $scimtype;
    }
}
