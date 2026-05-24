$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_users'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_users'

$files = @(
    'classes\user_manager.php',
    'classes\form\edit_user.php',
    'classes\external\suspend_user.php',
    'classes\external\delete_user.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'lang\en\local_airpay_users.php',
    'templates\manage.mustache',
    'amd\src\user_actions.js',
    'amd\build\user_actions.min.js',
    'amd\build\user_actions.min.js.map'
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
