<?php
require "databaseManager.php";
include "config_db.php";
session_start();

/*========== RESPONSE ========== */

// HTTP Response 200
    // reg0 = User is not logged in
	// reg1 = At least one region is empty
	// reg2 = Validation checked successfully
	// reg3 = Already existing business email
	// reg4 = Already existing company
	// reg5 = Already existing VAT
	// reg6 = Already existing business phone number
	// reg7 = Failed to connect to the Mysql Server
	// reg8 = owner name doesn't met the constraints
	// reg9 = owner surname doesn't met the constraints
	// reg10 = business name doesn't met the constraints
	// reg11 = business VAT doesn't met the constraints
	// reg12 = business email doesn't met the constraints
	// reg13 = business phone number doesn't met the constraints
	// reg14 = business address doesn't met the constraints
	// reg15 = Successfully Registered
	// reg16 = Registration failed
	// reg17 = No User ID found
	// reg18 = Error while intersecting information in the database
	// reg19 = Messagge contains information


/*========== CODE ========== */

if(!isset($_SESSION['loggedin'])){
	$response = ["status" => '200', "response" => 'reg0'];
	exit (json_encode($response));
}

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


if($_POST['info_id']==1){

// content empty check

	if  (   !isset(
		$_POST['owner_name'],
		$_POST['owner_surname'],
		$_POST['name'],
		$_POST['email'],
		$_POST['VAT'],
		$_POST['phone_number'],
		$_POST['city'],
		$_POST['state'],
		$_POST['zip'],
		$_POST['type_of_business'],
		$_POST['address'] 
	        ) 
	    ) 
	{
		// Could not get the data that should have been sent.
		$response = ["status" => '200', "response" => 'reg1'];
		exit (json_encode($response));
		// exit('Empty_Region');
	}

	// content validation with REGEX

	$client_info = array(
		$_POST['owner_name'],
		$_POST['owner_surname'],
		$_POST['name'],
		$_POST['VAT'],
		$_POST['email'],
		$_POST['phone_number'],
		$_POST['address'] 
	);


	// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
	if ($stmt = $con->prepare('SELECT regex_name, regex_surname, regex_name, regex_VAT, regex_email, regex_phone_number, regex_address FROM config')) {
		// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
		$stmt->execute();
		// Store the result so we can check if the account exists in the database.
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$stmt->bind_result($regex[0], $regex[1], $regex[2], $regex[3], $regex[4], $regex[5], $regex[6]);
			$stmt->fetch();
			foreach($client_info as $i=>$value){
				if(preg_match($regex[$i], $value)){
					$response = ["status" => '200', "response" => 'reg2'];
				} else {
					$response = ["status" => '200', "response" => 'reg'.$i+8];
					//$response = ["status" => '200', "response" => $value];
					exit (json_encode($response));
				}
			}
		$stmt->close();
	}
	
	/* check if name, email, phone number, VAT are already used */		

	// sub-array for the database check

	$checkbusinessinfo =array(
		$_POST['email'],
		$_POST['name'],
		$_POST['VAT'],
		$_POST['phone_number'],
	);

	$businessinfo =array(
		'email',
		'name',
		'VAT',
		'phone_number',
	);

	foreach($checkbusinessinfo as $i=>$value){
		$return = $database->check_businessinfo_registration($businessinfo[$i], $value);
			if ($return == 0) {
				$response = ["status" => '200', "response" => 'reg'.$i+3];//$value];
				exit (json_encode($response));
			} else {
				$response = ["status" => '200', "response" => '0'];
			}
		}
	}

	/* insert userinfo passed by the client in the database */

	$insert_businessinfo= array(
		"name",
		"VAT",
		"email",
		"phone_number",
		"city",
		"state",
		"zip",
        "address"
	);

	$return = $database->add_info('business_info',$insert_businessinfo);
		if ($return == 0){
			$response = ["status" => '200', "response" => 'reg'.$i+9];
			exit (json_encode($response));
		} else {
			$response = ["status" => '200', "response" => '0'];
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