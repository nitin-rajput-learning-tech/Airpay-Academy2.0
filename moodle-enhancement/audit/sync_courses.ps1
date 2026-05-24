$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_courses'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_courses'

$files = @(
    'classes\course_manager.php',
    'classes\form\edit_course.php',
    'classes\external\toggle_visibility.php',
    'classes\external\delete_course.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'index.php',
    'lang\en\local_airpay_courses.php',
    'templates\manage.mustache',
    'amd\src\course_actions.js',
    'amd\build\course_actions.min.js'
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
