<?php
/**
 * Upgrade helpers for local_sentientia_emails.
 *
 * Kept out of upgrade.php so PHPUnit can exercise them directly
 * (tests/override_audit_test.php) - an upgrade step cannot be re-run in a test.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * ADR-031 follow-up: was $authorid entitled to write an override for $tenantid?
 *
 * The same rule tenant_scope::require_can_write_tenant() now enforces on every
 * save: a cross-tenant author (site admin, or a holder of
 * local/sentientia_platform:crosstenant) may write any tenant, including the
 * global override; anyone else only their own tenant root. An author who no
 * longer exists (usermodified = 0, or the user row is gone) or who has no
 * tenant cannot be shown to be entitled, so they are not.
 *
 * @param int $authorid local_sentientia_email_overrides.usermodified
 * @param int $tenantid local_sentientia_email_overrides.tenant_id
 * @return bool
 */
function local_sentientia_emails_override_author_entitled(int $authorid, int $tenantid): bool {
    global $DB;
    if ($authorid <= 0) {
        return false;
    }
    if (tenant::is_cross_tenant($authorid)) {
        return true;
    }
    if ($tenantid <= 0) {
        return false;
    }
    $author = $DB->get_record('user', ['id' => $authorid], 'id, open_path');
    return $author && tenant::root_for_user($author) === $tenantid;
}

/**
 * The tenant root of an override's author, for the audit trace; 0 if none.
 *
 * @param int $authorid
 * @return int
 */
function local_sentientia_emails_override_author_root(int $authorid): int {
    global $DB;
    $author = $authorid > 0 ? $DB->get_record('user', ['id' => $authorid], 'id, open_path') : false;
    return $author ? tenant::root_for_user($author) : 0;
}

/**
 * ADR-031 follow-up: switch off active TENANT overrides whose author was not
 * entitled to write them.
 *
 * Why this runs at deploy: until 2026-09-25 email_renderer::render() never
 * resolved the recipient's tenant (it read an 'open_path' key tenant_config
 * never returned), so only the global override (tenant_id 0) was ever
 * delivered and every tenant_id > 0 row sat inert. Those rows were also
 * writable by ANY manager-archetype tenant admin for ANY tenant: editor.php
 * offered Global/Airpay/Public/ZEEA to everyone and template_api::save_template
 * took any tenantid. ADR-031 makes the renderer deliver tenant overrides, so
 * without this step an override a /1 admin wrote for tenant 177 would start
 * reaching ZEEA learners the moment the code lands - the phishing/link
 * injection of sweep hit 23, switched on by its own fix.
 *
 * Global overrides are NOT changed here: they were already being delivered
 * before ADR-031, so switching them off would itself change what learners
 * receive. They are listed by local_sentientia_emails_global_overrides_for_review().
 *
 * Idempotent: a second run finds nothing to do. Rows are switched off, not
 * deleted, so an entitled editor can review and re-save them.
 *
 * @return stdClass[] the rows switched off: id, template_key, tenant_id, usermodified, author_root
 */
function local_sentientia_emails_deactivate_unentitled_overrides(): array {
    global $DB;
    $rows = $DB->get_records_select('local_sentientia_email_overrides',
        'tenant_id > 0 AND is_active = 1', null, 'id ASC',
        'id, template_key, tenant_id, usermodified');
    $switchedoff = [];
    foreach ($rows as $row) {
        if (local_sentientia_emails_override_author_entitled((int) $row->usermodified, (int) $row->tenant_id)) {
            continue;
        }
        $DB->update_record('local_sentientia_email_overrides', (object) [
            'id'           => $row->id,
            'is_active'    => 0,
            'timemodified' => time(),
        ]);
        $row->author_root = local_sentientia_emails_override_author_root((int) $row->usermodified);
        $switchedoff[] = $row;
    }
    return $switchedoff;
}

/**
 * Active GLOBAL overrides written by someone who is not cross-tenant.
 *
 * Reported for review only (see above for why they are left active): each one
 * is content a single tenant admin chose for every tenant's learners.
 *
 * @return stdClass[] id, template_key, tenant_id, usermodified, author_root
 */
function local_sentientia_emails_global_overrides_for_review(): array {
    global $DB;
    $rows = $DB->get_records('local_sentientia_email_overrides',
        ['tenant_id' => 0, 'is_active' => 1], 'id ASC',
        'id, template_key, tenant_id, usermodified');
    $review = [];
    foreach ($rows as $row) {
        if (local_sentientia_emails_override_author_entitled((int) $row->usermodified, 0)) {
            continue;
        }
        $row->author_root = local_sentientia_emails_override_author_root((int) $row->usermodified);
        $review[] = $row;
    }
    return $review;
}

/**
 * The 2026092501 audit: switch off unentitled tenant overrides, list the
 * global ones for review, and keep a record of both that outlives the
 * upgrade output.
 *
 * Until 2026-09-25 the DEACTIVATED and REVIEW lines were only mtrace()d, so
 * after a web upgrade, or a CLI run nobody captured, nothing said which
 * overrides had been switched off or which global ones needed a look. They
 * are now also written to:
 *
 *  - the config changes log (Site administration > Reports > Config changes,
 *    /report/configlog/index.php), plugin local_sentientia_emails: one
 *    'adr031_override_deactivated' or 'adr031_override_review' entry per row,
 *    and one 'adr031_override_audit' summary entry;
 *  - the plugin setting local_sentientia_emails/adr031_override_audit, a JSON
 *    document {"recorded", "deactivated": [...], "review": [...]} (read it with
 *    admin/cli/cfg.php --component=local_sentientia_emails --name=adr031_override_audit).
 *
 * The persisted record names the override id, template key, tenant and the
 * author's tenant root, but not the author's user id: no privacy provider
 * covers a config value or the config log's text, and the override row
 * itself keeps usermodified (the step changes only is_active and
 * timemodified). The console lines still name usermodified, as before.
 *
 * @return string[] the lines for the upgrade step to mtrace()
 */
function local_sentientia_emails_run_override_audit(): array {
    $switchedoff = local_sentientia_emails_deactivate_unentitled_overrides();
    $review = local_sentientia_emails_global_overrides_for_review();
    return local_sentientia_emails_record_override_audit($switchedoff, $review);
}

/**
 * Persist an override audit (see local_sentientia_emails_run_override_audit())
 * and return its console lines.
 *
 * @param stdClass[] $switchedoff from local_sentientia_emails_deactivate_unentitled_overrides()
 * @param stdClass[] $review from local_sentientia_emails_global_overrides_for_review()
 * @return string[] the lines for the upgrade step to mtrace()
 */
function local_sentientia_emails_record_override_audit(array $switchedoff, array $review): array {
    $plugin = 'local_sentientia_emails';
    $entry = fn(stdClass $row): array => [
        'id'           => (int) $row->id,
        'template_key' => (string) $row->template_key,
        'tenant_id'    => (int) $row->tenant_id,
        'author_root'  => (int) $row->author_root,
    ];
    $lines = [];

    foreach ($switchedoff as $row) {
        $lines[] = sprintf('DEACTIVATED tenant override id=%d template_key=%s tenant_id=%d usermodified=%d '
            . '(author tenant root %d): author not entitled to that tenant.',
            $row->id, $row->template_key, $row->tenant_id, $row->usermodified, $row->author_root);
        add_to_config_log('adr031_override_deactivated', 'is_active=1',
            sprintf('is_active=0: tenant override id=%d template_key=%s tenant_id=%d, author tenant root %d '
                . '(not entitled to that tenant). Re-save it as an entitled editor to restore it.',
                $row->id, $row->template_key, $row->tenant_id, $row->author_root),
            $plugin);
    }
    $lines[] = sprintf('%d tenant override(s) deactivated for review.', count($switchedoff));

    foreach ($review as $row) {
        $lines[] = sprintf('REVIEW (left active) global override id=%d template_key=%s '
            . 'usermodified=%d (author tenant root %d): author is not cross-tenant.',
            $row->id, $row->template_key, $row->usermodified, $row->author_root);
        add_to_config_log('adr031_override_review', null,
            sprintf('left active: global override id=%d template_key=%s, author tenant root %d '
                . '(not cross-tenant). Every tenant\'s learners receive it; review its content.',
                $row->id, $row->template_key, $row->author_root),
            $plugin);
    }

    $summary = sprintf('%d tenant override(s) deactivated [ids: %s]; %d global override(s) to review [ids: %s].',
        count($switchedoff), implode(',', array_map(fn($r) => (int) $r->id, $switchedoff)) ?: '-',
        count($review), implode(',', array_map(fn($r) => (int) $r->id, $review)) ?: '-');
    add_to_config_log('adr031_override_audit', null, $summary, $plugin);
    set_config('adr031_override_audit', json_encode([
        'recorded'    => time(),
        'deactivated' => array_map($entry, array_values($switchedoff)),
        'review'      => array_map($entry, array_values($review)),
    ]), $plugin);
    $lines[] = 'Recorded in Site administration > Reports > Config changes (plugin ' . $plugin
        . ') and in the setting ' . $plugin . '/adr031_override_audit.';

    return $lines;
}
