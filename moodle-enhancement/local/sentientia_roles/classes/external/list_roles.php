<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_roles\role_manager;

/**
 * List roles (paginated, filterable) for the role-management table.
 *
 * @package local_sentientia_roles
 */
class list_roles extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'    => new external_value(PARAM_TEXT,     'Substring search', VALUE_DEFAULT, ''),
            'archetype' => new external_value(PARAM_ALPHAEXT, 'Archetype filter (or "all" / "custom")', VALUE_DEFAULT, 'all'),
            'sort'      => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'sortorder'),
            'sortdir'   => new external_value(PARAM_ALPHA,    'asc|desc', VALUE_DEFAULT, 'asc'),
            'page'      => new external_value(PARAM_INT,      'Page (0-based)', VALUE_DEFAULT, 0),
            'perpage'   => new external_value(PARAM_INT,      'Per-page', VALUE_DEFAULT, 25),
            'filters'   => new external_value(PARAM_RAW,      'Reserved JSON blob', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $archetype = 'all',
                                    string $sort = 'sortorder', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'archetype', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_roles:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_sentientia_roles');
        }

        $rows = role_manager::list_roles($params['search'], $params['archetype']);

        // Server-side sort (small list, simple compare).
        $allowed = ['name', 'shortname', 'archetype', 'capcount', 'assigncount', 'sortorder'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'sortorder';
        $dir = strtolower($params['sortdir']) === 'desc' ? -1 : 1;
        usort($rows, function ($a, $b) use ($sort, $dir) {
            $av = $a[$sort] ?? '';
            $bv = $b[$sort] ?? '';
            if (is_numeric($av) && is_numeric($bv)) return ($av <=> $bv) * $dir;
            return strcmp((string) $av, (string) $bv) * $dir;
        });

        $total = count($rows);
        $perpage = max(5, min(200, $params['perpage']));
        $page = max(0, $params['page']);
        $rows = array_slice($rows, $page * $perpage, $perpage);

        // Render datatable-friendly action HTML.
        $can_manage = has_capability('local/sentientia_roles:manage', $context);
        $can_audit  = has_capability('local/sentientia_roles:audit', $context);
        $viewbase   = new \moodle_url('/local/sentientia_roles/view.php');
        $auditbase  = new \moodle_url('/local/sentientia_roles/view.php');
        foreach ($rows as &$r) {
            $actions = [];
            $viewurl = (clone $viewbase);
            $viewurl->params(['id' => $r['id'], 'tab' => 'overview']);
            $actions[] = '<a href="' . s($viewurl->out(false)) . '" '
                . 'class="btn btn-sm btn-link text-muted p-1" '
                . 'title="' . s(get_string('btn_view_role', 'local_sentientia_roles')) . '">'
                . '<i class="fa fa-eye"></i></a>';
            if ($can_manage) {
                $editurl = (clone $viewbase);
                $editurl->params(['id' => $r['id'], 'tab' => 'capabilities']);
                $actions[] = '<a href="' . s($editurl->out(false)) . '" '
                    . 'class="btn btn-sm btn-link text-muted p-1" '
                    . 'title="' . s(get_string('btn_edit_caps', 'local_sentientia_roles')) . '">'
                    . '<i class="fa fa-pencil"></i></a>';
            }
            if ($can_audit) {
                $audurl = (clone $viewbase);
                $audurl->params(['id' => $r['id'], 'tab' => 'audit']);
                $actions[] = '<a href="' . s($audurl->out(false)) . '" '
                    . 'class="btn btn-sm btn-link text-muted p-1" '
                    . 'title="' . s(get_string('audit_filter_role', 'local_sentientia_roles')) . '">'
                    . '<i class="fa fa-history"></i></a>';
            }
            // Replace the plain name with a link for the table.
            $r['name'] = '<a href="' . s($viewurl->out(false)) . '">' . $r['name'] . '</a>';
            $r['archetype_label'] = $r['archetype'] !== '' ? $r['archetype']
                : '<em class="text-muted">' . s(get_string('ov_archetype_custom', 'local_sentientia_roles')) . '</em>';
            $r['actions'] = implode(' ', $actions);
        }
        unset($r);

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'perpage' => $perpage];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT,  'Role ID'),
                    'name'         => new external_value(PARAM_RAW,  'Role name (HTML, may contain link)'),
                    'shortname'    => new external_value(PARAM_TEXT, 'Shortname'),
                    'archetype'    => new external_value(PARAM_TEXT, 'Archetype (or "")'),
                    'archetype_label' => new external_value(PARAM_RAW, 'Archetype display HTML'),
                    'capcount'     => new external_value(PARAM_INT,  'Non-inherit capability count'),
                    'assigncount'  => new external_value(PARAM_INT,  'User assignment count'),
                    'sortorder'    => new external_value(PARAM_INT,  'Sort order'),
                    'description'  => new external_value(PARAM_RAW,  'Description HTML'),
                    'actions'      => new external_value(PARAM_RAW,  'Per-row action HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
