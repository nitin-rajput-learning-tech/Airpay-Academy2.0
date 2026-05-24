$files = @(
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\skills_manager.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\form\edit_skill.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\form\edit_category.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\external\delete_skill.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\classes\external\delete_category.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\db\services.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\db\access.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\version.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\admin.php',
    'C:\xampp\htdocs\moodle5\public\local\airpay_skills\lang\en\local_airpay_skills.php',
    'C:\xampp\htdocs\moodle5\public\theme\airpayux\classes\sidebar_navigation.php'
)
foreach ($f in $files) {
    & C:\xampp\php\php.exe -l $f
}
