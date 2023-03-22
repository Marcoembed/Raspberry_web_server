<?php
require "databaseManager.php";
session_start();
$response = ["status" => '200', "response" => '8'];

// Check user login or not
if(!isset($_SESSION['loggedin'])){
	$response = ["status" => '200', "response" => '5'];
	exit (json_encode($response));
}

$database = new DatabaseManager();
if ($database->init() == 2) {
	$response = ["status" => 200, "code" => 2]; // Set the response to "Failed to connect to the Mysql Server"
	exit (json_encode($response));	
}

// Show the log access in the Customer Administrator Dashboard
if ($_POST["info_id"] == 0) {
	$return = $database->get_access_log();

	if ($return["return"] == 0) {
		$response = ["status" => '200', "response" => '8', "data" => $return["data"]];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Show CO/USR in the Workers Table in the Customer Administrator Dashboard
if ($_POST["info_id"] == 1) {
	$return = $database->get_CO_USR_from_business();

	if ($return["return"] == 0) {
		$response = ["status" => '200', "response" => '8', "data" => $return["data"], "amount" => $return["amount"]];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => $return["return"]];
		exit (json_encode($response));
	}
}

// API returns the username of the user
if ($_POST["info_id"] == 2) {
	$return = $database->get_username();

	if ($return["return"] == 0) {
		$response = ["status" => '200', "response" => '8', "username" => $return["username"]];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => $return["return"]];
		exit (json_encode($response));
	}
}

// API used by the viewprofile page
// API also used by editprofile page
if ($_POST["info_id"] == 3) {
	$return = $database->get_user_information($_POST["id"]);

	if ($return["return"] == 0) {
		$response = ["status" => '200', "response" => '8', "data" => $return["data"]];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Get current business name
if ($_POST["info_id"] == 4) {
	$return = $database->get_business_name();

	if ($return["return"] == 0) {
		$response = ["status" => '200', "response" => '8', "business_name" => $return["business_name"]];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => $return["return"]];
		exit (json_encode($response));
	}
}


// Check if this person has the permission 
if ($_POST["info_id"] == 5) {
	require_once "functions.php";
	if(check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], $_POST['role'], $_SESSION['role'])) 
	{
		$response = ["status" => '200', "response" => '35'];
		exit (json_encode($response));
	} else {
		$response = ["status" => '200', "response" => '34'];
		exit (json_encode($response));
	}
}

echo (json_encode($response));

?>