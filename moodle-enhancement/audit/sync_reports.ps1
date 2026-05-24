$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_reports'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_reports'

$files = @(
    'version.php',
    'index.php',
    'run.php',
    'export.php',
    'classes\report_manager.php',
    'classes\form\edit_report.php',
    'classes\external\delete_report.php',
    'classes\external\toggle_status.php',
    'db\install.xml',
    'db\access.php',
    'db\services.php',
    'lang\en\local_airpay_reports.php',
    'templates\manage.mustache',
    'templates\run.mustache',
    'amd\src\report_actions.js',
    'amd\build\report_actions.min.js'
)

foreach ($f in $files) {
    $sf = Join-Path $src $f
    $df = Join-Path $dst $f
    $dd = Split-Path $df -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $sf) {
        Copy-Item $sf $df -Force
        Write-Host "OK: $f"
    } else {
        Write-Host "MISS: $f"
    }
}

# Also sync sidebar nav (modified for Reports link).
$nav_src = 'C:\xampp\htdocs\moodle5\public\theme\airpayux\classes\sidebar_navigation.php'
$nav_dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\classes\sidebar_navigation.php'
if (Test-Path $nav_src) {
    $nav_dd = Split-Path $nav_dst -Parent
    if (!(Test-Path $nav_dd)) { New-Item -ItemType Directory $nav_dd -Force | Out-Null }
    Copy-Item $nav_src $nav_dst -Force
    Write-Host "OK: sidebar_navigation.php"
}
