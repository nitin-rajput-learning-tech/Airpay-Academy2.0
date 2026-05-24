$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_classroom'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_classroom'

$files = @(
    'classes\session_manager.php',
    'classes\form\edit_classroom.php',
    'classes\external\delete_classroom.php',
    'classes\external\change_status.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'index.php',
    'lang\en\local_airpay_classroom.php',
    'templates\manage.mustache',
    'amd\src\classroom_actions.js',
    'amd\build\classroom_actions.min.js'
)

foreach ($f in $files) {
    $sf = Join-Path $src $f
    $df = Join-Path $dst $f
    $dd = Split-Path $df -Parent
    if (!(Test-Path $dd)) {
        New-Item -ItemType Directory $dd -Force | Out-Null
    }
    if (Test-Path $sf) {
        Copy-Item $sf $df -Force
        Write-Host "OK: $f"
    } else {
        Write-Host "MISS: $f"
    }
}
