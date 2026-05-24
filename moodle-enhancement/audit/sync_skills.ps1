$src = 'C:\xampp\htdocs\moodle5\public\local\airpay_skills'
$dst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_skills'

$files = @(
    'classes\skills_manager.php',
    'classes\form\edit_skill.php',
    'classes\form\edit_category.php',
    'classes\external\delete_skill.php',
    'classes\external\delete_category.php',
    'db\services.php',
    'db\access.php',
    'version.php',
    'admin.php',
    'lang\en\local_airpay_skills.php',
    'templates\manage.mustache',
    'amd\src\skill_actions.js',
    'amd\build\skill_actions.min.js'
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

# Also sync sidebar nav update
Copy-Item 'C:\xampp\htdocs\moodle5\public\theme\airpayux\classes\sidebar_navigation.php' `
          'D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\classes\sidebar_navigation.php' -Force
Write-Host "OK: theme/airpayux/classes/sidebar_navigation.php"
