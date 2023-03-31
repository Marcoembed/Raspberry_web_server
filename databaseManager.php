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
         *
         * @param int $id           Description.
         * @param int $businessid   Description.
         * 
         * @return int|text If text then return role, int if error
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
         * Remove an user from a business.
         *
         * This function will set the status column of `business_people` of the user 
         * in the DB to "removed".
         *
         * @param int   $id             Description.
         * @param int   $businessid     Description.
         * @param text  $role           Description.
         * 
         * @return array 42 means everything is okay, another number.
         */
        public function remove_user_from_business($id) {
            $my_userid = $_SESSION["id"];
            $businessid = $_SESSION["BusinessId"];

            if ($stmt = $this->con->prepare('UPDATE `business_people` SET '.
                '`status` = "removed", `last_edit` = NOW(), `last_edit_by` = ? '.
                'WHERE `UserID` = ? AND `BusinessID` = ?')) {
                $stmt->bind_param('iii', $my_userid, $id, $businessid);
                $stmt->execute();
                
                if(mysqli_affected_rows($this->con))
                    $response = 42; // Set the response to "Update OK"
                else 
                    $response = 41; // Set the response to "Update not OK"

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
                $response["return"] = 37;
            }

            return $response;
        }

        /**
         * Get all workers information from business.
         *
         * @param int $page If 0, it will return all workers
         * @param text $role Can be used to filter the workers (For example, "CA" will show only "CA", "CO", "USR")
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_people_from_business($page, $filter, $filter_building) {
            require_once 'functions.php';
            
            $my_userid;
            if ($_SESSION["playrole"] == 1) {
                $my_userid = $_SESSION["playrole_id"];
            } else {
                $my_userid = $_SESSION["id"];
            }

            $my_businessid = $_SESSION["BusinessId"];
            // if ($_SESSION["playrole"] == 1) {
            //     $my_businessid = $_SESSION["playrole_Businessid"];
            // } else {
            //     $my_businessid = $_SESSION["BusinessId"];
            // }

            $my_role = $this->get_role_in_business($my_userid, $_SESSION["BusinessId"]);
            if (is_numeric($my_role)) {
                $response["return"] = $my_role;
                return $response;
            }
            
            // if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
            //     $response["return"] = 34;
            //     return $response;
            // }

            // if($page == 0) {
            //     $response["return"] = 36;
            //     return $response;
            // }

            // This function is used to filter the workers of the business depending on their role and
            // depending on the role of the user who asked for this function.
            switch($my_role) {
                case "SA" :
                    $sql2 = "(`business_people`.`role` = 'CO' OR `business_people`.`role` = 'USR' OR `business_people`.`role` = 'CA' OR `business_people`.`role` = 'SA') ";
                    break;

                case "CA":
                    $sql2 = "(`business_people`.`role` = 'CO' OR `business_people`.`role` = 'USR' OR `business_people`.`role` = 'CA') ";
                    break;
                
                case "CO" :
                    $sql2 = "(`business_people`.`role` = 'CO' OR `business_people`.`role` = 'USR') ";
                    break;
                
                case "USR" : 
                    $response["return"] = 34;
                    return $response;
                    break;
            }
            
            if (!isset($filter)) {
                $filter == "";
            }
            
            $limit;
            if ($page == 0) {
                // I want ALL workers for some reason
                $limit = " ";
            } else {
                $limit = "LIMIT 9 OFFSET ".($page-1)*9;
            }

            $sql3;
            if ($filter_building == 0) {
                $sql3 = "1 ";
            } else {
                $sql3 = "business_people.id_business_building = ".$filter_building." ";
            }

            // By using this query we will select all the workers by joining the table business_people and userinfo.
            // We can further filter those people depending on the business area (if business has multiple area and if the filter is active)
            $sql = "SELECT `username`, `name`, `surname`, `email`, `role`, `userinfo`.`id` FROM `userinfo` ".
            "INNER JOIN `business_people` ON `userinfo`.`id`=`business_people`.`UserID` ".
            "WHERE `business_people`.`BusinessID` = ".$my_businessid." AND `business_people`.`status`='active' ".
            "AND ".$sql3.
            "AND ".$sql2.
            "AND(CONCAT_WS (' ', `userinfo`.name, `userinfo`.surname) LIKE '%".$filter."%') ".
            "ORDER BY `business_people`.`role` ASC ".
            $limit;

            //echo $sql;
            $result = $this->con->query($sql);
        
            // output data of each row
            $i = 0;
            while(($row = $result->fetch_assoc())) {
                $array = [	"username"  => $row["username"], 
                            "name"      => $row["name"], 
                            "surname"   => $row["surname"], 
                            "email"     => $row["email"],
                            "id"        => $row['id'],
                            "role"      => $row['role']
                        ];

                $response["data"][$i] = $array;
                $response["return"] = 0;
                $i++;
            }
            
            // This query will be used to count the numer of total workers.
            // It is very fast
            $sql = "SELECT COUNT(*) as `count` FROM `userinfo` ".
            "INNER JOIN `business_people` ON `userinfo`.`id`=`business_people`.`UserID` ".
            "WHERE `business_people`.`BusinessID` = ".$my_businessid." AND `business_people`.`status`='active' ".
            "AND ".$sql3.
            "AND ".$sql2.
            "AND(CONCAT_WS (' ', `userinfo`.name, `userinfo`.surname) LIKE '%".$filter."%') ".
            "ORDER BY `business_people`.`role` ASC ";

            $result = $this->con->query($sql);
            $row = $result->fetch_assoc();
            
            $response["amount"] = $row['count']; 
            return $response;
        }

        /**
         * Get the username of user.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_username() {
            require_once 'functions.php';
            $userid;
            if ($_SESSION["playrole"] == 1)
                $userid = $_SESSION["playrole_id"];
            else
                $userid = $_SESSION["id"]; 
            
            $sql = "SELECT username FROM userinfo WHERE id = ".$userid;
            $result = $this->con->query($sql);

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $response["username"] = $row["username"];
                $response["return"] = 0;
            } else {
                // User not found into the DB
                $response["return"] = 6;
            }
            return $response;
            $stmt->close();
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
        
            // if(!$this->check_worker_is_CO_or_USR($id, $_SESSION["BusinessId"])) {
            //     $response["return"] = 32;
            //     return $response;
            // }
            
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
        
        /**
         * Get business information.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_business_info($businessID) {
            $response["return"] = 1;
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT `name`, `VAT` FROM business_info WHERE id = ?')) {
                $stmt->bind_param('i', $businessID);
                $stmt->execute();
                ($stmt->store_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($row["name"], $row["VAT"]);
                    $stmt->fetch();

                    $response["return"] = 0;
                    $response["data"]["name"] 	= $row["name"];
                    $response["data"]["VAT"]    = $row["VAT"];
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }
            }
            
            if ($stmt = $this->con->prepare('SELECT `id`, `building_name` FROM business_building WHERE business_id = ?')) {
                $stmt->bind_param('i', $businessID);
                $stmt->execute();
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["data"]["building"][$data["id"]]["building_name"] 	= $data["building_name"];
                    }

                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }
            }
            
            return $response;
            $stmt->close();
        }
        
        /**
         * Get business areas.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_business_areas($businessID, $business_buildingID) {
            $response["return"] = 1;
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT `id`, `name` FROM business_areas WHERE business_id = ? AND business_building_id = ?')) {
                $stmt->bind_param('ii', $businessID, $business_buildingID);
                $stmt->execute();
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["data"][$data["id"]]["name"] = $data["name"];
                    }
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }
            }
            
            return $response;
            $stmt->close();
        }
        
        /**
         * Get business areas.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function create_new_building($building_name, $building_address) {
            $businessID = $_SESSION["BusinessId"];
            $response = 1;
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare(
                'INSERT INTO business_building (`business_id`, `building_name`, `building_address`) '.
                'VALUES (?, ?, ?)')) {
                $stmt->bind_param('iss', $businessID, $building_name, $building_address);
                $stmt->execute();
                if(mysqli_affected_rows($this->con) == 1) {
                    $response = 0;
                } else {
                    $response = 41;
                }
            }
            
            return $response;
            $stmt->close();
        }
    }

    ?>