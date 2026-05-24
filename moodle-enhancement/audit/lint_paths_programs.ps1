$files = @(
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\path_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\form\edit_path.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\external\delete_path.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\external\toggle_status.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\index.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\lang\en\local_airpay_learningpath.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\classes\program_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\classes\form\edit_program.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\classes\external\delete_program.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\classes\external\change_status.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\index.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_programs\lang\en\local_airpay_programs.php'
)
foreach ($f in $files) {
    & C:\xampp\php\php.exe -l $f
}
