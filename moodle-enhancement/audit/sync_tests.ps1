# Sync PHPUnit test files from XAMPP to repo.
# Run after the test-writer agent finishes.

$pairs = @(
    @{ src='airpay_users\tests\external\list_users_test.php' },
    @{ src='airpay_users\tests\external\bulk_action_test.php' },
    @{ src='airpay_courses\tests\external\list_courses_test.php' },
    @{ src='airpay_org\tests\org_manager_test.php' },
    @{ src='airpay_org\tests\external\delete_org_test.php' },
    @{ src='airpay_reports\tests\external\delete_report_test.php' }
)

$srcRoot = 'C:\xampp\htdocs\moodle5\public\local'
$dstRoot = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\local'

foreach ($p in $pairs) {
    $sf = Join-Path $srcRoot $p.src
    $df = Join-Path $dstRoot $p.src
    $dd = Split-Path $df -Parent
    if (!(Test-Path $dd)) { New-Item -ItemType Directory $dd -Force | Out-Null }
    if (Test-Path $sf) {
        Copy-Item $sf $df -Force
        Write-Host "OK: $($p.src)"
    } else {
        Write-Host "MISS: $($p.src)"
    }
}

# Also sync the config.php phpunit lines + runbook
$configSrc = 'C:\xampp\htdocs\moodle5\config.php'
$configDst = 'D:\Claude Local\airpay-ld-os\moodle-enhancement\config-sample.php'
if (Test-Path $configSrc) {
    Copy-Item $configSrc $configDst -Force
    Write-Host "OK: config.php → config-sample.php"
}
