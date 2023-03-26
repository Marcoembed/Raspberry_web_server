<?php
include "config_db.php";
session_start();

function password_check($password_try, $password_real) {
    if ($password_try === $password_real) {
        return 1;
    }
    return 0;
}

// HTTP Response 200
	// 0 = Already Logged In
	// 1 = Email or password field are empty
	// 2 = Failed to connect to the Mysql Server
	// 3 = Wrong username or password
	// 4 = Successfully Logged In
	// 5 = User not logged in
	// 6 = No User ID found
	// 7 = Error while intersecting information in the database
	// 8 = Messagge contains information

	// -- get_info.php --
	// 31 = No Permission for this action
	// 32 = No Permission for this action (person id CA/SA)
	// 33 = Error while intersecting information in the database
	// 34 = No Permission for this action
	// 35 = Permission Granted for this action
	// 36 = Passed wrong data
	// 37 = No Logs Found

	// -- set_info.php --
	// 41 = Update non OK
	// 42 = Update OK
	// 43 = Error while intersecting information in the database (1)
	// 44 = Error while intersecting information in the database (2)
	// 48 = OK

// Check user login or not
if(isset($_SESSION['loggedin'])){
	$response = ["status" => '200', "response" => '0'];
	exit (json_encode($response));
}

if ( !isset($_POST['email'], $_POST['password']) ) {
	// Could not get the data that should have been sent.
	$response = ["status" => '200', "response" => '1'];
	exit (json_encode($response));
}

// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	$response = ["status" => '200', "response" => '2'];
	exit (json_encode($response));
}

// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id, password FROM userinfo WHERE email = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['email']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($id, $password);
		$stmt->fetch();
		// Account exists, now we verify the password.
		// Note: remember to use password_hash in your registration file to store the hashed passwords.
		if (password_check($_POST['password'], $password)) {
			// Verification success! User has logged-in!
			// Create sessions, so we know the user is logged in, they basically act like cookies but remember the data on the server.
			session_regenerate_id();
			$_SESSION['loggedin'] 	= TRUE;
			$_SESSION['id'] 		= $id;
			
			$_SESSION['BusinessId']	= 1;
			$_SESSION['role'] 		= "CA";
			
			$_SESSION['playrole']	= 0;
			// $_SESSION['playrole_id'] = 2;
			
			$response = ["status" => '200', "response" => '4'];
			
		} else {
			// Incorrect password
			$response = ["status" => '200', "response" => '3'];
			exit (json_encode($response));
		}
	} else {
		// Incorrect username
		$response = ["status" => '200', "response" => '3'];
		exit (json_encode($response));
	}

	exit (json_encode($response));
	$stmt->close();
}

?>