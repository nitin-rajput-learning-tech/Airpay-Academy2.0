$files = @(
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\classes\exam_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\classes\form\edit_exam.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\classes\external\delete_exam.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\classes\external\toggle_status.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\index.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_exams\lang\en\local_airpay_exams.php'
)
foreach ($f in $files) {
    & C:\xampp\php\php.exe -l $f
}
