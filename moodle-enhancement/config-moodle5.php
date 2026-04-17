<?php  // Moodle 5.1 configuration file — LOCAL DEVELOPMENT
// Airpay Academy Enterprise v4.0
// config.php lives at moodle5/ root (ABOVE public/)

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

// Disable auto-download of plugin updates
$CFG->updateautocheck = false;
$CFG->disableupdateautodeploy = true;

// CRITICAL: Prevent ALL email sending from local environment.
$CFG->noemailever = true;

require_once(__DIR__ . '/lib/setup.php');
