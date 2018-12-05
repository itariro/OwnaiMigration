<?php
	
	error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );
	$host = "localhost"; $user = "rajesh_stage"; $password = "bscWZI3k2jNeLWOA";
	$ownai_db = @mysqli_connect($host, $user, $password, 'ownai_db') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());
	$tengai_db = @mysqli_connect($host, $user, $password, 'tengai_db') OR die ("Could not connect to MySQL: ".  mysqli_connect_error());
		
?>
