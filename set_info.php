<?php
include "config_db.php";
include "functions.php";
session_start();
$response = ["status" => '200', "response" => '8'];

// Check user login or not
if(!isset($_SESSION['loggedin'])){
	$response = ["status" => '200', "response" => '5'];
	exit (json_encode($response));
}

// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	$response = ["status" => '200', "response" => '2'];
	exit (json_encode($response));
}

function update_userinfo($field, $userid, $newvalue) {
	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('UPDATE `userinfo` SET `?` = "?" WHERE `userinfo`.`id` = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('ssi', $field, $newvalue, $userid);
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$response = ["status" => '200', "response" => '41'];		
		} else {
			$response = ["status" => '200', "response" => '42'];
			exit (json_encode($response));
		}

		exit (json_encode($response));
		$stmt->close();
	}
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
		$response = ["status" => '200', "response" => '34'];
		exit (json_encode($response));
	}

	$obj = json_decode($_POST['data']);

	if(!isset(obj["id"])) {
		$response = ["status" => '200', "response" => '41'];	
	}

	if(isset(obj["name"])) {
		update_userinfo("name", obj["name"], $_POST["id"]);
	}

	if(isset(obj["surname"])) {
		
	}
}

?>