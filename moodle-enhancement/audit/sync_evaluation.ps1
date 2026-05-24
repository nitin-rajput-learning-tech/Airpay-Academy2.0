$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation'

$files = @(
    'classes\evaluation_manager.php',
    'classes\form\edit_evaluation.php',
    'classes\external\delete_evaluation.php',
    'classes\external\change_status.php',
    'db\services.php',
    'db\access.php',
    'db\install.xml',
    'db\upgrade.php',
    'version.php',
    'index.php',
    'lang\en\local_airpay_evaluation.php',
    'templates\manage.mustache',
    'amd\src\evaluation_actions.js',
    'amd\build\evaluation_actions.min.js'
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
