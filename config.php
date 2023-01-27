<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'bayeruat3';
$CFG->dbuser    = 'root';
$CFG->dbpass    = 'Newpassword@123';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://localhost/bayer';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->cachejs     = false;
$CFG->directorypermissions = 0777;
//  $CFG->debug = (E_ALL | E_STRICT);  
 // === DEBUG_DEVELOPER - NOT FOR PRODUCTION SERVERS!
//  $CFG->debugdisplay = 0;  
require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!