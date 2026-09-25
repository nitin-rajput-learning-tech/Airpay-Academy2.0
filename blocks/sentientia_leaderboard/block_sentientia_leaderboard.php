<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS — Real-time Leaderboard block.
 *
 * Renders one configured leaderboard inside any page that supports
 * blocks (dashboard, course pages, site index). The board is selected
 * via the block instance config; default is "first board visible to the
 * caller".
 *
 * The AMD client opens an SSE connection on render (via the realtime
 * flag) so the table refreshes in place as learners earn points.
 *
 * @package    block_sentientia_leaderboard
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_sentientia_leaderboard extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_sentientia_leaderboard');
    }

    public function applicable_formats(): array {
        return [
            'all'       => true,
            'my'        => true,
            'site-index' => true,
            'course-view' => true,
        ];
    }

    /** Block can be instantiated multiple times on one page. */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /** Block has an instance config form. */
    public function instance_config_print(): bool {
        return true;
    }

    /** Has settings for choosing the board to render. */
    public function has_config(): bool {
        return false;
    }

    public function get_content(): \stdClass {
        global $USER, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new \stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Master flag gate — render an info notice in the block when off.
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            if (!\local_sentientia_platform\feature_flags::is_enabled(
                    'sentientia.leaderboards.enabled')) {
                $this->content->text = $OUTPUT->notification(
                    get_string('feature_disabled',
                        'local_sentientia_leaderboard'),
                    \core\output\notification::NOTIFY_INFO);
                return $this->content;
            }
        }

        // Capability gate.
        $context = \context_system::instance();
        if (!has_capability('local/sentientia_leaderboard:view', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // Find the configured board id, or pick the first visible board.
        $boardid = isset($this->config->boardid)
            ? (int) $this->config->boardid : 0;

        // ADR-031: board scope comes from board_manager::list_for_viewer() /
        // viewer_can_see() (tenant::is_cross_tenant(); a viewer with no
        // tenant sees nothing), not from :viewall - which every tenant admin
        // held, so an unconfigured block auto-picked any tenant's board.
        if ($boardid <= 0) {
            $boards = \local_sentientia_leaderboard\board_manager::list_for_viewer();
            if (empty($boards)) {
                $this->content->text = $OUTPUT->notification(
                    get_string('block_none',
                        'local_sentientia_leaderboard'),
                    \core\output\notification::NOTIFY_INFO);
                return $this->content;
            }
            $boardid = (int) $boards[0]->id;
        }

        $board = \local_sentientia_leaderboard\board_manager::get($boardid);
        if (!$board) {
            $this->content->text = '';
            return $this->content;
        }

        // Tenant gate - also for a configured board: a block pinned to
        // another tenant's board renders nothing for this viewer.
        if (!\local_sentientia_leaderboard\board_manager::viewer_can_see($board)) {
            $this->content->text = '';
            return $this->content;
        }

        // Opt-outs are honoured for everyone but a site admin (ADR-031).
        $result = \local_sentientia_leaderboard\ranking_engine::read_top(
            $boardid, 5, \local_sentientia_leaderboard\board_manager::viewer_bypasses_optout());
        $my_rank = \local_sentientia_leaderboard\ranking_engine::read_my_rank(
            $boardid, (int) $USER->id);

        $data = [
            'boardid'         => $boardid,
            'name'            => format_string($board->name),
            'type'            => $board->type,
            'type_label'      => get_string('type_' . $board->type,
                'local_sentientia_leaderboard'),
            'last_recomputed_str' => (int) $board->last_recomputed > 0
                ? userdate((int) $board->last_recomputed) : '-',
            'rows'            => $result['rows'],
            'has_rows'        => !empty($result['rows']),
            'total'           => $result['total'],
            'optout_total'    => $result['optout_total'],
            'my_rank'         => $my_rank ? (int) $my_rank['rank'] : 0,
            'my_points'       => $my_rank ? (int) $my_rank['points'] : 0,
            'my_optout'       => \local_sentientia_leaderboard\optout_manager::is_opted_out((int) $USER->id) ? 1 : 0,
            'show_my_rank'    => $my_rank !== null,
            'preferences_url' => (new \moodle_url('/local/sentientia_leaderboard/preferences.php'))->out(false),
        ];

        $this->content->text = $OUTPUT->render_from_template(
            'local_sentientia_leaderboard/board_view', $data);
        $this->content->footer = \html_writer::link(
            new \moodle_url('/local/sentientia_leaderboard/view.php',
                ['id' => $boardid]),
            get_string('action_view', 'local_sentientia_leaderboard'),
            ['class' => 'btn btn-link btn-sm']);

        // SSE / polling client.
        $realtime_on = !class_exists('\\local_sentientia_platform\\feature_flags')
            || \local_sentientia_platform\feature_flags::is_enabled(
                'sentientia.leaderboards.realtime.enabled');

        $PAGE->requires->js_call_amd(
            'local_sentientia_leaderboard/leaderboard_client',
            'init',
            [[
                'boardid'  => $boardid,
                'realtime' => $realtime_on,
            ]]
        );

        return $this->content;
    }
}
