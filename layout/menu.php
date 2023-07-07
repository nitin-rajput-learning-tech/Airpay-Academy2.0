<?php
global $DB,$OUTPUT,$USER,$CFG,$PAGE;
require(__DIR__ . '/../config.php');
?>

<nav class="navbar navbar-expand-lg tophead">
	<div class="container">
			<a class="navbar-brand" href="#"><img src="resources/images/ap-academy-logo.png"></a>
			
			  <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
				<li class="nav-item">
				  <a class="nav-link" target="_blank" href="<?php echo $CFG->wwwroot ; ?>/login/index.php">Login</a>
				</li>
				<li class="nav-item">
				  <a class="nav-link" target="_blank" href="<?php echo $CFG->wwwroot ; ?>/local/users/signup.php">Register</a>
				</li>
			  </ul>
	</div>
</nav>
