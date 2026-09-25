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
