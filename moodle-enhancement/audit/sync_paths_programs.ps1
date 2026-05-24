$pairs = @(
    @{src='C:\xampp\htdocs\moodle5\public\local\airpay_learningpath'; dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_learningpath'; files=@(
        'classes\path_manager.php',
        'classes\form\edit_path.php',
        'classes\external\delete_path.php',
        'classes\external\toggle_status.php',
        'db\services.php',
        'db\access.php',
        'version.php',
        'index.php',
        'lang\en\local_airpay_learningpath.php',
        'templates\manage.mustache',
        'amd\src\path_actions.js',
        'amd\build\path_actions.min.js'
    )},
    @{src='C:\xampp\htdocs\moodle5\public\local\airpay_programs'; dst='D:\Claude Local\airpay-ld-os\moodle-enhancement\local\airpay_programs'; files=@(
        'classes\program_manager.php',
        'classes\form\edit_program.php',
        'classes\external\delete_program.php',
        'classes\external\change_status.php',
        'db\services.php',
        'db\access.php',
        'db\install.xml',
        'db\upgrade.php',
        'version.php',
        'index.php',
        'lang\en\local_airpay_programs.php',
        'templates\manage.mustache',
        'amd\src\program_actions.js',
        'amd\build\program_actions.min.js'
    )}
)

foreach ($p in $pairs) {
    foreach ($f in $p.files) {
        $sf = Join-Path $p.src $f
        $df = Join-Path $p.dst $f
        $dd = Split-Path $df -Parent
        if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
        if (Test-Path $sf) {
            Copy-Item $sf $df -Force
            Write-Host "OK: $f"
        } else {
            Write-Host "MISS: $f"
        }
    }
}
