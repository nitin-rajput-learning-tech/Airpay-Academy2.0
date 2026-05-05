$mappings = @(
    # Phase 1 — mobile + dark mode
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\dark_mode.scss';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\scss\moodle\dark_mode.scss' },
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\partials\_datatable.scss';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\scss\moodle\partials\_datatable.scss' },
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\custom_changes.scss';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\scss\moodle\custom_changes.scss' },

    # Phase 1 — fixed CSS variable names in templates
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\templates\manage.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\templates\manage.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\templates\manage.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\templates\run.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\templates\run.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\templates\member.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\templates\member.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\templates\questions.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\templates\questions.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\templates\respond.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\templates\respond.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\templates\responses.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation\templates\responses.mustache' },

    # Phase 2 — security fixes
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\classes\external\list_users.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\classes\external\list_users.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\classes\external\bulk_action.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\classes\external\bulk_action.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\classes\external\list_courses.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\classes\external\list_courses.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\classes\org_manager.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\classes\org_manager.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\classes\external\delete_org.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\classes\external\delete_org.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\classes\external\toggle_visibility.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\classes\external\toggle_visibility.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\classes\report_manager.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\classes\report_manager.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\classes\external\delete_report.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\classes\external\delete_report.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\classes\external\toggle_status.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\classes\external\toggle_status.php' }
)

foreach ($m in $mappings) {
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    }
}
