<?php
include "config_db.php";
session_start();

/*========== RESPONSE ========== */

// HTTP Response 200
	// reg0 = At least one reagion is empty
	// reg1 = Validation checked successfully
	// reg2 = Already existing email
	// reg3 = Already existing username
	// reg4 = Already existing codice fiscale
	// reg5 = Already existing phone number
	// reg6 = Failed to connect to the Mysql Server
	// reg7 = name doesn't met the constraints
	// reg8 = surname doesn't met the constraints
	// reg9 = email doesn't met the constraints
	// reg10 = username doesn't met the constraints
	// reg11 = password1 doesn't met the constraints
	// reg12 = codice fiscale doesn't met the constraints
	// reg13 = phone number doesn't met the constraints
	// reg14 = address doesn't met the constraints
	// reg15 = passwords do not match
	// reg16 = Successfully Registered
	// reg17 = -- User not logged in
	// reg18 = -- No User ID found
	// reg19 = Error while intersecting information in the database
	// reg20 = Messagge contains information


/*========== FUNCTION ========== */

function password_comparison($first_password, $second_password){
    if ($first_password === $second_password) {
        return 1;
    }
    return 0;
}


/*========== CODE ========== */


// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// client variables information


// content empty check

if  (   !isset(
	$_POST['name'],
	$_POST['surname'],
	$_POST['email'],
	$_POST['username'],
	$_POST['password1'],
	$_POST['password2'],
	$_POST['codice_fiscale'],
	$_POST['phone_number'],
	$_POST['address'] 
        ) 
    ) 
{
	// Could not get the data that should have been sent.
	$response = ["status" => '200', "response" => 'reg0'];
	exit (json_encode($response));
	// exit('Empty_Region');
}

// content validation with REGEX

$client_info = array(
	$_POST['name'],
	$_POST['surname'],
	$_POST['email'],
	$_POST['username'],
	$_POST['password1'],
	$_POST['password2'],
	$_POST['codice_fiscale'],
	$_POST['phone_number'],
	$_POST['address'] 
);

// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT regex_name, regex_surname, regex_email, regex_username, regex_password1, regex_password2, regex_codice_fiscale, regex_phone_number, regex_address FROM config')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($regex[0], $regex[1], $regex[2], $regex[3], $regex[4], $regex[5], $regex[6], $regex[7], $regex[8]);
		// $stmt->fetch();
		for($i=0; $i<9; $i++){
			if(preg_match($client_info[i], $regex[i])){
				$response = ["status" => '200', "response" => 'reg1'];
			} else {
				switch(i){
					case 1:
						echo 'name does not match the requirements';
						$response = ["status" => '200', "response" => 'reg4'];
						exit (json_encode($response));
					break;
					case 2:
						echo 'surname does not match the requirements';
						$response = ["status" => '200', "response" => 'reg5'];
						exit (json_encode($response));
					break;
					case 3:
						echo 'email does not match the requirements';
						$response = ["status" => '200', "response" => 'reg6'];
						exit (json_encode($response));
					break;
					case 4:
						echo 'username does not match the requirements';
						$response = ["status" => '200', "response" => 'reg7'];
						exit (json_encode($response));
					break;
					case 5:
						echo 'password does not match the requirements';
						$response = ["status" => '200', "response" => 'reg8'];
						exit (json_encode($response));
					break;
					case 6:
						echo 'password does not match the requirements';
						$response = ["status" => '200', "response" => 'reg8'];
						exit (json_encode($response));
					break;
					case 7:
						echo 'codice fiscale does not match the requirements';
						$response = ["status" => '200', "response" => 'reg9'];
						exit (json_encode($response));
					break;
					case 8:
						echo 'phone number does not match the requirements';
						$response = ["status" => '200', "response" => 'reg10'];
						exit (json_encode($response));
					break;
					case 9:
						echo 'address does not match the requirements';
						$response = ["status" => '200', "response" => 'reg11'];
						exit (json_encode($response));
					break;
						
				}
			}
		}

	$stmt->close();
}

// comparison check between password1 and password2

if(!password_comparison($_POST['password1'], $_POST['password2'])){
	echo 'passwords do not match'
	$response = ["status" => '200', "response" => 'reg12'];
	exit (json_encode($response));
}

/* check if email, username, phone number, codice fiscale are already used */		

// check if the email exit 
		
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id FROM userinfo WHERE email = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['email']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($id);
		$stmt->fetch();
		//Account already exist
		$response = ["status" => '200', "response" => 'reg2'];
		exit (json_encode($response));
		
	}
	$stmt->close();
}

// check if the username exit 
		
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id FROM userinfo WHERE username = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['username']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($id);
		$stmt->fetch();
		//Account already exist
		$response = ["status" => '200', "response" => 'reg3'];
		exit (json_encode($response));
		
	}
	$stmt->close();
}

// check if the codice_fiscale exit 
		
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id FROM userinfo WHERE codice_fiscale = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['codice_fiscale']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($id);
		$stmt->fetch();
		//Account already exist
		$response = ["status" => '200', "response" => 'reg4'];
		exit (json_encode($response));
		
	}
	$stmt->close();
}

// check if the phone_number exit 
		
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id FROM userinfo WHERE phone_number = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['phone_number']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($id);
		$stmt->fetch();
		//Account already exist
		$response = ["status" => '200', "response" => 'reg5'];
		exit (json_encode($response));
		
	}
	$stmt->close();
}



?>
