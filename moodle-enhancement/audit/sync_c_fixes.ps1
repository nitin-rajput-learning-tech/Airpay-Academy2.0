$mappings = @(
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\classes\output\core_renderer.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\classes\output\core_renderer.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\classes\course_manager.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\classes\course_manager.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\classes\form\edit_course.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\classes\form\edit_course.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\classes\form\edit_user.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\classes\form\edit_user.php' }
)

foreach ($m in $mappings) {
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    }
}
