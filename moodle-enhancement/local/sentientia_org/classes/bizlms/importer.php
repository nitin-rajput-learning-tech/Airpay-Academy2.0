<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\file_rehome;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The org feature: local_costcenter -> local_sentientia_org (ADR-032, mapping doc section 3).
 *
 * The organisation tree is what every other feature resolves its tenant path against, so this is the first
 * importer of a full run and the only one that depends on nothing. It keeps each organisation's BizLMS id (a
 * PRESERVE step, see costcenter_step), copies the logo files in finalise(), and leaves local_costcenter where it
 * is: Sentientia readers use it in place (category link, org_legacy_source), and ADR-032 keeps every BizLMS
 * table as the archive.
 *
 * Nothing here sends a message, fires an event, enrols or assigns anyone. The capability copy that the retired
 * migrate_all.php did is not part of this import (ADR-032, "Capabilities").
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements framework_importer {

    /** Feature key. The framework names this one registry::TENANT_OWNER. */
    public const FEATURE = 'org';

    /** Owning plugin. */
    public const COMPONENT = 'local_sentientia_org';

    /**
     * Plugin version that ships this importer together with the removal of the BizLMS capability fallbacks
     * from accesslib (ADR-032 gate 3). The registry refuses to run the importer below it, so the two cannot
     * be deployed apart. The feature needs no schema addition.
     */
    public const REQUIRES_VERSION = 2026093001;

    /** The owner's choice about the columns this import does not copy. */
    public const DECISION_UNMAPPED = 'org.unmapped_columns';

    /** Filearea and component the logo files are copied from. */
    private const LEGACY_LOGO_COMPONENT = 'local_costcenter';

    /** @var string */
    private const LEGACY_LOGO_AREA = 'costcenter_logo';

    /** @var string */
    private const NEW_LOGO_AREA = 'org_logo';

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return self::COMPONENT;
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * @return string[]
     */
    public function depends(): array {
        return [];
    }

    /**
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            org_source::TABLE => new source_spec(
                org_source::TABLE,
                true,
                ['visible' => ['0' => 'hidden', '1' => 'shown']],
                // Added by BizLMS upgrade steps or left out of older installs; the importer copes with their absence.
                ['description', 'depth', 'sortorder', 'theme', 'costcenter_logo', 'brand_color', 'button_color', 'hover_color']
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        // local_costcenter_permissions belongs to org_roles, local_coursedetails to course_lookups.
        return [];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [costcenter_step::TARGET];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        // None. The logo copy is file storage, which no importer declares (see finalise()).
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [costcenter_step::TARGET => 'path'];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            // A row with no usable path: BizLMS costcentersettings.php can insert one that holds only multipleorg.
            new reason('not_org_row', false, false),
            // A path whose first segment is not a registered tenant. The rows under it are skipped too.
            new reason('invalid_tenant_root', false, true),
            // A visible value other than 0 and 1, which an owner let through the enum check.
            new reason('unmapped_enum', false, true),
        ];
    }

    /**
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision(self::DECISION_UNMAPPED,
                'multipleorg, childpermission and shell are not copied: nothing in Sentientia reads them, and they '
                . 'stay in local_costcenter', true, null, ['not_copied']),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    /**
     * @return array<\local_sentientia_platform\bizlms\step|\local_sentientia_platform\bizlms\recompute_step>
     */
    public function steps(): array {
        return [new costcenter_step()];
    }

    /**
     * Read-only. Counts and warns about the shape of the table; the rows that cannot be imported are decided
     * one by one by the step and reported with a reason.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();

        try {
            $choice = $ctx->decision(self::DECISION_UNMAPPED);
        } catch (blocked $e) {
            // The runner has already recorded the missing or unfinished decision as a blocker.
            return $pf;
        }
        if ($choice !== 'not_copied') {
            $pf->block('decision_not_implemented:' . self::DECISION_UNMAPPED);
        }

        $rows = org_source::rows($ctx);
        $pf->count('org_rows', count($rows));
        foreach (org_source::anomalies($rows) as $code => $n) {
            $pf->count('anomaly:' . $code, $n);
            $pf->warn($code . ':' . $n);
        }

        // The framework's tenant check reads EVERY row of the target table after the load, native ones too. A row
        // already there whose path is not a valid tenant path (and that is not one of the ids this import writes)
        // would fail that check after all the work; say so now.
        $invalid = $this->native_rows_with_an_invalid_path($rows);
        if ($invalid) {
            $pf->block('native_org_invalid_path:' . count($invalid) . ' ids=' . implode(',', array_slice($invalid, 0, 20)));
        }

        // A logo item id with no file behind it cannot be copied. The org keeps its item id and shows no logo.
        $missing = 0;
        $logos = 0;
        if ($ctx->legacy->has_column(org_source::TABLE, 'costcenter_logo')) {
            $after = 0;
            do {
                $page = $ctx->legacy->page(org_source::TABLE, $after, 2000, ['id', 'costcenter_logo']);
                foreach ($page as $id => $row) {
                    $after = (int) $id;
                    if ((int) ($row->costcenter_logo ?? 0) > 0) {
                        $logos++;
                        if (!$DB->record_exists_select('files',
                                "component = :c AND filearea = :a AND itemid = :i AND filename <> '.'",
                                ['c' => self::LEGACY_LOGO_COMPONENT, 'a' => self::LEGACY_LOGO_AREA,
                                    'i' => (int) $row->costcenter_logo])) {
                            $missing++;
                        }
                    }
                }
            } while (count($page) === 2000);
        }
        $pf->count('org_logos', $logos);
        if ($missing > 0) {
            $pf->warn('logo_file_missing:' . $missing);
        }
        return $pf;
    }

    /**
     * Rows of the target table that the import does not write and whose path would fail the tenant check.
     *
     * @param array<int, \stdClass> $source The rows of local_costcenter by id; a target id in it is the import's own
     *        (an adoptable copy or a collision, which the framework handles).
     * @return int[]
     */
    private function native_rows_with_an_invalid_path(array $source): array {
        global $DB;
        if (!$DB->get_manager()->table_exists(costcenter_step::TARGET)) {
            return [];
        }
        $invalid = [];
        $after = 0;
        do {
            $page = $DB->get_records_select(costcenter_step::TARGET, 'id > :after AND path IS NOT NULL',
                ['after' => $after], 'id ASC', 'id, path', 0, 2000);
            foreach ($page as $row) {
                $after = (int) $row->id;
                if (!isset($source[$after]) && !org_source::path_is_valid((string) $row->path)) {
                    $invalid[] = $after;
                }
            }
        } while (count($page) === 2000);
        return $invalid;
    }

    /**
     * Read-only, after the load step. Checks what outlives a normal admin edit: every organisation the import
     * mapped is still at its BizLMS id, and (for a row nobody has changed since the import) still has the path,
     * parent and sort order the source gives it. The tenant paths themselves are checked by the framework.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        if ($ctx->dryrun) {
            // A dry run writes no organisation, so there is nothing to compare.
            return [];
        }
        $source = org_source::rows($ctx);
        $orders = org_source::sort_orders($source);
        $failures = [];
        $bad = 0;
        $after = 0;
        do {
            $page = $DB->get_records_sql(
                "SELECT m.id AS mapid, m.sourceid, m.targetid, m.timecreated AS mappedat,
                        o.id AS orgid, o.path, o.parentid, o.sortorder, o.timemodified
                   FROM {local_sentientia_legacymap} m
              LEFT JOIN {local_sentientia_org} o ON o.id = m.targetid
                  WHERE m.feature = :feature AND m.sourcetable = :source AND m.subkey = ''
                        AND m.outcome IN ('imported', 'adopted') AND m.id > :after
               ORDER BY m.id",
                ['feature' => self::FEATURE, 'source' => org_source::TABLE, 'after' => $after], 0, 500);
            foreach ($page as $row) {
                $after = (int) $row->mapid;
                $problem = $this->compare($row, $source, $orders);
                if ($problem !== null) {
                    $bad++;
                    if (count($failures) < 25) {
                        $failures[] = $problem . ':' . (int) $row->sourceid;
                    }
                }
            }
        } while (count($page) === 500);
        if ($bad > count($failures)) {
            $failures[] = 'org_more_failures:' . ($bad - count($failures));
        }
        return $failures;
    }

    /**
     * One mapped organisation against the source.
     *
     * @param \stdClass $row Map row joined to the organisation it points at.
     * @param array<int, \stdClass> $source
     * @param array<int, int> $orders
     * @return string|null Failure code, or null when the row is fine.
     */
    private function compare(\stdClass $row, array $source, array $orders): ?string {
        if ($row->orgid === null) {
            return 'org_missing';
        }
        if ((int) $row->sourceid !== (int) $row->targetid) {
            return 'org_id_moved';
        }
        if ((int) $row->timemodified > (int) $row->mappedat) {
            // An admin has edited it since; the admin's values win and are not the importer's to check.
            return null;
        }
        $src = $source[(int) $row->sourceid] ?? null;
        if ($src === null) {
            return 'org_source_gone';
        }
        if ((string) $row->path !== (string) org_source::path_of($src)) {
            return 'org_path_differs';
        }
        if ((int) $row->parentid !== (int) ($src->parentid ?? 0)) {
            return 'org_parent_differs';
        }
        if ((int) $row->sortorder !== ($orders[(int) $src->id] ?? org_source::SORT_STEP)) {
            return 'org_sortorder_differs';
        }
        return null;
    }

    /**
     * Outside any transaction and idempotent. The runner has already reset the sequence of local_sentientia_org.
     *
     * Copies each logo file into the system context under this plugin's own component and file area, with the
     * item id the organisation already carries, so branding_manager serves it and lib.php's pluginfile callback
     * can find it. BizLMS kept the file in the context of the organisation's course category and served it from
     * local_costcenter, whose code is gone on 5.2. The originals stay where they are (the legacy archive is never
     * altered) and a file already at the target is left alone, so a second run copies nothing.
     *
     * The copy is a write to the files table that is declared nowhere: registry::CORE_WRITES_ALLOWED has no files
     * entry and the side-effect tripwire does not watch files. --purge-feature=org therefore leaves the copied
     * logos behind; that is harmless, because a re-import finds them and copies nothing.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
        global $DB;
        $systemcontextid = (int) \context_system::instance()->id;

        $orgs = $DB->get_records_sql(
            "SELECT o.id, o.org_logo
               FROM {local_sentientia_org} o
               JOIN {local_sentientia_legacymap} m
                 ON m.targetid = o.id AND m.targettable = :target AND m.feature = :feature AND m.subkey = ''
                    AND m.outcome IN ('imported', 'adopted')
              WHERE o.org_logo IS NOT NULL AND o.org_logo > 0",
            ['target' => costcenter_step::TARGET, 'feature' => self::FEATURE]);

        $done = [];
        foreach ($orgs as $org) {
            $itemid = (int) $org->org_logo;
            if (isset($done[$itemid])) {
                continue;
            }
            $done[$itemid] = true;
            $contexts = $DB->get_records_sql(
                "SELECT MIN(f.id) AS k, f.contextid
                   FROM {files} f
                  WHERE f.component = :c AND f.filearea = :a AND f.itemid = :i AND f.filename <> '.'
               GROUP BY f.contextid",
                ['c' => self::LEGACY_LOGO_COMPONENT, 'a' => self::LEGACY_LOGO_AREA, 'i' => $itemid]);
            foreach ($contexts as $from) {
                file_rehome::copy_area(
                    ['contextid' => (int) $from->contextid, 'component' => self::LEGACY_LOGO_COMPONENT,
                        'filearea' => self::LEGACY_LOGO_AREA, 'itemid' => $itemid],
                    ['contextid' => $systemcontextid, 'component' => self::COMPONENT,
                        'filearea' => self::NEW_LOGO_AREA, 'itemid' => $itemid]);
            }
        }
    }
}
