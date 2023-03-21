<?php

// Destroy
session_start();
session_destroy();
$_SESSION = [];

// Check user login or not
if(isset($_SESSION['email'])){
	exit('Logout failed.');
}

header('Location: login.html');

?>