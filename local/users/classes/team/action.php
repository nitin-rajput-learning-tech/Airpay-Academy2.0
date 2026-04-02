<?php
namespace local_users\team;
class action {
	//stores the db variable 
    public $db;
	//stores the user 
    public $user;
    public function __construct($db, $user) {
        global $DB, $USER;
        $this->db = $db ? $db : $DB;
        $this->user = $user ? $user : $USER;
    }
    public function team_approvals_view() {
    }
}
