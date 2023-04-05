<?php
require "databaseManager.php";
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
	// reg9 = username doesn't met the constraints
	// reg10 = password doesn't met the constraints
	// reg11 = email doesn't met the constraints
	// reg12 = codice fiscale doesn't met the constraints
	// reg13 = phone number doesn't met the constraints
	// reg14 = address doesn't met the constraints
	// reg15 = passwords do not match
	// reg16 = Successfully Registered
	// reg17 = Registration failed
	// reg18 = No User ID found
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

$database = new DatabaseManager();

if ($database->init() == 2) {
	$response = ["code" => '2']; // Set the response to "Failed to connect to the Mysql Server"
	exit (json_encode($response));	
}

// content empty check

if($_POST['info_id']==1){

	if  (   !isset(
		$_POST['name'],
		$_POST['surname'],
		$_POST['email'],
		$_POST['username'],
		$_POST['password'],
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
		$_POST['username'],
		$_POST['password'],
		$_POST['email'],
		$_POST['codice_fiscale'],
		$_POST['phone_number'],
		$_POST['address'] 
	);


	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT regex_name, regex_surname, regex_username, regex_password, regex_email, regex_codice_fiscale, regex_phone_number, regex_address FROM config')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($regex[0], $regex[1], $regex[2], $regex[3], $regex[4], $regex[5], $regex[6], $regex[7]);
			$stmt->fetch();
			foreach($client_info as $i=>$value){
				if(preg_match($regex[$i], $value)){
					$response = ["status" => '200', "response" => 'reg1'];
				} else {
					$response = ["status" => '200', "response" => 'reg'.$i+7];
					//$response = ["status" => '200', "response" => $value];
					exit (json_encode($response));
				}
			}
		$stmt->close();
	}
	
	// comparison check between password and password2

	if(!password_comparison($_POST['password'], $_POST['password2'])){
		$response = ["status" => '200', "response" => 'reg15'];
		exit (json_encode($response));
	}


	/* check if email, username, phone number, codice fiscale are already used */		

	// sub-array for the database check

	$checkuserinfo =array(
		$_POST['email'],
		$_POST['username'],
		$_POST['codice_fiscale'],
		//$_POST['phone_number'],
	);

	$userinfo =array(
		'email',
		'username',
		'codice_fiscale',
		//'phone_number',
	);

	foreach($checkuserinfo as $i=>$value){
		$return = $database->check_userinfo_registration($userinfo[$i], $value);
			if ($return == 0) {
				$response = ["status" => '200', "response" => 'reg'.$i+2];//$value];
				exit (json_encode($response));
			} else {
				$response = ["status" => '200', "response" => '0'];
			}
		}
	}

	/* insert userinfo passed by the client in the database */

	$insert_userinfo= array(
		"name",
		"surname",
		"username",
		"password",
		"email",
		"codice_fiscale"
	);

	$return = $database->add_userinfo('userinfo',$insert_userinfo);
		if ($return == 0){
			$response = ["status" => '200', "response" => 'reg'.$i+17];
			echo "error";
			exit (json_encode($response));
		} else {
			$response = ["status" => '200', "response" => '0'];
			echo "ok";
		}
}

if ($_POST["info_id"] == 0) {
	$return = $database->return_regex();

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

exit (json_encode($response));

?>