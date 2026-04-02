<?php  // Moodle configuration file — LOCAL DEVELOPMENT
// Production config preserved at config.php.production-backup

// Suppress PHP 8.2 deprecation warnings that break Moodle sessions
// (cache_config dynamic properties output before session_start)
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://localhost:8080/moodle';
$CFG->dataroot  = 'C:\\xampp\\moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// Debug to log file only — display OFF to prevent session breakage
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 0;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
