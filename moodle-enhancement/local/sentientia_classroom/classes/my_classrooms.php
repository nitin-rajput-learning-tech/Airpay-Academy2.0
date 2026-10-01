<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * What the learner's "My classrooms" page shows (ADR-032, classroom code fix 12).
 *
 * my.php is behind the sentientia.classroom.import_history flag and shows the learner's OWN rows only. The data
 * is built here, apart from the page, so it can be tested without rendering a Moodle page.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_classrooms {

    /**
     * A name or title for the template: filtered like format_string(), but NOT HTML-escaped, because the template
     * escapes every {{ }} itself (including the {{# str }} caption argument). Escaping here too showed an
     * imported "Tom & Jerry" as "Tom &amp; Jerry" (the same bug class as my_evaluations.php).
     *
     * @param string $text
     * @return string
     */
    private static function plain(string $text): string {
        return format_string($text, true, ['escape' => false]);
    }

    /**
     * The template context of local_sentientia_classroom/my for one learner.
     *
     * @param int $userid The learner.
     * @param int $now Current time (tests pass a fixed one).
     * @return array{classrooms: array, has_classrooms: bool}
     */
    public static function context_for(int $userid, int $now = 0): array {
        $now = $now > 0 ? $now : time();
        $classrooms = session_manager::get_user_classrooms($userid);
        $sessions = session_manager::get_user_sessions($userid, array_map(fn($c) => (int) $c->id, $classrooms));

        $datefmt = '%d %b %Y';
        $timefmt = '%d %b %Y %H:%M';
        $attendance = [
            session_manager::ATT_ABSENT  => ['attendance_status_absent', 'badge-secondary'],
            session_manager::ATT_PRESENT => ['attendance_status_present', 'badge-success'],
            session_manager::ATT_LATE    => ['attendance_status_late', 'badge-warning'],
            session_manager::ATT_EXCUSED => ['attendance_status_excused', 'badge-info'],
        ];

        $items = [];
        foreach ($classrooms as $classroom) {
            $start = (int) ($classroom->trainingstart ?? 0);
            $end = (int) ($classroom->trainingend ?? 0);
            $dates = '';
            if ($start > 0 && $end > 0) {
                $dates = userdate($start, $datefmt) . ' – ' . userdate($end, $datefmt);
            } else if ($start > 0 || $end > 0) {
                $dates = userdate(max($start, $end), $datefmt);
            }

            $rows = [];
            foreach ($sessions[(int) $classroom->id] ?? [] as $session) {
                if ($session->attendance !== null) {
                    [$key, $badge] = $attendance[(int) $session->attendance] ?? $attendance[session_manager::ATT_ABSENT];
                    $label = get_string($key, 'local_sentientia_classroom');
                } else if ((int) $session->endtime > 0 && (int) $session->endtime < $now) {
                    $label = get_string('my_not_marked', 'local_sentientia_classroom');
                    $badge = 'badge-light';
                } else {
                    $label = '';
                    $badge = '';
                }
                $title = trim((string) $session->title);
                $rows[] = [
                    'title'          => $title !== '' ? self::plain($title)
                        : get_string('my_session_untitled', 'local_sentientia_classroom'),
                    'when'           => (int) $session->starttime > 0 ? userdate((int) $session->starttime, $timefmt) : '—',
                    'location'       => self::plain((string) ($session->location ?? '')),
                    'attendance'     => $label,
                    'has_attendance' => $label !== '',
                    'badge'          => $badge,
                ];
            }

            $completed = (int) $classroom->completion_status === 1;
            $items[] = [
                'name'               => self::plain((string) $classroom->name),
                'status_label'       => session_manager::status_label((int) $classroom->status),
                'status_css'         => session_manager::status_badge((int) $classroom->status),
                'location'           => self::plain((string) ($classroom->location ?? '')),
                'has_location'       => trim((string) ($classroom->location ?? '')) !== '',
                'training_dates'     => $dates,
                'has_training_dates' => $dates !== '',
                'completed'          => $completed,
                'completed_on'       => ($completed && (int) $classroom->completed_at > 0)
                    ? userdate((int) $classroom->completed_at, $datefmt) : '',
                'hours'              => $classroom->hours !== null ? (int) $classroom->hours : null,
                'has_hours'          => $classroom->hours !== null && (int) $classroom->hours > 0,
                'sessions'           => $rows,
                'has_sessions'       => !empty($rows),
            ];
        }

        return ['classrooms' => $items, 'has_classrooms' => !empty($items)];
    }
}
