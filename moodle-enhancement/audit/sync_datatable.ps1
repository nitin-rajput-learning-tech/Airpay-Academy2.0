$mappings = @(
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\src\datatable.js';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\src\datatable.js' },
    @{ src='C:\xampp\htdocs\moodle5\public\theme\airpayux\amd\build\datatable.min.js';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\amd\build\datatable.min.js' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\index.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\index.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\templates\manage.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\version.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_users\classes\external\list_users.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users\classes\external\list_users.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\index.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\index.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\templates\manage.mustache';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\templates\manage.mustache' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\db\services.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\db\services.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\version.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\version.php' },
    @{ src='C:\xampp\htdocs\moodle5\public\local\airpay_courses\classes\external\list_courses.php';
       dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses\classes\external\list_courses.php' }
)

foreach ($m in $mappings) {
    $dd = Split-Path $m.dst -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $m.src) {
        Copy-Item $m.src $m.dst -Force
        Write-Host "OK: $($m.src.Replace('C:\xampp\htdocs\moodle5\public\', ''))"
    } else {
        Write-Host "MISS: $($m.src)"
    }
}
