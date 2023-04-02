<?php
include "config_db.php";
include "functions.php";
require "databaseManager.php";
session_start();
$response = ["code" => '48']; // Set the response to OK

// Check user login or not
if(!isset($_SESSION['loggedin'])){
	$response = ["code" => '5']; // Set the response to "User not Logged In"
	exit (json_encode($response));
}

$database = new DatabaseManager();
if ($database->init() == 2) {
	$response = ["code" => '2']; // Set the response to "Failed to connect to the Mysql Server"
	exit (json_encode($response));	
}

/**
 * Modify user information
 *
 * This function should be called by a CA or SA
 *
 * @param $_POST['id'] User to be modified
 * @param $_SESSION['BusinessId'] Business ID
 * @param $_SESSION['id'] CA/SA ID
 * @param $_POST['data'] JSON Information to modify
 */
if ($_POST["set_function"] == 0) {
	
	if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
		$response = ["code" => '34']; // Set the response to "No permission for this action"	
		exit (json_encode($response));
	}

	$obj = json_decode($_POST['data'], true);

	if(!isset($_POST["id"])) {
		$response = ["code" => '41']; // Set the response to "Update not OK"	
		exit (json_encode($response));	
	}

	$response;
	foreach ($obj as $key => $value) {
		$response = $database->update_userinfo($key, $_POST["id"], $value);
		if($response["code"] != 42) {
			exit (json_encode($response)); // Set the response to "Update not OK"		
		}
	}

	exit (json_encode($response));	
}

/**
 * Set user playrole
 *
 * This function should be called by a CA or SA
 *
 */
if ($_POST["set_function"] == 1) {
	
	if(!isset($_POST["id"])) {
		http_response_code(401);
		exit;
	}

	$response;
	$_SESSION["playrole"] = 1;
	$_SESSION["playrole_id"] = $_POST["id"];

	http_response_code(200);
	exit;
}

/**
 * Reset user playrole
 *
 * This function should be called by a CA or SA
 *
 */
if ($_POST["set_function"] == 2) {
	
	$response;
	$_SESSION["playrole"] = 0;
	$_SESSION["playrole_id"] = 0;
	
	$response["data"]["playrole"] = 0;
	$response["response"] = 8;

	exit (json_encode($response));	
}

/**
 * Remove an user from a business
 *
 *
 */
if ($_POST["set_function"] == 3) {
	$return["response"] = $database->remove_user_from_business($_POST["id"]);

	if ($return["response"] == 42) {
		http_response_code(200);
		exit();
	}

	http_response_code(400);
	exit (json_encode($return));	
}

/**
 * Create a new building for the current business
 *
 *
 */
if ($_POST["set_function"] == 4) {
	$return["response"] = $database->create_new_building($_POST["building_name"], $_POST["building_address"]);

	if ($return["response"] == 0) {
		http_response_code(200);
		exit();
	}

	http_response_code(400);
	exit (json_encode($return));	
}

/**
 * Create a new area for the current business
 */
if ($_POST["set_function"] == 5) {
	$array_to_pass = [
		"area_name" 			=> $_POST["area_name"],
		"area_parent_id" 		=> $_POST["area_parent_id"],
		"building_parent_id" 	=> $_POST["building_parent_id"],
		"whitelist" 			=> $_POST["whitelist"]
	];
	
	$return["response"] = $database->create_new_area($array_to_pass);

	if ($return["response"] == 0) {
		http_response_code(200);
		exit();
	}

	http_response_code(400);
	exit (json_encode($return));	
}

?>