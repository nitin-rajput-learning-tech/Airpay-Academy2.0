$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation'

$files = @(
    'classes\evaluation_manager.php',
    'classes\external\submit_response.php',
    'db\services.php',
    'version.php',
    'respond.php',
    'responses.php',
    'lang\en\local_airpay_evaluation.php',
    'templates\manage.mustache',
    'templates\respond.mustache',
    'templates\responses.mustache',
    'amd\src\response_actions.js',
    'amd\build\response_actions.min.js'
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
