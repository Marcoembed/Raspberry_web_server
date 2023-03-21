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
	// $sql = "";
	// $result = $con->query($sql);

	// if ($result->num_rows > 0) {
    //     // output data of each row
    //     $i = 0;
    //     while($row = $result->fetch_assoc()) {
    //         $i++;
    //     }
	// } else {
	// 	$response = ["status" => '200', "response" => '7'];
	// 	exit (json_encode($response));
	// }
}

?>