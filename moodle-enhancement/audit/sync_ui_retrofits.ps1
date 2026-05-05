$pairs = @(
    'airpay_reports\index.php',
    'airpay_reports\templates\manage.mustache',
    'airpay_evaluation\index.php',
    'airpay_evaluation\templates\manage.mustache',
    'airpay_skills\admin.php',
    'airpay_skills\templates\manage.mustache',
    'airpay_notifications\index.php',
    'airpay_notifications\templates\manage.mustache',
    'airpay_programs\index.php',
    'airpay_programs\templates\manage.mustache',
    'airpay_learningpath\index.php',
    'airpay_learningpath\templates\manage.mustache'
)

$srcRoot = 'C:\xampp\htdocs\moodle5\public\local'
$dstRoot = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local'

foreach ($p in $pairs) {
    $sf = Join-Path $srcRoot $p
    $df = Join-Path $dstRoot $p
    $dd = Split-Path $df -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $sf) {
        Copy-Item $sf $df -Force
        Write-Host "OK: $p"
    } else {
        Write-Host "MISS: $p"
    }
}
