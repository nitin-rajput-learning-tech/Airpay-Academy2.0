$files = @(
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\classes\evaluation_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\classes\form\edit_evaluation.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\classes\external\delete_evaluation.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\classes\external\change_status.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\db\upgrade.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\index.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\lang\en\local_airpay_evaluation.php'
)
foreach ($f in $files) {
    & C:\xampp\php\php.exe -l $f
}
