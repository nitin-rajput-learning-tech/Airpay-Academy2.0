$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_evaluation'

$files = @(
    'classes\evaluation_manager.php',
    'classes\form\edit_question.php',
    'classes\external\delete_question.php',
    'classes\external\reorder_questions.php',
    'db\services.php',
    'version.php',
    'questions.php',
    'lang\en\local_airpay_evaluation.php',
    'templates\manage.mustache',
    'templates\questions.mustache',
    'amd\src\question_actions.js',
    'amd\build\question_actions.min.js'
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
