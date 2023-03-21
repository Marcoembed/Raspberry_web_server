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

function check_worker_belong_to_business($id, $businessid) {
	global $con;
	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT * FROM `business_people` WHERE `UserID` = ? AND `BusinessID` = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('ii', $id, $businessid);
		$stmt->execute();
		
		$result = $stmt->get_result();
		if($result->num_rows === 0) {
			// ID Not found / ID found but does not belong to this business
			$response = ["status" => '200', "response" => '31'];
			exit (json_encode($response));
		}
		else {
			return 1;
		}
		return 0;
		$stmt->close();
	}
}


/**
 * Summary.
 *
 * Description.
 *
 * @since x.x.x
 *
 * @see Function/method/class relied on
 * @link URL
 * @global type $varname Description.
 * @global type $varname Description.
 *
 * @param type $var Description.
 * @param type $var Optional. Description. Default.
 * @return type Description.
 */
function check_worker_is_CO_or_USR($id, $businessid) {
	global $con;
	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT * FROM `business_people` WHERE `UserID` = ? AND `BusinessID` = ? AND (`role` = "USR" OR `role` = "CO")')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('ii', $id, $businessid);
		$stmt->execute();
		
		$result = $stmt->get_result();
		if($result->num_rows === 0) {
			// Probably the user is not a USR or CO. 
			// We know the user exist because check_worker_belong_to_business() should be called before. 
			$response = ["status" => '200', "response" => '32'];
			exit (json_encode($response));
		}
		else {
			return 1;
		}
		return 0;
		$stmt->close();
	}
}

function get_role_in_business($id, $businessid) {
	global $con;
	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT `role` FROM `business_people` WHERE `UserID` = ? AND `BusinessID` = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('ii', $id, $businessid);
		$stmt->execute();
		
		$result = $stmt->get_result();
		if($result->num_rows === 0) {
			//Something went wrong. 
			$response = ["status" => '200', "response" => '33'];
			exit (json_encode($response));
		}
		else {
			$row = $result->fetch_assoc();
			return $row["role"];
		}
		return 0;
		$stmt->close();
	}
}

// Show the log access in the Customer Administrator Dashboard
if ($_POST["info_id"] == 0) {
	
	if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
		$response = ["status" => '200', "response" => '34'];
		exit (json_encode($response));
	}
	$sql = "SELECT `id`, `Time`, `Area`, `PersonID`, `action` FROM `business_access_log` WHERE `BusinessID` = ".$_SESSION['BusinessId'];
	$result = $con->query($sql);

	if ($result->num_rows > 0) {
		// output data of each row
		$i = 0;
		while($row = $result->fetch_assoc()) {
			$sql = "SELECT `username` FROM `userinfo` WHERE `id` = ".$row['PersonID'];
			$result2 = $con->query($sql);
			$row2 = $result2->fetch_assoc();

			$array = ["id" => $row["id"], "Time" => $row["Time"], "Area" => $row["Area"], "PersonID" => $row2["username"], "action" => $row["action"]];
			$response["data"][$i] = $array;
			$i++;
		}
	} else {
		$response = ["status" => '200', "response" => '7'];
		exit (json_encode($response));
	}
}

// Show CO/USR in the Workers Table in the Customer Administrator Dashboard
if ($_POST["info_id"] == 1) {
	if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
		$response = ["status" => '200', "response" => '34'];
		exit (json_encode($response));
	}
	$sql = "SELECT `UserID` FROM `business_people` WHERE `BusinessID` = ".$_SESSION['BusinessId']." AND (`role` = 'CO' OR `role` = 'USR')";
	$result = $con->query($sql);

	if ($result->num_rows > 0) {
		// output data of each row
		$i = 0;
		while($row = $result->fetch_assoc()) {
			$sql = "SELECT `username`, `name`, `surname`, `email` FROM `userinfo` WHERE `id` = ".$row['UserID'];
			$result2 = $con->query($sql);
			$row2 = $result2->fetch_assoc();

			$array = [	"username" => $row2["username"], 
						"name" => $row2["name"], 
						"surname" => $row2["surname"], 
						"email" => $row2["email"],
						"id" => $row['UserID']
					];
			$response["data"][$i] = $array;
			$i++;
		}
	} else {
		$response = ["status" => '200', "response" => '7'];
		exit (json_encode($response));
	}
}

// API returns the username of the user
if ($_POST["info_id"] == 2) {
	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT username FROM userinfo WHERE id = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('s', $_SESSION['id']);
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($username);
			$stmt->fetch();
			$response["username"] = $username;
		} else {
			// Incorrect username
			$response = ["status" => '200', "response" => '6'];
			exit (json_encode($response));
		}
		$stmt->close();
	}
}

// API used by the viewprofile page
// API also used by editprofile page
if ($_POST["info_id"] == 3) {
	if(!check_worker_belong_to_business($_POST["id"], $_SESSION["BusinessId"])) {
		$response = ["status" => '200', "response" => '31'];
		exit (json_encode($response));
	}

	if(!check_worker_is_CO_or_USR($_POST["id"], $_SESSION["BusinessId"])) {
		$response = ["status" => '200', "response" => '32'];
		exit (json_encode($response));
	}
	
	$role = get_role_in_business($_POST["id"], $_SESSION["BusinessId"]);
	if(!$role) {
		$response = ["status" => '200', "response" => '33'];
		exit (json_encode($response));
	}

	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT `username`, `name`, `surname`, `email` FROM userinfo WHERE id = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('s', $_POST['id']);
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($username, $name, $surname, $email);
			$stmt->fetch();
			$response["data"]["username"] 	= $username;
			$response["data"]["name"] 		= $name;
			$response["data"]["surname"] 	= $surname;
			$response["data"]["email"] 		= $email;
			$response["data"]["role"] 		= $role;
		} else {
			// Incorrect username
			$response = ["status" => '200', "response" => '6'];
			exit (json_encode($response));
		}
		$stmt->close();
	}
}

// Get current business name
if ($_POST["info_id"] == 4) {

	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT `name` FROM business_info WHERE id = ?')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->bind_param('s', $_SESSION['BusinessId']);
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($business_name);
			$stmt->fetch();
			$response["business_name"] 	= $business_name;
		} else {
			// Incorrect username
			$response = ["status" => '200', "response" => '6'];
			exit (json_encode($response));
		}
		$stmt->close();
	}
}


// Check if this person has the permission 
if ($_POST["info_id"] == 5) {

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