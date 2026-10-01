<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_request_comments -> folded into decision_note (mapping doc, section 19; decision request.comments).
 *
 * The table is expected to be empty. Where it is not, the comment thread of a request is already in the
 * decision_note of the imported request row: records_step builds it, oldest first, as
 * "[date] <full name>: text". This step only settles each comment row in the accounting: folded into the
 * request it belongs to, or skipped and reported when that request is not there. The note is built once, from
 * the source, rather than appended to an imported row afterwards, so a re-run cannot double it.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class comments_step extends request_step {

    public function key(): string {
        return 'request.comments';
    }

    public function sourcetable(): string {
        return legacy_request::SOURCE_COMMENTS;
    }

    /**
     * This feature's own request map: the records step has finished by now.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [[legacy_request::SOURCE_RECORDS, '']];
    }

    public function transform(array $rows, context $ctx): array {
        // Reads request.comments and the other fixed decisions, and so blocks on a value it cannot carry out.
        $this->settings($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $requestid = legacy_request::comment_request_id($row->instanceid ?? '');
            if ($requestid <= 0) {
                $out[] = outcome::skip($id, 'orphan_request', 'instance_not_a_request');
                continue;
            }
            $entry = $ctx->map->entry(legacy_request::SOURCE_RECORDS, $requestid);
            if ($entry === null) {
                $out[] = outcome::skip($id, 'orphan_request', 'request_not_found');
            } else if ($entry['targetid'] === null) {
                $out[] = outcome::skip($id, 'orphan_request', 'request_not_imported');
            } else {
                $out[] = outcome::fold($id, legacy_request::TARGET, (int) $entry['targetid'], 'folded_into_note');
            }
        }
        return $out;
    }
}
