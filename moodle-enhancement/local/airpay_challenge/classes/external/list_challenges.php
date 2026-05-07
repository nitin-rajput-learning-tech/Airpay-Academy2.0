<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_challenge\challenge_engine;

class list_challenges extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT,     'Search', VALUE_DEFAULT, ''),
            'status'  => new external_value(PARAM_ALPHAEXT, 'Status filter (all|draft|active|archived)', VALUE_DEFAULT, 'active'),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort col', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA,    'asc|desc', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT,      'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      'Reserved JSON', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $status = 'active',
                                    string $sort = 'timecreated', string $sortdir = 'desc',
                                    int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'status', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_challenge:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_airpay_challenge');
        }

        // Tenant scoping: callers without :viewall see only their tenant + global.
        $tenant = is_siteadmin() || has_capability('local/airpay_challenge:viewall', $context)
            ? 0
            : challenge_engine::tenant_from_path($USER->open_path ?? '');

        $result = challenge_engine::list_challenges(
            $tenant, $params['status'], $params['search'], (int) $USER->id,
            (int) $params['page'], (int) $params['perpage']);

        $can_manage = has_capability('local/airpay_challenge:manage', $context);
        $can_join   = has_capability('local/airpay_challenge:participate', $context);
        $viewbase   = new \moodle_url('/local/airpay_challenge/view.php');
        $lbbase     = new \moodle_url('/local/airpay_challenge/leaderboard.php');

        foreach ($result['rows'] as &$row) {
            $vurl = (clone $viewbase);
            $vurl->params(['id' => $row['id'], 'tab' => 'overview']);
            $row['name'] = '<a href="' . s($vurl->out(false)) . '">' . $row['name'] . '</a>';

            $actions = [];
            // Join / Leave (active challenges only).
            if ($can_join && $row['status'] === challenge_engine::STATUS_ACTIVE) {
                if ($row['joined']) {
                    $actions[] = '<button type="button" data-action="leave-challenge" '
                        . 'data-challengeid="' . (int) $row['id'] . '" '
                        . 'class="btn btn-sm btn-outline-secondary">'
                        . s(get_string('btn_leave', 'local_airpay_challenge')) . '</button>';
                } else {
                    $actions[] = '<button type="button" data-action="join-challenge" '
                        . 'data-challengeid="' . (int) $row['id'] . '" '
                        . 'data-name="' . s($row['shortname']) . '" '
                        . 'class="btn btn-sm btn-primary">'
                        . s(get_string('btn_join', 'local_airpay_challenge')) . '</button>';
                }
            }
            // Leaderboard link (always visible to those with :view).
            $lburl = (clone $lbbase);
            $lburl->params(['challengeid' => $row['id']]);
            $actions[] = '<a href="' . s($lburl->out(false)) . '" '
                . 'class="btn btn-sm btn-link p-1" '
                . 'title="' . s(get_string('btn_leaderboard', 'local_airpay_challenge')) . '">'
                . '<i class="fa fa-trophy"></i></a>';
            // Edit / delete (manager only).
            if ($can_manage) {
                $actions[] = '<button type="button" data-action="edit-challenge" '
                    . 'data-challengeid="' . (int) $row['id'] . '" '
                    . 'class="btn btn-sm btn-link text-muted p-1" title="Edit">'
                    . '<i class="fa fa-pencil"></i></button>';
                $actions[] = '<button type="button" data-action="delete-challenge" '
                    . 'data-challengeid="' . (int) $row['id'] . '" '
                    . 'data-name="' . s($row['shortname']) . '" '
                    . 'class="btn btn-sm btn-link text-danger p-1" title="Delete">'
                    . '<i class="fa fa-trash"></i></button>';
            }
            $row['actions'] = implode(' ', $actions);
            $row['target_label'] = get_string('target_x_completions', 'local_airpay_challenge', (int) $row['targetcount']);
            $row['mystatus_label'] = $row['mystatus'] !== ''
                ? get_string('attempt_' . $row['mystatus'], 'local_airpay_challenge')
                : '';
        }
        unset($row);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'ID'),
                    'name'         => new external_value(PARAM_RAW, 'Name HTML (with link)'),
                    'shortname'    => new external_value(PARAM_TEXT, 'Shortname'),
                    'description'  => new external_value(PARAM_RAW, 'Description HTML'),
                    'type'         => new external_value(PARAM_TEXT, 'Type'),
                    'targetcount'  => new external_value(PARAM_INT, 'Target'),
                    'target_label' => new external_value(PARAM_RAW, 'Target localised'),
                    'pointsreward' => new external_value(PARAM_INT, 'Points'),
                    'status'       => new external_value(PARAM_INT, 'Status'),
                    'statuslabel'  => new external_value(PARAM_TEXT, 'Status label'),
                    'statuscss'    => new external_value(PARAM_TEXT, 'Status CSS'),
                    'startdate'    => new external_value(PARAM_INT, 'Start date'),
                    'enddate'      => new external_value(PARAM_INT, 'End date'),
                    'participants' => new external_value(PARAM_INT, 'Participants'),
                    'completed'    => new external_value(PARAM_INT, 'Completed'),
                    'mystatus'     => new external_value(PARAM_TEXT, 'My attempt status'),
                    'mystatus_label' => new external_value(PARAM_TEXT, 'Localised'),
                    'myprogress'   => new external_value(PARAM_INT, 'My progress'),
                    'mytarget'     => new external_value(PARAM_INT, 'My target'),
                    'mypoints'     => new external_value(PARAM_INT, 'My points'),
                    'joined'       => new external_value(PARAM_BOOL, 'Joined'),
                    'actions'      => new external_value(PARAM_RAW, 'Actions HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
