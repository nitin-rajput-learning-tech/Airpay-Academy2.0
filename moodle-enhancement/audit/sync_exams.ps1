$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_exams'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_exams'

$files = @(
    'classes\exam_manager.php',
    'classes\form\edit_exam.php',
    'classes\external\delete_exam.php',
    'classes\external\toggle_status.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'index.php',
    'lang\en\local_airpay_exams.php',
    'templates\manage.mustache',
    'amd\src\exam_actions.js',
    'amd\build\exam_actions.min.js'
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
