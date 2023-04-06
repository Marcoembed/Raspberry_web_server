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

$my_id;
$my_role;
$my_businessid;
if ($_SESSION["playrole"] == 1) {
	$my_id = $_SESSION["playrole_id"];
} else {
	$my_id = $_SESSION["id"];
}

$my_businessid = $_SESSION["BusinessId"];
$my_role = $database->get_role_in_business($my_id, $my_businessid);
if (is_numeric($my_role) === FALSE) {

} else {
	http_response_code(400);
	$response["response"] = $information;
	if ($_POST["info_id"] != 13) {
		exit (json_encode($response));
	}
}

// Show the log access in the Customer Administrator Dashboard
if ($_POST["info_id"] == 0) {
	$return = $database->get_access_log("", 1);

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
	$filter; $filter_building;
	if ($_POST["filter"] !== null && !empty($_POST["filter"])) {
		if ($_POST["filter_building"] !== null && !empty($_POST["filter_building"])) {
			$filter = $_POST["filter"];
			$filter_building = $_POST["filter_building"];
		} else {
			$filter = $_POST["filter"];
			$filter_building = 0;
		}
	} else {
		if ($_POST["filter_building"] !== null && !empty($_POST["filter_building"])) {
			$filter = "";
			$filter_building = $_POST["filter_building"];
		} else {
			$filter = "";
			$filter_building = 0;
		}
	}
	
	$return = $database->get_people_from_business($_POST["page"], $filter, $filter_building);

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
	require "functions.php";
	$id;
	global $my_id;
	global $my_role;
	if ($_POST["id"] == 0) { // You are requesting information about yourself
		$id = $_SESSION["id"];
	} else {
		$id = $_POST["id"];
		if(!check_permission_role($my_id, $_SESSION["BusinessId"], "CO", $my_role)) {
			http_response_code(400);
			$return["response"] = 31;
			exit(json_encode($return));
		}
	}
	$return = $database->get_user_information($id);

	$allowed_fields = [
		//"birthdate",
		"name",
		"surname",
		"id_business_building",
		"business_email",
		"city",
		"country",
		"role",
		"sex",
		"street",
		"number",
		"telephone",
		"telephone_prefix",
		"zip"
	];

	if ($return["return"] == 0) {
		http_response_code(200);
		$allowed_data;

		foreach($allowed_fields as $value) {
			$to_client["data"][$value] = $return["data"][$value] == null ? "" : $return["data"][$value];
		}
		
		$to_client["data"]["birthdate"] = date('d/m/Y', strtotime($return["data"]["birthdate"]));
		exit (json_encode($to_client));
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
	global $my_id;
	$userid = $my_id;

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

// API used by the editbusiness page
// -- This function is used to retrieve the information related to the business buildings
if ($_POST["info_id"] == 8) {
	require "functions.php";
	$id;
	global $my_id;
	global $my_role;
	
	if(!check_permission_role($my_id, $_SESSION["BusinessId"], "CO", $my_role)) {
		http_response_code(400);
		$return["response"] = 31;
		exit(json_encode($return));
	}
	
	$my_business = $_SESSION["BusinessId"];
	
	$return = $database->get_business_info($my_business);

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

// API used by the editbusiness page
if ($_POST["info_id"] == 9) {
	$my_business = $_SESSION["BusinessId"];
	
	$return = $database->get_business_areas($my_business, $_POST["building_id"]);

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

// Get the information regarding area access
if ($_POST["info_id"] == 10) {
	$return = $database->get_people_in_building(1, 0);

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

// Get the information regarding an area (like area name)
if ($_POST["info_id"] == 11) {
	// $return = $database->get_people_in_building(1, 0);
	$return["return"] == 0;

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

// Get the number of workers for the current business
if ($_POST["info_id"] == 12) {
	$return = $database->get_numer_of_workers();

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = $return;
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Get the business the worker belong
if ($_POST["info_id"] == 13) {
	global $my_id;
	$return = $database->get_user_business($my_id);

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = $return;

		if ($response["data"]["number_of_business"] == 1) {
			$_SESSION['BusinessId']	= $response["data"]["last_businessID"];
			$_SESSION['role'] 		= $database->get_role_in_business($my_id, $_SESSION["BusinessId"]);
		}
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Get for which areas the user has permission
if ($_POST["info_id"] == 14) {
	$my_id = $_POST["id"];
	$return = $database->get_user_area_permission($my_id);

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = $return;
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

// Get for which areas the user does not have permission
if ($_POST["info_id"] == 15) {
	$user_id = $_POST["id"];
	global $my_businessid;
	$return = $database->get_user_area_no_permission($user_id, $my_businessid);

	if ($return["return"] == 0) {
		http_response_code(200);
		$response = $return;
		exit (json_encode($response));
	} else {
		http_response_code(401);
		$response = ["response" => $return["return"]];
		exit (json_encode($response));
	}
}

echo (json_encode($response));

?>