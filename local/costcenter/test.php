<?php
require_once('../../config.php');
// $uerrolepath = (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path(5);
// print_object($uerrolepath);

$uerrolepath = (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path();
print_object($uerrolepath);