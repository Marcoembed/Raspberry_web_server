<?php
include "config_db.php";
session_start();

/*========== RESPONSE ========== */

// HTTP Response 200
	// reg0 = Already existing user
	// reg1 = At least one reagion is empty
	// reg2 = Failed to connect to the Mysql Server
	// reg3 = name doesn't met the constraints
	// reg4 = surname doesn't met the constraints
	// reg5 = email doesn't met the constraints
	// reg6 = username doesn't met the constraints
	// reg7 = password1 doesn't met the constraints
	// reg8 = codice fiscale doesn't met the constraints
	// reg9 = phone number doesn't met the constraints
	// reg10 = address doesn't met the constraints
	// reg11 = password2 is different from password1
	// reg12 = Successfully Registered
	// reg13 = -- User not logged in
	// reg14 = -- No User ID found
	// reg15 = Error while intersecting information in the database
	// reg16 = Messagge contains information


/*========== FUNCTION ========== */

function password_comparison($first_password, $second_password){
    if ($first_password === $second_password) {
        return 1;
    }
    return 0;
}

function empty_region_check(){ 
	if  (   !isset(
	            $_POST['name'],
	            $_POST['surname'],
	            $_POST['email'], 
	            $_POST['password1'],
	            $_POST['password2'],
	            $_POST['codice_fiscale'],
	            $_POST['phone_number'],
	            $_POST['address'] 
	        ) 
	    ) 
	{
		// Could not get the data that should have been sent.
		$response = ["status" => '200', "response" => 'reg1'];
		exit (json_encode($response));
		// exit('Empty_Region');
	}
}


/*========== CODE ========== */


// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// content check with REGEX

$client_info = array($_POST['name'], $_POST['surname'],$_POST['email'],$_POST['username'],$_POST['password'],$_POST['codice_fiscale'],$_POST['phone_number'], $_POST['address']);

// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT regex_name, regex_surname, regex_email, regex_username, regex_password, regex_codice_fiscale, regex_phone_number, regex_address FROM config')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($regex[0], $regex[1], $regex[2], $regex[3], $regex[4], $regex[5], $regex[6], $regex[7]);
		$stmt->fetch();
		for($i=0; $i<8; $i++){
			if(preg_match($client_info[i], $regex[i])){

				echo 'registration_success';
			} else {

				echo 'registration failed';
			}
		}
	}
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
			echo 'login_success';
		} else {
			// Incorrect password
			echo 'wrong_username_password';
		}
	} else {
		// Incorrect username
		echo 'wrong_username_password';
	}
	$stmt->close();
}

?>