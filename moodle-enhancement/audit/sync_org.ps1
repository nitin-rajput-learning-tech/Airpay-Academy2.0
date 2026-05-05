$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_org'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_org'

$files = @(
    'version.php',
    'admin.php',
    'classes\org_manager.php',
    'classes\form\edit_org.php',
    'classes\external\delete_org.php',
    'classes\external\toggle_visibility.php',
    'db\services.php',
    'lang\en\local_airpay_org.php',
    'templates\manage.mustache',
    'templates\org_node.mustache',
    'amd\src\org_actions.js',
    'amd\build\org_actions.min.js'
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
