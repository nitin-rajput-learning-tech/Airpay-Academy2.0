$mappings = @(
    # D1 — manager drill-down
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\classes\team_manager.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\classes\team_manager.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\index.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\index.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\member.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\member.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\templates\dashboard.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\templates\dashboard.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_manager\templates\member.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_manager\templates\member.mustache' },

    # D2 — dashboard widgets (refactored manager section)
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\layout\dashboard.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\layout\dashboard.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\templates\dashboard.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\templates\dashboard.mustache' },

    # D3 — bulk operations + datatable selection
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\src\datatable.js';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\src\datatable.js' },
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\build\datatable.min.js';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\build\datatable.min.js' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\classes\external\bulk_action.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\classes\external\bulk_action.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\version.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\templates\manage.mustache' }
)

foreach ($m in $mappings) {
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    }
}
