<?php
require_once('../../config.php');

$costcenterpath="/159";
//echo $costcenterpath;
$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='g.open_path',$costcenterpath);

//echo $costcenterpathconcatsql;