$mappings = @(
    # 4a — classroom + exams (full retrofit: WS + index + template + version + services)
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_classroom\classes\external\list_classrooms.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom\classes\external\list_classrooms.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_classroom\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_classroom\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom\version.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_classroom\index.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom\index.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_classroom\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom\templates\manage.mustache' },

    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_exams\classes\external\list_exams.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams\classes\external\list_exams.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_exams\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_exams\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams\version.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_exams\index.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams\index.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_exams\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams\templates\manage.mustache' },

    # 4b — eval + skills + notifications (WS layer only — UI rewrite is follow-up)
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\classes\external\list_evaluations.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\classes\external\list_evaluations.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\version.php' },

    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\external\list_skills.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_skills\classes\external\list_skills.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_skills\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_skills\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_skills\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_skills\version.php' },

    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_notifications\classes\external\list_rules.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_notifications\classes\external\list_rules.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_notifications\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_notifications\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_notifications\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_notifications\version.php' },

    # 4c — programs + paths + reports (WS layer only)
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_programs\classes\external\list_programs.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_programs\classes\external\list_programs.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_programs\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_programs\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_programs\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_programs\version.php' },

    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\external\list_paths.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_learningpath\classes\external\list_paths.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_learningpath\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_learningpath\version.php' },

    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\classes\external\list_reports.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\classes\external\list_reports.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\version.php' },

    # PROJECT-STATE.md update
    @{ src='D:\Claude Local\airpay-ld-os\moodle-enhancement\PROJECT-STATE.md';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\PROJECT-STATE.md' }
)

foreach ($m in $mappings) {
    if ($m.src -eq $m.dst) { continue }
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    }
}
