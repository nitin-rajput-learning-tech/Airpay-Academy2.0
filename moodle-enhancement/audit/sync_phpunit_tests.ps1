$mappings = @(
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\tests\external\list_users_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\tests\external\list_users_test.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\tests\external\bulk_action_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\tests\external\bulk_action_test.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\tests\external\list_courses_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\tests\external\list_courses_test.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\tests\org_manager_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\tests\org_manager_test.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_org\tests\external\delete_org_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org\tests\external\delete_org_test.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_reports\tests\external\delete_report_test.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports\tests\external\delete_report_test.php' }
)

foreach ($m in $mappings) {
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    }
}

# Sync config-sample.php for the phpunit_dataroot lines.
$cfgsrc = 'C:\xampp\htdocs\moodle5\config.php'
$cfgdst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\config-sample.php'
if (Test-Path $cfgsrc) { Copy-Item $cfgsrc $cfgdst -Force; Write-Host "OK: config.php → config-sample.php" }
