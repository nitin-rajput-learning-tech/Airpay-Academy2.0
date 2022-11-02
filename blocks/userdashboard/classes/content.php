<?php
namespace block_userdashboard;
class content{
	public function __construct($db, $user = NULL){
		global $USER;
		$this->db = $db;
		$this->user = is_null($user) ? $USER : $user;
	}
}