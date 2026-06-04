<?php
/**
 * Phase 8A: Enable activity completion tracking globally and per course.
 * This is the foundation for compliance tracking, dashboard stats, and progress reporting.
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
global $DB, $CFG;

echo "=== PHASE 8A: Activity Completion Configuration ===\n\n";

// 1. Check and enable global completion tracking
echo "1. Global completion tracking: ";
if (!empty($CFG->enablecompletion)) {
    echo "ALREADY ENABLED\n";
} else {
    set_config('enablecompletion', 1);
    echo "ENABLED NOW\n";
}

// 2. Enable completion on all test courses
echo "\n2. Enabling completion on courses:\n";
$courses = $DB->get_records_select('course', 'id > 1', [], '', 'id,shortname,fullname,enablecompletion');
$updated = 0;
foreach ($courses as $course) {
    if (!$course->enablecompletion) {
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        echo "   ENABLED: {$course->shortname} (id={$course->id})\n";
        $updated++;
    } else {
        echo "   OK: {$course->shortname} (already enabled)\n";
    }
}
echo "   Updated: $updated courses\n";

// 3. Check for course_modules_completion records
echo "\n3. Completion data status:\n";
$totalcompletions = $DB->count_records('course_completions');
$completedcount = $DB->count_records_select('course_completions', 'timecompleted IS NOT NULL');
$modulecompletions = $DB->count_records('course_modules_completion');
echo "   Course completions: $totalcompletions total ($completedcount with timecompleted)\n";
echo "   Module completions: $modulecompletions\n";

// 4. Check default completion settings
echo "\n4. Default completion configuration:\n";
$defaultcompletion = get_config('moodlecourse', 'enablecompletion');
echo "   New course default: " . ($defaultcompletion ? 'completion enabled' : 'completion disabled') . "\n";
if (!$defaultcompletion) {
    set_config('enablecompletion', 1, 'moodlecourse');
    echo "   FIXED: New courses will now have completion enabled by default\n";
}

echo "\n=== COMPLETION TRACKING READY ===\n";
echo "Dashboard stats and compliance tracking can now use completion data.\n";
echo "Note: Activity-level completion still needs modules added to courses.\n";
echo "On production, SCORM modules auto-track completion.\n";
