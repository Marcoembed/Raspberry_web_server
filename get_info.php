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
		http_response_code(200);
		$response = ["data" => $return["data"]];
		exit (json_encode($response));
	} else {
		http_response_code(200);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Show CO/USR in the Workers Table in the Customer Administrator Dashboard
if ($_POST["info_id"] == 1) {
	$return = $database->get_people_from_business($_POST["page"]);

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = ["data" => $return["data"], "amount" => $return["amount"]];
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// API returns the username of the user
if ($_POST["info_id"] == 2) {
	$return = $database->get_username();

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = ["response" => '8', "username" => $return["username"]];
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// API used by the viewprofile page
// API also used by editprofile page
if ($_POST["info_id"] == 3) {
	$return = $database->get_user_information($_POST["id"]);

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = ["data" => $return["data"]];
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Get current business name
if ($_POST["info_id"] == 4) {
	$return = $database->get_business_name();

	if ($return["return"] == 0) {
		http_response_code("200");
		$response = ["business_name" => $return["business_name"]];
		exit (json_encode($response));
	} else {
		http_response_code("401");
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}


// Check if this person has the permission 
if ($_POST["info_id"] == 5) {
	require_once "functions.php";
	if(check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], $_POST['role'], $_SESSION['role'])) 
	{
		http_response_code("200");
		exit;
	} else {
		http_response_code("401");
		exit;
	}
}

// Check if user is playing any role 
// if yes, Get name, surname and role of the user with id playrole_id
if ($_POST["info_id"] == 6) {
	$return = ["playrole" => $_SESSION['playrole']];
	if ($return["playrole"] == 1) {
		$information = $database->get_user_information($_SESSION["playrole_id"]);
		if (!$information["return"]) {
			$return["name"] 	= $information["data"]["name"];
			$return["surname"] 	= $information["data"]["surname"];
			$return["role"] 	= $information["data"]["role"];
		}
	}

	$response = ["status" => '200', "response" => '8', "data" => $return];
}

// Check the current role in the Business
// This can be used by the client to show the right page
if ($_POST["info_id"] == 7) {

	$userid;
	if($_SESSION["playrole"] == 1) {
		$userid = $_SESSION["playrole_id"];
	} else {
		$userid = $_SESSION["id"];
	}

	$information = $database->get_role_in_business($userid, $_SESSION["BusinessId"]);
	if (is_numeric($information) === FALSE) {
		http_response_code(200);
		$response["role"] 	= $information;
		$response["response"] = '8';
		exit (json_encode($response));
	} else {
		http_response_code(200);
		$response["response"] = $information;
		exit (json_encode($response));
	}
}

echo (json_encode($response));

?>