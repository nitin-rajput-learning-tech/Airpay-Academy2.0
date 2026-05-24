$files = @(
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\classes\rule_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\classes\form\edit_rule.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\classes\external\delete_rule.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\classes\external\toggle_rule.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\index.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_notifications\lang\en\local_airpay_notifications.php'
)
foreach ($f in $files) {
    & C:\xampp\php\php.exe -l $f
}
