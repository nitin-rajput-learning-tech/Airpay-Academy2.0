<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Source pins for the dashboard layout (layout/dashboard.php) and its templates, Phase C of 18-19 Nov 2026.
 *
 *   - DASH-QUICKNAV: the admin quick-navigation tiles are gone (the template stopped printing them long ago, but
 *     their counts still ran on every admin /my/ load);
 *   - DASH-THROWABLE: every dashboard section catches \Throwable, so an Error or TypeError costs the section, not /my/,
 *     and says so at developer level;
 *   - B4: the team compliance table scrolls inside its card at phone width;
 *   - the mini leaderboard's caption says it is organisation-wide (it is not per department);
 *   - LB-MASK (V9, Nitin, decision D2 = A): on the mini leaderboard everyone except the viewer shows as first name and
 *     last initial; the viewer's own row keeps the full name, and the initials avatar is unchanged;
 *   - V5: the course card shows the course image or a course icon, never the shortname;
 *   - V16: "Courses you teach" always runs (a bug fix with no flag: Nitin, decision D3; the flag G3 added first is
 *     gone again). A user who teaches nothing keeps the learner figures of every enrolled course, as before, and a
 *     user who only teaches gets no "Continue Learning" empty state.
 *
 * The page itself is checked by the visual pass. These tests read the sources with CRLF normalised to LF.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversNothing
 * @group theme_sentientia
 */
final class dashboard_layout_source_test extends \advanced_testcase {

    /** @var string The V16 flag key G3 registered first and V16-UNFLAG removed again. */
    private const TEACHING_FLAG = 'sentientia.dashboard.teaching.enabled';

    /**
     * layout/dashboard.php with LF line endings.
     *
     * @return string
     */
    private function layout(): string {
        $path = dirname(__DIR__) . '/layout/dashboard.php';
        $this->assertFileExists($path);
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /**
     * layout/dashboard.php without its comments, with every run of whitespace collapsed to one space.
     *
     * @return string
     */
    private function layout_code(): string {
        $code = '';
        foreach (token_get_all($this->layout()) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $code .= ' ';
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        return (string) preg_replace('/\s+/', ' ', $code);
    }

    /**
     * A theme template's source without its Mustache comments, with LF line endings.
     *
     * @param string $name template name under templates/, without .mustache
     * @return string
     */
    private function template(string $name): string {
        $path = dirname(__DIR__) . '/templates/' . $name . '.mustache';
        $this->assertFileExists($path);
        $source = str_replace("\r\n", "\n", (string) file_get_contents($path));
        // Comments end at the first closing pair of braces, as Mustache itself reads them.
        return (string) preg_replace('/\{\{!.*?\}\}/s', '', $source);
    }

    /**
     * DASH-THROWABLE: no section catches only \Exception, and each former \Exception site reports what it skipped.
     */
    public function test_every_section_catches_throwable(): void {
        $layout = $this->layout();
        $this->assertDoesNotMatchRegularExpression('/catch\s*\(\s*\\\\?Exception\b/', $layout);
        $this->assertGreaterThanOrEqual(10, substr_count($layout, 'catch (\Throwable'));

        foreach (['gamification', 'admin widgets', 'learner courses', 'certificate count', 'team overview',
                'deadlines', 'achievements', 'activity timeline'] as $section) {
            $this->assertStringContainsString(
                "debugging('Dashboard {$section} skipped: ' . \$e->getMessage(), DEBUG_DEVELOPER);", $layout,
                "the {$section} section reports what it skipped");
        }
    }

    /**
     * DASH-QUICKNAV: the quick-navigation tiles are neither built nor printed.
     */
    public function test_quick_navigation_is_gone(): void {
        $code = $this->layout_code();
        $template = $this->template('dashboard');
        foreach (['admin_quicknav', 'hasquicknav'] as $name) {
            $this->assertStringNotContainsString($name, $code);
            $this->assertStringNotContainsString($name, $template);
        }
        // The admin KPI figures the tiles shared are still computed.
        $this->assertStringContainsString('$totalusers = ', $code);
        $this->assertStringContainsString('$activeusers = ', $code);
    }

    /**
     * V16-UNFLAG: the teaching flag is no longer registered (Nitin, decision D3: a bug fix, no flag); the flag
     * registered before it is untouched.
     */
    public function test_teaching_flag_is_not_registered(): void {
        $path = dirname(__DIR__) . '/db/feature_flags.php';
        $this->assertFileExists($path);
        $flags = (static function (string $file): array {
            $flags = [];
            include($file);
            return $flags;
        })($path);

        $this->assertArrayNotHasKey(self::TEACHING_FLAG, $flags);
        $this->assertArrayHasKey('sentientia.dashboard.blocksdrawer.enabled', $flags);
        $this->assertFalse($flags['sentientia.dashboard.blocksdrawer.enabled']['default']);

        // Nothing reads the removed key any more, not even in a comment.
        foreach (['/layout/dashboard.php', '/templates/dashboard.mustache', '/classes/local/teaching_courses.php'] as $file) {
            $this->assertStringNotContainsString(self::TEACHING_FLAG, (string) file_get_contents(dirname(__DIR__) . $file),
                "{$file} does not mention the removed flag");
        }
    }

    /**
     * V16-UNFLAG: the teaching split runs for every learner-branch user, with no flag read. A missing class or a
     * failing lookup leaves no taught courses, so the dashboard is as before.
     */
    public function test_teaching_split_runs_unconditionally(): void {
        $code = $this->layout_code();

        $this->assertStringNotContainsString('$teachingflag', $code);
        $this->assertStringNotContainsString("'" . self::TEACHING_FLAG . "'", $code);

        // teaching_courses is called once, behind class_exists only, and starts from no taught courses.
        $guard = <<<'EOT'
$teachingids = []; if (class_exists('\\theme_sentientia\\local\\teaching_courses')) { try { $teachingids = \theme_sentientia\local\teaching_courses::course_ids(
EOT;
        $this->assertStringContainsString($guard, $code);
        $this->assertSame(1, substr_count($code, 'teaching_courses::course_ids('));
        // A failing lookup costs the regrouping only: it says so at developer level and falls back to [].
        $fallback = <<<'EOT'
} catch (\Throwable $e) { debugging('Dashboard teaching courses skipped: ' . $e->getMessage(), DEBUG_DEVELOPER); $teachingids = []; } }
EOT;
        $this->assertStringContainsString($fallback, $code);
        $this->assertGreaterThan(strpos($code, $guard), strpos($code, $fallback));
        // $teachingonly starts false, before the learner try block.
        $this->assertStringContainsString(
            '$teachingonly = false; try { $enrolledcourses = enrol_get_all_users_courses($USER->id, true); $teachingids = [];',
            $code);

        // With no taught courses the learner figures are the enrolled courses, as before V16.
        $this->assertStringContainsString('$learningcourses = $enrolledcourses; $teachingcourses = []; foreach ($teachingids as $tid) {', $code);
        $this->assertStringContainsString('foreach ($learningcourses as $course) {', $code);
        $this->assertStringContainsString("'enrolled' => count(\$learningcourses),", $code);
        $this->assertStringContainsString('$totalenrolled = count($learningcourses);', $code);
        $this->assertStringContainsString('$teachingonly = empty($learningcourses) && !empty($teachingcourses);', $code);
        $this->assertStringContainsString("\$airpay_dashboard['has_progress_ring'] = !\$teachingonly;", $code);
        $this->assertStringContainsString(
            "\$airpay_dashboard['haslearnerkpis'] = !empty(\$airpay_dashboard['hasstats']) && empty(\$teachingonly);", $code);
        $this->assertStringContainsString("\$airpay_dashboard['hasteachingcourses'] = !empty(\$teachingcourses);", $code);
        // The template hides the Continue Learning empty state for a user who only teaches.
        $this->assertStringContainsString("\$airpay_dashboard['teachingonly'] = \$teachingonly;", $code);
        $this->assertGreaterThan(
            strpos($code, '$teachingonly = empty($learningcourses) && !empty($teachingcourses);'),
            strpos($code, "\$airpay_dashboard['teachingonly'] = \$teachingonly;"));
        // The deadlines follow the learning courses; the recommendations still exclude every enrolled course.
        $this->assertStringContainsString('$enrolledids = array_keys($learningcourses ?? ($enrolledcourses ?? []));', $code);
        $this->assertStringContainsString('$enrolledids = array_keys($enrolledcourses ?? []);', $code);

        // The manager greeting is for a manager with a team only.
        $this->assertStringContainsString(
            "\$airpay_dashboard['showmanagerheader'] = \$ismanager && !empty(\$airpay_dashboard['hasmanagerkpis']);",
            $code);
    }

    /**
     * V16 (reviewer finding): a user who only teaches gets no "Continue Learning" empty state under "Courses you
     * teach"; a learner with no courses still gets it, as before.
     */
    public function test_continue_empty_state_is_hidden_for_teaching_only(): void {
        $template = $this->template('dashboard');

        $open = '{{^hascontinuecourses}}{{^teachingonly}}';
        $close = '{{/teachingonly}}{{/hascontinuecourses}}';
        $this->assertSame(1, substr_count($template, $open));
        $this->assertSame(1, substr_count($template, $close));
        // The empty-state section is inside both gates.
        $start = strpos($template, $open);
        $end = strpos($template, $close);
        $this->assertGreaterThan($start, $end);
        $section = substr($template, $start, $end - $start);
        $this->assertStringContainsString('{{#empty_continue}}', $section);
        $this->assertStringContainsString('{{> theme_sentientia/components/empty_state }}', $section);
        $this->assertStringContainsString('{{#str}}dash_continue_learning, theme_sentientia{{/str}}', $section);
        // It is the only place the empty state is printed, and it comes after "Courses you teach".
        $this->assertSame(1, substr_count($template, '{{#empty_continue}}'));
        $this->assertGreaterThan(strpos($template, '{{/hasteachingcourses}}'), $start);
        // The layout still builds the empty state for everybody; only the template gate differs.
        $this->assertStringContainsString("\$airpay_dashboard['empty_continue'] = [[", $this->layout_code());
    }

    /**
     * LB-MASK (V9, Nitin, decision D2 = A): on the mini leaderboard everyone except the viewer shows as first name and
     * last initial; the viewer's own row keeps the full name, and the initials avatar is unchanged.
     */
    public function test_leaderboard_masks_other_people_to_last_initial(): void {
        $code = $this->layout_code();

        $build = "\$leaderboard = \\local_sentientia_gamification\\leaderboard::get_template_data(\$USER->id, 'department', 5);";
        $loop = "foreach (\$leaderboard['entries'] as &\$lbentry) {";
        $skipown = "if (!is_array(\$lbentry) || !empty(\$lbentry['is_current_user'])) { continue; }";
        $mask = "\$lbentry['lastname'] = \\core_text::substr(\$lblastname, 0, 1) . '.';";
        $assign = "\$airpay_dashboard['leaderboard'] = \$leaderboard;";
        foreach ([$build, $loop, $skipown, $mask, $assign] as $needle) {
            $this->assertStringContainsString($needle, $code);
        }
        $this->assertSame(1, substr_count($code, $assign));
        // The masking runs after the board is built and before it reaches the template.
        $this->assertGreaterThan(strpos($code, $build), strpos($code, $loop));
        $this->assertGreaterThan(strpos($code, $loop), strpos($code, $skipown));
        $this->assertGreaterThan(strpos($code, $skipown), strpos($code, $mask));
        $this->assertGreaterThan(strpos($code, $mask), strpos($code, $assign));

        // The masked block: lastname is format_string() output, so it is decoded and trimmed first, an empty one is
        // left alone, and the reference is released after the loop.
        $block = substr($code, (int) strpos($code, $loop), strpos($code, $assign) - strpos($code, $loop));
        $this->assertStringContainsString(
            "\$lblastname = trim(html_entity_decode((string) (\$lbentry['lastname'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));",
            $block);
        $this->assertStringContainsString("if (\$lblastname !== '') {", $block);
        $this->assertStringContainsString('unset($lbentry);', $block);
        // Only the last name changes: the first name and the initials avatar are not touched.
        $this->assertStringNotContainsString("\$lbentry['firstname']", $block);
        $this->assertStringNotContainsString("\$lbentry['initials']", $block);
        $this->assertStringNotContainsString("\$lbentry['is_current_user'] =", $block);

        // The template still prints the initials avatar and "firstname lastname", escaped.
        $template = $this->template('dashboard');
        $start = strpos($template, '{{#leaderboard.entries}}');
        $this->assertNotFalse($start);
        $row = substr($template, $start, (int) strpos($template, '{{/leaderboard.entries}}') - $start);
        $this->assertStringContainsString('{{initials}}', $row);
        $this->assertStringContainsString('{{firstname}} {{lastname}}', $row);
        $this->assertStringNotContainsString('{{{lastname}}}', $row);
    }

    /**
     * V16: the template lists the taught courses in the learner section, before Continue Learning, and the greeting
     * follows showmanagerheader.
     */
    public function test_template_teaching_section_and_greeting(): void {
        $template = $this->template('dashboard');

        $this->assertSame(1, substr_count($template, '{{#hasteachingcourses}}'));
        $section = substr($template, (int) strpos($template, '{{#hasteachingcourses}}'));
        $section = substr($section, 0, (int) strpos($section, '{{/hasteachingcourses}}'));
        $this->assertStringContainsString('{{#str}}dash_sec_teaching, theme_sentientia{{/str}}', $section);
        $this->assertStringContainsString('{{#teachingcourses}}', $section);

        $learner = strpos($template, '{{#islearner}}');
        $teaching = strpos($template, '{{#hasteachingcourses}}');
        $continue = strpos($template, '{{#hascontinuecourses}}');
        $learnerend = strpos($template, '{{/islearner}}');
        $this->assertNotFalse($learner);
        $this->assertNotFalse($continue);
        $this->assertNotFalse($learnerend);
        $this->assertGreaterThan($learner, $teaching);
        $this->assertGreaterThan($teaching, $continue);
        $this->assertGreaterThan($continue, $learnerend);

        $this->assertStringContainsString('{{#showmanagerheader}}', $template);
        $this->assertStringContainsString('{{^isadmin}}{{^showmanagerheader}}', $template);
        $this->assertStringNotContainsString('{{#ismanager}}{{^isadmin}}', $template);
        $this->assertStringNotContainsString('{{^isadmin}}{{^ismanager}}', $template);
        // The ring keeps its own gate, which the layout sets.
        $this->assertStringContainsString('{{#has_progress_ring}}', $template);
    }

    /**
     * B4: the team compliance table sits in the scroll wrapper custom_media.scss styles.
     */
    public function test_team_table_is_in_its_scroll_wrapper(): void {
        $template = $this->template('dashboard');
        $this->assertMatchesRegularExpression(
            '#<div class="airpay-dash__team-table-wrapper">\s*<table class="generaltable airpay-dash__team-table">#',
            $template);
        $table = strpos($template, '<table class="generaltable airpay-dash__team-table">');
        $this->assertMatchesRegularExpression('#^.*?</table>\s*</div>#s', substr($template, (int) $table));

        $scss = (string) file_get_contents(dirname(__DIR__) . '/scss/moodle/custom_media.scss');
        $this->assertStringContainsString('.airpay-dash__team-table-wrapper', $scss);
    }

    /**
     * The mini leaderboard is tenant-wide, so its caption names the organisation, not the department.
     */
    public function test_leaderboard_caption_names_the_organisation(): void {
        $template = $this->template('dashboard');
        $start = strpos($template, 'airpay-gamification__leaderboard-header');
        $this->assertNotFalse($start);
        $header = substr($template, $start, (int) strpos($template, '</div>', $start) - $start);
        $this->assertStringContainsString('{{#str}}dash_your_organisation, theme_sentientia{{/str}}', $header);
        $this->assertStringNotContainsString('dash_your_department', $template);
    }

    /**
     * V5: the layout gives the cards no shortname and looks up each course image.
     */
    public function test_layout_passes_course_images_not_shortnames(): void {
        $code = $this->layout_code();
        $this->assertStringNotContainsString("'shortname' => format_string(\$course->shortname)", $code);
        $this->assertStringContainsString('\core_course\external\course_summary_exporter::get_course_image(', $code);
        $this->assertStringContainsString("\$card['thumburl'] = '';", $code);
    }

    /**
     * V5: rendered, the card shows a course icon without an image, and the image over the brand gradient with one.
     */
    public function test_course_card_thumb(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->force_theme('sentientia');
        $renderer = $PAGE->get_renderer('core');
        $partial = 'theme_sentientia/components/course_progress_card';

        $plain = $renderer->render_from_template($partial, ['viewurl' => '#', 'fullname' => 'X', 'progress' => 0]);
        $this->assertStringContainsString('fa-graduation-cap', $plain);
        $thumbstart = strpos($plain, 'airpay-course-card__thumb');
        $this->assertNotFalse($thumbstart);
        $thumb = substr($plain, $thumbstart, (int) strpos($plain, 'airpay-course-card__body') - $thumbstart);
        $this->assertStringNotContainsString('>X</span>', $thumb);
        $this->assertStringNotContainsString('background-image', $thumb);

        $image = $renderer->render_from_template($partial, [
            'viewurl' => '#', 'fullname' => 'X', 'progress' => 0, 'thumburl' => 'https://example.com/course.png',
        ]);
        $this->assertStringContainsString('var(--ap-gradient-cta)', $image);
        $this->assertStringContainsString("url('https://example.com/course.png')", $image);
        $this->assertStringNotContainsString('fa-graduation-cap', $image);

        // A caller that still passes a shortname gets it printed in the thumb, as before.
        $short = $renderer->render_from_template($partial,
            ['viewurl' => '#', 'fullname' => 'X', 'progress' => 0, 'shortname' => 'ABC']);
        $this->assertStringContainsString('>ABC</span>', $short);
        $this->assertStringNotContainsString('fa-graduation-cap', $short);
    }
}
