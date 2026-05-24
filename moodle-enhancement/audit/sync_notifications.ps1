$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_notifications'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_notifications'

$files = @(
    'classes\rule_manager.php',
    'classes\form\edit_rule.php',
    'classes\external\delete_rule.php',
    'classes\external\toggle_rule.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'index.php',
    'lang\en\local_airpay_notifications.php',
    'templates\manage.mustache',
    'amd\src\rule_actions.js',
    'amd\build\rule_actions.min.js'
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
