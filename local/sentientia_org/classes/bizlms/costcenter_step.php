<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_costcenter -> local_sentientia_org (ADR-032, mapping doc section 3). PRESERVE: the organisation keeps
 * its BizLMS id.
 *
 * Why the id is kept. Rows the import does not rewrite store it: every user.open_path, every course.open_path,
 * every costcenterid of a Sentientia table, and the paths built from them ('/1/5/12'). A new id would point all
 * of them at the wrong organisation.
 *
 * What is copied, column by column (the mapping doc has the reasons):
 *
 *   id, fullname, shortname, description, parentid, visible, path, depth, sortorder,
 *   costcenter_logo -> org_logo, brand_color, button_color, hover_color, theme -> theme_scheme,
 *   timecreated, timemodified.
 *
 * What is not copied: category (the category link is read in place), multipleorg, childpermission, shell and
 * usermodified (decision org.unmapped_columns = not_copied: nothing in Sentientia reads them, and they stay in
 * local_costcenter). The Sentientia columns with no BizLMS source (favicon, footer text, e-mail identity,
 * hero text, custom CSS) are left NULL.
 *
 * Tenant. This step does NOT call tenant_resolver::resolve(). That method checks a candidate path against the
 * organisation table, and this step is what fills that table. On a resume the first batch is already there, so
 * resolve() would find a later row missing and "walk up" to its parent's path, and the row would be written
 * under the wrong organisation. The path is the row's own: normalised, with a root that tenant::assert_valid()
 * accepts, else the row is skipped and reported.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class costcenter_step extends step {

    /** Step key. */
    public const KEY = 'org.costcenter';

    /** Target table. */
    public const TARGET = 'local_sentientia_org';

    /** @var array<int, int>|null Org id => sort order, worked out once from the whole source table. */
    private ?array $sortorders = null;

    public function key(): string {
        return self::KEY;
    }

    public function sourcetable(): string {
        return org_source::TABLE;
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Where the BizLMS organisation id lives in rows this import does not rewrite.
     *
     * @return array<array{0: string, 1: string, 2?: string}>
     */
    public function external_refs(): array {
        return [
            ['user', 'open_path'],
            ['course', 'open_path'],
            ['local_sentientia_org', 'parentid'],
            ['local_sentientia_org', 'path'],
        ];
    }

    /**
     * A row that the retired data_migration.php copied holds the same shortname and path as the source. Both
     * were copied verbatim, so the signature compares the stored text.
     *
     * @return array<int|string, string>
     */
    public function adopt_signature(): array {
        return ['shortname', 'path'];
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'fullname', 'shortname', 'description', 'parentid', 'visible', 'path', 'depth', 'sortorder',
            'costcenter_logo', 'brand_color', 'button_color', 'hover_color', 'theme', 'timecreated', 'timemodified'];
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map_row($row, $ctx);
        }
        return $out;
    }

    /**
     * One source row to one outcome.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function map_row(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        $path = org_source::path_of($row);

        if ($path === null) {
            // BizLMS costcentersettings.php can insert a row that carries only multipleorg.
            $blank = trim((string) ($row->path ?? '')) === '';
            return outcome::skip($id, 'not_org_row', $blank ? 'no_path' : 'path_not_numeric');
        }
        if (!org_source::root_is_registered(org_source::root_of($path))) {
            return outcome::skip($id, 'invalid_tenant_root', 'root_not_registered');
        }
        $visible = (int) $row->visible;
        if ($visible !== 0 && $visible !== 1) {
            // The source_spec enum already stops a run on any other value; an owner who maps one in the decisions
            // file has told the framework to let it through, but this importer has no meaning for it.
            return outcome::skip($id, 'unmapped_enum', 'visible_not_0_or_1');
        }

        $fields = new \stdClass();
        $fields->fullname = $ctx->text->fit((string) ($row->fullname ?? ''), 254, 'fullname');
        $fields->shortname = $row->shortname === null ? null : $ctx->text->fit((string) $row->shortname, 100, 'shortname');
        $fields->description = $row->description ?? null;
        $fields->parentid = (int) ($row->parentid ?? 0);
        $fields->path = $path;
        [$depth, $depthwarning] = org_source::depth_of($row, $path);
        $fields->depth = $depth;
        $fields->visible = $visible;
        $logo = (int) ($row->costcenter_logo ?? 0);
        $fields->org_logo = $logo > 0 ? $logo : null;
        foreach (['brand_color', 'button_color', 'hover_color'] as $column) {
            $fields->{$column} = isset($row->{$column}) ? $ctx->text->fit((string) $row->{$column}, 20, $column) : null;
        }
        $fields->theme_scheme = isset($row->theme) ? $ctx->text->fit((string) $row->theme, 50, 'theme_scheme') : null;
        $fields->sortorder = $this->sort_order_of($id, $ctx);
        // The source timestamps, as they are (mapping doc rule R2): data_migration.php wrote "now".
        $fields->timecreated = (int) $row->timecreated;
        $fields->timemodified = (int) $row->timemodified;

        // Exact only when the stored text already is the normalised path; padding or a doubled slash is 'normalised'.
        $exact = (string) $row->path === $path;
        $outcome = outcome::insert($id, self::TARGET, $fields)->tenant_method($exact ? 'exact' : 'normalised');
        if (!$exact) {
            $outcome->warn('path_normalised');
        }
        if ($depthwarning !== null) {
            $outcome->warn($depthwarning);
        }
        if (trim((string) ($row->fullname ?? '')) === '') {
            $outcome->warn('empty_fullname');
        }
        return $outcome;
    }

    /**
     * The sort order of an organisation. Ranking needs every sibling, so it is worked out once from the whole
     * source table (tens of rows) and kept for the life of this step object, which the runner makes once per
     * run and every batch reuses.
     *
     * @param int $id
     * @param context $ctx
     * @return int
     */
    private function sort_order_of(int $id, context $ctx): int {
        if ($this->sortorders === null) {
            $this->sortorders = org_source::sort_orders(org_source::rows($ctx));
        }
        return $this->sortorders[$id] ?? org_source::SORT_STEP;
    }
}
