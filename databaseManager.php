<?php
    class DatabaseManager {   
        private $con;

        public function init() {
            require 'config_db.php'; 

            // Try and connect to the DB
            $this->con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
            if ( mysqli_connect_errno() ) {
                return (2); // Set the response to "Failed to connect to the Mysql Server"
            }
        }

        /**
         * Update the user information.
         *
         * This function is intedded to be called by a CA user.
         *
         * @param text  $field    Description.
         * @param int   $userid   Description.
         * @param text  $newvalue Description.
         * @return array Description.
         */
        public function update_userinfo($field, $userid, $newvalue) {
            $allowed_field = array("name", "surname", "username", "email");
            $allowed_role = array("role");
            
            if (array_search($field, $allowed_field) !== FALSE ) {
            
                if ($stmt = $this->con->prepare('UPDATE userinfo SET '.$field.'=? WHERE id=?')) {
                    // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                    $stmt->bind_param('si', $newvalue, $userid);
                    $stmt->execute();
                    
                    if(mysqli_affected_rows($this->con))
                        $response = ["code" => '42']; // Set the response to "Update OK"
                    else 
                        $response = ["code" => '41']; // Set the response to "Update not OK"		 

                } else {
                    $response = ["code" => '43'];
                }
            } else if ($field == "role") { // Check for ROLE changes
                $user_business_role = $this->get_role_in_business($userid, $_SESSION["BusinessId"]);
                if ($user_business_role == '33') { // Something went wrong
                    $response = ["code" => '43'];
                } else if ($user_business_role == "CA" || $user_business_role == "SA") { 
                    // Something went wrong. You can't modify the role to a person that is SA or CA
                    // .. because this function is intended to be called by a CA.
                    $response = ["code" => '41'];
                } else {
                    $return_val = $this->set_role_in_business($userid, $_SESSION["BusinessId"], $newvalue);
                    $response = ["code" => $return_val];    
                }
            } else {
                $response = ["code" => '44'];
            }
            
            return $response;
        }

        /**
         * Get Role of an User in a Business.
         *
         * Description.
         *
         * @param int $id           Description.
         * @param int $businessid   Description.
         * @return array Description.
         */
        public function get_role_in_business($id, $businessid) {
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT `role` FROM `business_people` WHERE `UserID` = ? AND `BusinessID` = ?')) {
                // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('ii', $id, $businessid);
                $stmt->execute();
                
                $result = $stmt->get_result();
                if($result->num_rows == 0) {
                    //Something went wrong. 
                    return 33;
                }
                else {
                    $row = $result->fetch_assoc();
                    return $row["role"];
                }
                return 33;
                $stmt->close();
            }
        }
        
        /**
         * Set Role of an User in a Business.
         *
         * Description.
         *
         * @param int   $id             Description.
         * @param int   $businessid     Description.
         * @param text  $role           Description.
         * 
         * @return array Description.
         */
        public function set_role_in_business($id, $businessid, $role) {
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            $allowed_role = array("CO", "USR");
            if (array_search($role, $allowed_role) !== FALSE ) {
                if ($stmt = $this->con->prepare('UPDATE `business_people` SET `role` = "'.$role.'" WHERE `UserID` = ? AND `BusinessID` = ?')) {
                    // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                    $stmt->bind_param('ii', $id, $businessid);
                    $stmt->execute();
                    
                    if(mysqli_affected_rows($this->con))
                        $response = 42; // Set the response to "Update OK"
                    else 
                        $response = 41; // Set the response to "Update not OK"

                } else {
                    $response = 41; // Set the response to "Update not OK"	
                }
            } else {
                $response = 41; // Set the response to "Update not OK"	
            }
            
            $stmt->close();
            return $response;
        }
        
        /**
         * Check if an user belong to a specific business.
         *
         * Description.
         *
         * @param int   $id             Description.
         * @param int   $businessid     Description.
         * 
         * @return int 1 if OK, otherwise 0.
         */
        function check_worker_belong_to_business($id, $businessid) {
            if ($stmt = $this->con->prepare('SELECT * FROM `business_people` WHERE `UserID` = ? AND `BusinessID` = ?')) {
                // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('ii', $id, $businessid);
                $stmt->execute();
                
                $result = $stmt->get_result();
                if($result->num_rows === 0) {
                    // ID Not found / ID found but does not belong to this business
                    return 0;
                }
                else {
                    return 1;
                }
                
                return 0;
                $stmt->close();
            }
        }

        /**
         * Check if an user is CO or USR in the business.
         *
         * Description.
         *
         * @param int   $id             Description.
         * @param int   $businessid     Description.
         * 
         * @return int 1 if OK, otherwise 0.
         */
        function check_worker_is_CO_or_USR($id, $businessid) {
            if ($stmt = $this->con->prepare('select * from `business_people` where `userid` = ? and `businessid` = ? and (`role` = "usr" or `role` = "co")')) {
                // bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('ii', $id, $businessid);
                $stmt->execute();
                
                $result = $stmt->get_result();
                if($result->num_rows === 0) {
                    // probably the user is not a usr or co. 
                    // we know the user exist because check_worker_belong_to_business() should be called before. 
                    return 0;
                }
                else {
                    return 1;
                }
                return 0;
                $stmt->close();
            }
        }

        /**
         * Check if the registration info are already present in the database
         * 
         * Description
         * 
         * @param int $userinfo Description.
         * @return int 1 if OK, otherwise 0
         */
        public function check_userinfo_registration($userinfo, $uservalue){
            if ($stmt = $this->con->prepare('select * from userinfo where `'.$userinfo.'` = ?')) {
                // bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('s', $uservalue);
                $stmt->execute();
                
                $stmt->store_result();
                if($stmt->num_rows === 0) {
                    // userinfo used for the first time
                    return 1;
                }
                else {
                    // user already registered in the database
                    return 0;
                }
                return 0;
                $stmt->close();
            }
        }

        /**
         * Get the log of the access in a Business.
         *
         * Description.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_access_log() {
            require_once 'functions.php'; 
            
            if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
                return 34;
            }
            
            $sql =  "SELECT `id`, `Time`, `Area`, `PersonID`, `action` FROM `business_access_log` WHERE ".
                    "`BusinessID` = ".$_SESSION['BusinessId'];

            $result = $this->con->query($sql);
            $response;
        
            if ($result->num_rows > 0) {
                // output data of each row
                $i = 0;
                while($row = $result->fetch_assoc()) {
                    $sql = "SELECT `username` FROM `userinfo` WHERE `id` = ".$row['PersonID'];
                    $result2 = $this->con->query($sql);
                    $row2 = $result2->fetch_assoc();
        
                    $array = [  
                                "id"        => $row["id"], 
                                "Time"      => $row["Time"], 
                                "Area"      => $row["Area"], 
                                "PersonID"  => $row2["username"], 
                                "action"    => $row["action"]];

                    $response["data"][$i] = $array;
                    $response["return"] = 0;
                    $i++;
                }
            } else {
                $response["return"] = 7;
            }

            return $response;
        }

        /**
         * Get CO and USR workers information from business.
         *
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_CO_USR_from_business() {
            require_once 'functions.php'; 
            if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
                $response["return"] = 34;
                return $response;
            }

            $sql = "SELECT `UserID`, `role` FROM `business_people` WHERE `BusinessID` = ".$_SESSION['BusinessId']." AND (`role` = 'CO' OR `role` = 'USR')";
            $result = $this->con->query($sql);
        
            if ($result->num_rows > 0) {
                // output data of each row
                $i = 0;
                while(($row = $result->fetch_assoc()) && $i < 9) {
                    $sql = "SELECT `username`, `name`, `surname`, `email` FROM `userinfo` WHERE `id` = ".$row['UserID'];
                    $result2 = $this->con->query($sql);
                    if ($result2->num_rows > 0) {
                        $row2 = $result2->fetch_assoc();
        
                        $array = [	"username" => $row2["username"], 
                                    "name" => $row2["name"], 
                                    "surname" => $row2["surname"], 
                                    "email" => $row2["email"],
                                    "id" => $row['UserID'],
                                    "role" => $row['role']
                                ];

                        $response["data"][$i] = $array;
                        $response["return"] = 0;
                        $i++;
                    } else {
                        $array = [	"username" => null, 
                                    "name" => null, 
                                    "surname" => null, 
                                    "email" => null,
                                    "id" => null
                                ];
                    }
                }
                $response["amount"] = $result->num_rows; 
            } else {
                $response["return"] = 7;
                return $response;
            }
            return $response;
        }

        /**
         * Get the username of user.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_username() {
            require_once 'functions.php'; 
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT username FROM userinfo WHERE id = ?')) {
                $stmt->bind_param('s', $_SESSION['id']);
                $stmt->execute();
                
                // Store the result so we can check if the account exists in the database.
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($username);
                    $stmt->fetch();
                    $response["username"] = $username;
                    $response["return"] = 0;
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                }
                
                return $response;
                $stmt->close();
            }
        }

        /**
         * Get user information.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_user_information($id) {
            $response["return"] = 1;

            if(!$this->check_worker_belong_to_business($id, $_SESSION["BusinessId"])) {
                $response["return"] = 31;
                return $response;
            }
        
            if(!$this->check_worker_is_CO_or_USR($id, $_SESSION["BusinessId"])) {
                $response["return"] = 32;
                return $response;
            }
            
            $role = $this->get_role_in_business($id, $_SESSION["BusinessId"]);
            if($role == 33) {
                $response["return"] = 33;
                return $response;
            }
        
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT `username`, `name`, `surname`, `email` FROM userinfo WHERE id = ?')) {
                $stmt->bind_param('s', $id);
                $stmt->execute();
                
                // Store the result so we can check if the account exists in the database.
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($username, $name, $surname, $email);
                    $stmt->fetch();
                    $response["return"] = 0;
                    $response["data"]["username"] 	= $username;
                    $response["data"]["name"] 		= $name;
                    $response["data"]["surname"] 	= $surname;
                    $response["data"]["email"] 		= $email;
                    $response["data"]["role"] 		= $role;
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }

                return $response;
                $stmt->close();
            }
        }

        /**
         * Get business name.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_business_name() {
            $response["return"] = 1;
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT `name` FROM business_info WHERE id = ?')) {
                // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('s', $_SESSION['BusinessId']);
                $stmt->execute();
                
                // Store the result so we can check if the account exists in the database.
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($business_name);
                    $stmt->fetch();
                    $response["return"] = 0;
                    $response["business_name"] 	= $business_name;
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }
                return $response;
                $stmt->close();
            }
        }
    }

    ?>