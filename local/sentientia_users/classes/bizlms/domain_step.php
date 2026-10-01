<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * local_domains -> local_sentientia_users_domain, ids kept (mapping doc section 10, gap G4). See lookup_step.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class domain_step extends lookup_step {

    /** The only table the step writes. */
    public const TARGET = 'local_sentientia_users_domain';

    public function key(): string {
        return 'users.domains';
    }

    public function sourcetable(): string {
        return 'local_domains';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    /**
     * user.open_domainid holds the id of the domain (a CHAR column, written as the number), and
     * local_positions.domain holds it too.
     *
     * @return array
     */
    public function external_refs(): array {
        return [['user', 'open_domainid']];
    }

    protected function fields(\stdClass $row, context $ctx): array {
        return [
            'name' => $this->text($ctx, $row->name ?? null, 'name'),
            'code' => $this->text($ctx, $row->code ?? null, 'code'),
            'costcenterid' => clean::id($row->costcenter ?? null),
        ];
    }
}
