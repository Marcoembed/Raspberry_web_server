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
         * Add the user information.
         * 
         * This function is called when the registration form is compiled by the client is sent 
         * @param text $table Description: table where to insert values
         * @param text $infoarray Description: array of info passed by the client
         * @return 
         */
        public function add_info($table, $infoarray){
            $values = implode(", ", $infoarray);
            $n_elements = count($infoarray);
            $params = array();
            $qmarks_array = array();
            for($i=0; $i<$n_elements; $i++){
                array_push($qmarks_array, "?");
            }
            $qmarks_string = implode(", ", $qmarks_array);
            foreach ($infoarray as $name) {
                if (isset($_POST[$name]) && $_POST[$name] != '') {
                    $params[$name] = $_POST[$name];
                }
            }
            if (count($params)) {
                $query = "INSERT INTO $table ($values) VALUES ($qmarks_string)";
            }
            if ($stmt = $this->con->prepare($query)) {
                $params = array_merge(array(str_repeat('s', count($params))), array_values($params));
                call_user_func_array(array(&$stmt, 'bind_param'), $params);
                $stmt->execute();
                return 1;
            } else {
                // Something is wrong with the SQL statement, so you must check to make sure your accounts table exists with all 3 fields.
                return 0;
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
            $my_businessid = $_SESSION["BusinessId"];
            $allowed_field = [
                "birthdate", 
                "sex", 
                "street",
                "number", 
                "city", 
                "zip", 
                "country", 
                "business_email", 
                "telephone_prefix", 
                "telephone",
                //"Visitor",
            ];
            
            if (array_search($field, $allowed_field) !== FALSE ) {
            
                if ($stmt = $this->con->prepare('UPDATE userdetails SET '.$field.'=? WHERE user_id=? AND business_id=?')) {
                    $stmt->bind_param('sii', $newvalue, $userid, $my_businessid);
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
                    $response = ["code" => 'Cant_Edit_CA/SA'];
                } else {
                    $return_val = $this->set_role_in_business($userid, $_SESSION["BusinessId"], $newvalue);
                    $response = ["code" => $return_val];    
                }
            } else if ($field == "id_business_building") {
                if ($stmt = $this->con->prepare('UPDATE business_people SET '.$field.'=? WHERE UserID=? AND BusinessID=?')) {
                    $stmt->bind_param('sii', $newvalue, $userid, $my_businessid);
                    $stmt->execute();
                    
                    if(mysqli_affected_rows($this->con))
                        $response = ["code" => '42']; // Set the response to "Update OK"
                    else 
                        $response = ["code" => '41']; // Set the response to "Update not OK"		 
                } else {
                    $response = ["code" => '43'];
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
         * Get user specific area permission.
         *
         * @param int $user_id The ID of the user
         * @param int $area_id The ID of the area to check
         * @return array check "return" key. If 0 everything is ok, else 1 if no permission, else error code.
         */
        public function get_user_specific_area_permission($user_id, $area_id) {
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            $sql1 = 'SELECT COUNT(*) as `count`
                    FROM `business_areas` 
                    INNER JOIN `business_area_blacklist_whitelist` ON `business_area_blacklist_whitelist`.`area_id` = `business_areas`.`id` 
                    WHERE `business_areas`.`id` = ?
                    AND `business_areas`.`whitelist_enabled` = `business_area_blacklist_whitelist`.`whitelist`
                    AND `business_area_blacklist_whitelist`.`user_id` = ?
                    AND `business_area_blacklist_whitelist`.`status` = "active"';
            $sql = 'SELECT 
                    	`business_areas`.`whitelist_enabled` 
                    FROM `business_areas` 
                    WHERE `business_areas`.`id` = ?';
            if ($stmt = $this->con->prepare($sql)) {
                $stmt->bind_param("i", $area_id);
                $stmt->execute();
                //echo $sql;
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    // The area exists, should be unique because id is unique.
                    $data = $result->fetch_assoc();
                    if ($stmt = $this->con->prepare($sql1)) {
                        $stmt->bind_param("ii", $area_id, $user_id);
                        $stmt->execute();
                        //echo $sql;
                        ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                        if ($result->num_rows > 0) {
                            $data_count = $result->fetch_assoc();
                            if ($data["whitelist_enabled"] == 0) {
                                // Blacklist method
                                if($data_count["count"]) {
                                    // Found an entry. The user has no permission to enter this area (black list)
                                    $response["return"] = 1; // No permission
                                } else {
                                    $response["return"] = 0;
                                }
                            } else {
                                // Whitelist method, function not developed yet.
                                $response["return"] = 8;
                                return $response;
                            }
                        } else {
                            $response["return"] = 9;
                            return $response;
                        }
                    } else {
                        $response["return"] = 6;
                        return $response;
                    }
                } else {
                    $response["return"] = 7;
                    return $response;
                }
                return $response;
                $stmt->close();
            }
        }

        // /**
        //  * Find first level children area for a given area
        //  * 
        //  * @return array Containing all the first level children area. If the array is empty, no children found.
        //  */
        // private function findChildrenAreas($area_id) {
        //     $children_id;
        //     $sql =  "SELECT `id` FROM `business_areas` WHERE `parent_id` = ?";
        //     if ($stmt = $this->con->prepare($sql)) {
        //         $stmt->bind_param('ii', $id, $businessid);
        //         $stmt->execute();
        //         $result = $stmt->get_result();
        //         if($result->num_rows) {
        //             while($data = $result->fetch_assoc()) {
        //                 array_push($children_id, $data["id"]);
        //             } 
        //         } else {
        //             return $children_id;
        //         }
        //         return $children_id;
        //     }
        // }
        
        // /**
        //  * Find all children area for a given area
        //  * 
        //  * @return array Containing all the children area. If the array is empty, no children found.
        //  */
        // private function findAllChildrenAreas($area_id) {
        //     $children_id;
        // }
        
        /**
         * Remove the permission of an area (and children) to an user.
         *
         * Description.
         *
         * @param int   $user_id             Description.
         * @param int   $area_id     Description.
         * @param text  $role           Description.
         * 
         * @return int 0 if everything is ok.
         */
        public function remove_user_area_permission($user_id, $area_id, $doneby_id) {
            $parent_id = $area_id;
            // We need a function to find all the children area starting from an area.
            // The algorithm should find all the areas having as parent id the area 
            // and then procede recursively.
            // I'm not able to fully understand the following SQL
            $sql = "WITH RECURSIVE tree_view AS (
                        SELECT `business_areas`.`id` as 'id',
                        `business_areas`.name,
                        `business_areas`.whitelist_enabled,
                        `business_areas`.`business_id`,
                        0 as level 
                        FROM `business_areas` 
                        WHERE `id` = ?
    
                        UNION ALL
    
                        SELECT 
                        `business_areas`.`id`,
                        `business_areas`.name,
                        `business_areas`.whitelist_enabled,
                        `business_areas`.`business_id`,
                        level + 1 AS level 
                        FROM `business_areas` 
                        JOIN tree_view tv 
                        ON `parent_id` = tv.id
                    )

                    SELECT
                       `id`, `whitelist_enabled`, `business_id`
                    FROM tree_view;";
            
            $sql3 = "   SELECT COUNT(*) as count 
                        FROM `business_area_blacklist_whitelist` 
                        WHERE user_id = ? 
                        AND area_id = ?";
            
            if (!($stmt = $this->con->prepare($sql))) {
                $response = 41; // Set the response to "Update not OK"
                return $response;	
            }
            
            $stmt->bind_param('i', $area_id);
            $stmt->execute();
            
            ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
            if ($result->num_rows == 0) {
                $response = 6;
                return $response;
            }
            
            while($data = $result->fetch_assoc())
            {
                if ($data["whitelist_enabled"] == 0) {
                    // Black list enabled, add user to the black list
                    // We need to check before if this user already does not have the permission to enter this area
                    $permission = $this->get_user_specific_area_permission($user_id, $data["id"]);
                    if ($permission["return"] == 0) {
                        // The user has the permission to enter, so we should check if it in the blacklist as removed.
                        if (!($stmt = $this->con->prepare($sql3))) {
                            $response = 6;
                            return $response;
                        }
                        
                        $stmt->bind_param("ii", $user_id, $data["id"]);
                        $stmt->execute();
                        
                        ($result1 = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                        $data1 = $result1->fetch_assoc();
                        if ($data1["count"] == 1) {
                            // Entry found, do an update query
                            $sql2 ="UPDATE `business_area_blacklist_whitelist` 
                                    SET `status`='active', `last_edit`=NOW(), `last_edit_by`= ? 
                                    WHERE `area_id` = ? AND `user_id` = ?";
                            
                            if (!($stmt = $this->con->prepare($sql2))) {
                                $response = 6;
                                return $response;
                            }
                            
                            $stmt->bind_param("iii", $doneby_id, $data["id"], $user_id);
                            $stmt->execute();
                        }
                        else if ($data1["count"] == 0) {
                            // Entry not found, do an insert query
                            $sql2 = "INSERT INTO `business_area_blacklist_whitelist` (`id`, `whitelist`, `business_id`, `area_id`, `user_id`, `status`, `last_edit`, `last_edit_by`) 
                            VALUES (NULL, 0, ?, ?, ?, 'active', NOW(), ?);";
                            if (!($stmt = $this->con->prepare($sql2))) {
                                $response = 6;
                                return $response;
                            }
                            
                            $stmt->bind_param("iiii", $data["business_id"], $data["id"], $user_id, $doneby_id);
                            $stmt->execute();
                        } else {
                            // Generic Error
                            $response = 8;
                            return $response;
                        }

                    }
                    // } else {
                    //     // Function not developed yet
                    //     $response = 8;
                    //     echo json_encode($permission);
                    //     return $response;
                    // }
                } else {
                    // Function not developed yet
                    $response = 7;
                    return $response;
                }
            }
            $response = 0;
            return 0;
        }
        
        /**
         * This function allows to add the access permission to an area by an user.
         *
         * If this area is inside another area, the permission to all the parent area will be granted.
         *
         * @param int   $user_id     Description.
         * @param int   $area_id     Description.
         * @param int   $doneby_id   Description.
         * 
         * @return int 0 if everything is ok.
         */
        public function add_user_area_permission($user_id, $area_id, $doneby_id, $business_id) {
            $sql = "WITH RECURSIVE tree_view AS (
                        SELECT 
                        `business_areas`.`id` as 'id',
                        `business_areas`.name,
                        `business_areas`.whitelist_enabled,
                        `business_areas`.`business_id`,
                        `business_areas`.`parent_id`,
                        0 as level 
                        FROM `business_areas` 
                        WHERE `id` = ?

                        UNION ALL

                        SELECT 
                        `business_areas`.`id`,
                        `business_areas`.name,
                        `business_areas`.whitelist_enabled,
                        `business_areas`.`business_id`,
                        `business_areas`.`parent_id`,
                        level - 1 AS level 
                        FROM `business_areas` 
                        JOIN tree_view tv 
                        ON `business_areas`.`id` = tv.parent_id
                    )

                    SELECT
                       `id`, `name`, `whitelist_enabled`, `business_id`
                    FROM tree_view;";

            if ($stmt = $this->con->prepare($sql)) {
                $stmt->bind_param('i', $area_id);
                $stmt->execute();
                
                //echo $sql;
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    {
                        $permission = $this->get_user_specific_area_permission($user_id, $data["id"]);
                        if ($permission["return"] == 0) {
                            // User has the permission for this area
                            continue;
                        } else if ($permission["return"] == 1) {
                            // User has no permission for this area
                            if ($data["whitelist_enabled"] == 1) {
                                // Not developed yet

                            } else if ($data["whitelist_enabled"] == 0) {
                                // We should "remove" the user from the blacklist
                                $sql2 = "   UPDATE `business_area_blacklist_whitelist` 
                                            SET `status`='removed', `last_edit`=NOW(),
                                            `last_edit_by`= ? 
                                            WHERE `area_id` = ? AND `user_id` = ?";
                                if ($stmt = $this->con->prepare($sql2)) {
                                    $stmt->bind_param('iii', $doneby_id, $data["id"], $user_id);
                                    $stmt->execute();
                                    // echo $sql2;
                                    if(mysqli_affected_rows($this->con) == 1) {
                                        $response = 0;
                                    } else {
                                        // Generic error
                                        $response = 10;
                                        return $response;
                                    }
                                } else {
                                    $response = 9;
                                    return $response;
                                }
                            } else {
                                // Generic error
                                $response = 8;
                                return $response;
                            }
                        } else {
                            // Generic error
                            $response = 7;
                            return $response;
                        }
                    }
                    return 0;
                } else {
                    $response = 6;
                    return $response;
                }
            } else {
                $response = 41; // Set the response to "Update not OK"	
            }
            $stmt->close();
            return $response;
        
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
         * Check if the registration info are already present in the database
         * 
         * Description
         * 
         * @param int $businessinfo Description.
         * @return int 1 if OK, otherwise 0
         */
        public function check_businessinfo_registration($businessinfo, $businessvalue){
            if ($stmt = $this->con->prepare('select * from business_info where `'.$businessinfo.'` = ?')) {
                // bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('s', $businessvalue);
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
         * Get the number of people in a building or in a area.
         *
         * Description.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_people_in_building($filter_building, $filter_area) {
            require_once 'functions.php'; 
            
            if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
                return 34;
            }

            $my_businessid = $_SESSION["BusinessId"];
            
            $sql2 = "AND `business_areas`.`parent_id` = 0";
            $sql3 = "";
            if (isset($filter_building)) {
                $sql3 = "AND `business_access_log`.`building_id` = ".$filter_building;
                if ($filter_area != 0) {
                    $sql2 = "AND `business_areas`.`id` = ".$filter_area;
                }
            } else {
                exit("You have to filter by building");
            }

            // With this SQL i want to get all the logs releted to a specific area.
            // If the filter_area equal to zero, this means i want to get the logs releted to the main building
            $sql =
            "SELECT `action`, `userinfo`.id, `userinfo`.username, 
            FROM `business_access_log` 
            INNER JOIN `userinfo` ON `business_access_log`.`user_id` = `userinfo`.`id`
            INNER JOIN `business_areas` ON `business_areas`.id = `business_access_log`.area_id 
            WHERE `business_access_log`.`business_id` = 1 
            ".$sql3."
            ".$sql2.
            " AND CAST(`time` AS DATE) = CAST( NOW() AS DATE)";

            exit($sql);

            $result = $this->con->query($sql);
            $response;
        
            if ($result->num_rows > 0) {
                // output data of each row
                $i = 0;
                while($row = $result->fetch_assoc()) {
                    $array;
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
        *  
        */

        public function return_regex() {
            
            $sql =  "SELECT `regex_name`, `regex_surname` `regex_email`, `regex_username`, `regex_password`, `regex_codice_fiscale`, `regex_phone_number`, `regex_address` FROM `config`";
            $result = $this->con->query($sql);
            $response;
        
            if ($result->num_rows > 0) {
                // output data of each row
                while($row = $result->fetch_assoc()) {
        
                    $array = [  
                                "regex_name"            => $row["regex_name"], 
                                "regex_surname"         => $row["regex_surname"], 
                                "regex_email"           => $row["regex_email"], 
                                "regex_username"        => $row["regex_username"], 
                                "regex_password"        => $row["regex_password"], 
                                "regex_codice_fiscale"  => $row["regex_codice_fiscale"], 
                                "regex_phone_number"    => $row["regex_phone_number"], 
                                "regex_address"         => $row["regex_address"] 
                    ];

                    $response["data"] = $array;
                    $response["return"] = 0;
                    $i++;
                }
            } else {
                $response["return"] = 37;
            }

            return $response;
        }


        /**
         * Get the log of the access in a Business for the current day.
         *
         * Description.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_access_log($filter_name, $filter_building) {
            require_once 'functions.php'; 
            
            if (!check_permission_role($_SESSION['id'], $_SESSION['BusinessId'], "CA", $_SESSION['role'])) {
                return 34;
            }

            $my_businessid = $_SESSION["BusinessId"];
            
            $sql2 = "";
            $sql3 = "";
            if (isset($filter_name)) {
                $sql2 = $filter_name;
                if (isset($filter_building)) {
                    $sql3 = "AND `business_access_log`.`building_id` = ".$filter_building;
                }
            }

            $sql =  "SELECT `area_id`, `action`, `badge_id`, `time`, 
                        `userinfo`.id AS `PersonID`, `userinfo`.`username`, 
                        `business_areas`.`name` AS `AreaName` 
                        FROM `business_access_log` 
                        INNER JOIN `userinfo` ON `business_access_log`.`user_id` = `userinfo`.`id`
                        INNER JOIN `business_areas` ON `business_areas`.id = `business_access_log`.area_id 
                        WHERE `business_access_log`.`business_id` = 1 
                        ".$sql3.  
                        " AND CAST(`time` AS DATE) = CAST( NOW() AS DATE) 
                        AND (CONCAT_WS (' ', `userinfo`.name, `userinfo`.surname) LIKE '%".$sql2."%')";

            $result = $this->con->query($sql);
            $response;
        
            if ($result->num_rows > 0) {
                // output data of each row
                $i = 0;
                while($row = $result->fetch_assoc()) {
        
                    $array = [  
                                "id"        => $row["PersonID"], 
                                "Time"      => $row["time"], 
                                "Area"      => $row["AreaName"], 
                                "Username"  => $row["username"], 
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
            $sql = "SELECT `username`, `name`, `surname`, `role`, `userinfo`.`id`, ". 
            "`userdetails`.`business_email`, `userdetails`.`telephone`, `userdetails`.`telephone_prefix`, `userdetails`.`street` ". 
            "FROM `userinfo` ".
            "INNER JOIN `business_people` ON `userinfo`.`id`=`business_people`.`UserID` ".
            "INNER JOIN `userdetails` ON `userinfo`.`id` = `userdetails`.`user_id` ".
            "WHERE `business_people`.`BusinessID` = ".$my_businessid." ".
            "AND `userdetails`.`business_id` = ".$my_businessid." ". 
            "AND `business_people`.`status`='active' ".
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
                            "role"      => $row['role'],
                            "id"        => $row['id'],
                            "email"     => $row["business_email"],
                            "phone_number" => $row['telephone_prefix'].$row['telephone'],
                            "address"   => $row['street']
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
         * Get user information, releted to the current business.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_user_information($id) {
            $my_businessid = $_SESSION["BusinessId"];
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
        
            $field_to_get = [
                "birthdate", 
                "sex", 
                "street",
                "number", 
                "city", 
                "zip", 
                "country", 
                "business_email", 
                "telephone_prefix", 
                "telephone",
                "name",
                "surname",
                "username",
                "email",
                "role",
                "Visitor",
                "id_business_building",
                "building_name"
            ];

            $sql = "SELECT ";
            $sql .= implode(", ", $field_to_get);


            $sql .=  " FROM `userdetails` ".
            "INNER JOIN `business_people` ON `userdetails`.`user_id`=`business_people`.`UserID` ".
            "INNER JOIN `userinfo` ON `userinfo`.`id` = `userdetails`.`user_id` ".
            "INNER JOIN `business_building` ON `business_building`.`id` = `business_people`.`id_business_building` ".
            "WHERE `business_people`.`BusinessID` = ".$my_businessid." ".
            "AND `userdetails`.`business_id` = ".$my_businessid." ".
            "AND `userinfo`.`id` = ?";

            if ($stmt = $this->con->prepare($sql)) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc()) {
                        $response["return"] = 0;
                        foreach($field_to_get as $value) {
                            $response["data"][$value] 	= $data[$value];
                        }
                    }
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
         * Get total numer of workers and visitors belonging to a business. 
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function get_numer_of_workers() {
            $response["return"] = 1;
            $businessID = $_SESSION["BusinessId"];
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT COUNT(*) as `count` FROM business_people 
                    WHERE BusinessID = ? AND `status` = "active" AND `Visitor` = 0')) {
                
                $stmt->bind_param('i', $businessID);
                $stmt->execute();
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["number_of_workers"] = $data["count"];
                    }
                } else {
                    // This should not happen
                    $response["return"] = 6;
                    return $response;
                }
            }
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare('SELECT COUNT(*) as `count` FROM business_people 
                    WHERE BusinessID = ? AND `status` = "active" AND `Visitor` = 1')) {
                
                $stmt->bind_param('i', $businessID);
                $stmt->execute();
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["number_of_visitors"] = $data["count"];
                    }
                } else {
                    // This should not happen
                    $response["return"] = 6;
                    return $response;
                }
            }
            
            return $response;
            $stmt->close();
        }
        
        /**
         * Create new business building.
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
        
        /**
         * Create new business areas.
         * 
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        function create_new_area($array) {
            $businessID = $_SESSION["BusinessId"];
            $response = 1;
            
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            if ($stmt = $this->con->prepare(
                'INSERT INTO business_areas (`business_id`, `business_building_id`, `parent_id`, `name`, `whitelist_enabled`) '.
                'VALUES (?, ?, ?, ?, '.$array["whitelist"].')')) {
                $stmt->bind_param('iiis', $businessID, $array["building_parent_id"], $array["area_parent_id"], $array["area_name"]);
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
        
        /**
         * Get the business belonging to the user.
         *
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        public function get_user_business($user_id) {
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            $sql = 'SELECT `BusinessID`, `name` FROM `business_people` 
                INNER JOIN `business_info` ON `business_people`.`BusinessID` = `business_info`.`id` 
                WHERE `UserID` = ? 
                AND `status` = "active"';

            if ($stmt = $this->con->prepare($sql)) {
                // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    $response["data"]["number_of_business"] = 0;
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["data"]["business"][$data["BusinessID"]]["business_name"] = $data["name"];
                        $response["data"]["number_of_business"]++;
                        $response["data"]["last_businessID"] = $data["BusinessID"];
                    }
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
         * Get all the area for which the user is allowed to enter.
         *
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        public function get_user_area_permission($user_id) {
            // Prepare our SQL, preparing the SQL statement will prevent SQL injection.
            $sql = 'SELECT `business_building`.`building_name`, `business_building`.`id` AS building_id, `business_areas`.`name`, `business_areas`.`id` as area_id, `whitelist_enabled`, `business_areas`.`parent_id`  
                FROM `business_areas` 
                INNER JOIN `business_building` ON `business_building`.`id` = `business_areas`.`business_building_id` 
                WHERE `business_areas`.`business_id` = ?
                ORDER BY `parent_id` ASC';
            if ($stmt = $this->con->prepare($sql)) {
                // Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
                $stmt->bind_param('i', $_SESSION["BusinessId"]);
                $stmt->execute();
                //echo $sql;
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["data"][$data["building_id"]]["building_name"] = $data["building_name"];
                        $response["data"][$data["building_id"]]["areas"][$data["area_id"]]["area_name"] = $data["name"];
                        $response["data"][$data["building_id"]]["areas"][$data["area_id"]]["whitelist"] = $data["whitelist_enabled"];
                        $response["data"][$data["building_id"]]["areas"][$data["area_id"]]["parent_id"] = $data["parent_id"];
                        $response["data"][$data["building_id"]]["areas"][$data["area_id"]]["access_granted"] = 0;
                    }
                } else {
                    // Incorrect username
                    $response["return"] = 6;
                    return $response;
                }

                foreach ($response["data"] as $key => $value) {
                    foreach ($response["data"][$key]["areas"] as $key1 => $value) {
                        $whitelist = $response["data"][$key]["areas"][$key1]["whitelist"];
                        $sql1;
                        if ($whitelist == 0) {
                            // Whitelist not enabled, search in the blacklist
                            $sql1 = "   SELECT * FROM `business_area_blacklist_whitelist` 
                                        WHERE business_id = ? 
                                        AND area_id = ? 
                                        AND user_id = ? 
                                        AND whitelist = 0
                                        AND status = 'active'"; 
                            $stmt = $this->con->prepare($sql1); 
                            $stmt->bind_param('iii', $_SESSION["BusinessId"], $key1, $user_id);
                            $stmt->execute();
                
                            ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                            if ($result->num_rows > 0) {
                                $response["data"][$key]["areas"][$key1]["access_granted"] = 0;
                                unset($response["data"][$key]["areas"][$key1]);
                            } else {
                                $response["data"][$key]["areas"][$key1]["access_granted"] = 1;
                            }
                            //echo ($sql1);
                        } else if ($whitelist == 1) {
                            // Whitelist enabled, search in the whitelist
                            $sql1 = "   SELECT * FROM `business_area_blacklist_whitelist` 
                                        WHERE business_id = ? 
                                        AND area_id = ? 
                                        AND user_id = ? 
                                        AND whitelist = 1 
                                        AND status = 'active'"; 
                            $stmt = $this->con->prepare($sql1); 
                            $stmt->bind_param('iii', $_SESSION["BusinessId"], $key1, $user_id);
                            $stmt->execute();
                
                            ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                            if ($result->num_rows > 0) {
                                $response["data"][$key]["areas"][$key1]["access_granted"] = 1;
                            } else {
                                $response["data"][$key]["areas"][$key1]["access_granted"] = 0;
                                unset($response["data"][$key]["areas"][$key1]);
                            }

                        }
                        if (empty($response["data"][$key]["areas"])) {
                            unset($response["data"][$key]);
                        }
                    }
                }
                return $response;
                $stmt->close();
            }
        }
        
        /**
         * Get all the area for which the user is not allowed to enter.
         *
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        public function get_user_area_no_permission($user_id, $business_id) {
            $sql = 'WITH RECURSIVE tree_view AS (
                    SELECT 
    			    `business_areas`.`id` as "id",
                    `business_areas`.name,
                    `business_areas`.whitelist_enabled,
                    `business_areas`.`business_id`,
    				`business_areas`.`business_building_id`,
    				`business_areas`.`parent_id`,
                    0 as level 
                    FROM `business_areas` 
       				WHERE `business_areas`.`business_id` = ?
    				AND `business_areas`.`parent_id` = 0
    
                    UNION ALL
    
                    SELECT 
                    `business_areas`.`id`,
                    `business_areas`.name,
                    `business_areas`.whitelist_enabled,
                    `business_areas`.`business_id`,
    				`business_areas`.`business_building_id`,
    				`business_areas`.`parent_id`,
                    level + 1 AS level 
                    FROM `business_areas` 
                    JOIN tree_view tv 
                    ON `business_areas`.`parent_id` = tv.id
                    )

                    SELECT
                       `business_building_id`, `business_building`.`building_name`, 
                       tree_view.`id`, `whitelist_enabled`, tree_view.`business_id`, 
                       `name`, `level`, tree_view.`parent_id` 
                    FROM tree_view INNER JOIN `business_building` ON `business_building`.`id` = tree_view.`business_building_id`
                    ORDER BY tree_view.level DESC;';
            if ($stmt = $this->con->prepare($sql)) {
                $stmt->bind_param('i', $business_id);
                $stmt->execute();
                ($result = $stmt->get_result()) or trigger_error($stmt->error, E_USER_ERROR);
                if ($result->num_rows > 0) {
                    while($data = $result->fetch_assoc())
                    { 
                        $response["return"] = 0;
                        $response["data"][$data["business_building_id"]]["building_name"] = $data["building_name"];
                        $response["data"][$data["business_building_id"]]["areas"][$data["id"]]["area_name"] = $data["name"];
                        $response["data"][$data["business_building_id"]]["areas"][$data["id"]]["parent_id"] = $data["parent_id"];
                        $response["data"][$data["business_building_id"]]["areas"][$data["id"]]["level"] = $data["level"];

                        $permission = $this->get_user_specific_area_permission($user_id, $data["id"]);
                        if ($permission["return"] == 0) {
                            unset($response["data"][$data["business_building_id"]]["areas"][$data["id"]]);
                        }
                    }
                } else {
                    $response["return"] = 6;
                    return $response;
                }

                return $response;
                $stmt->close();
            }
        }
        /**
         * Get the badge info from Badge Pseudo Code.
         *
         * @return array check "return" key. If 0 everything is ok, else error code.
         */
        public function get_badge_info($badge_code, $business_id) {
            if ($stmt = $this->con->prepare("SELECT * FROM `business_badge` WHERE `badge_code` = ? AND `business_id` = ?")) {
                $stmt->bind_param('si', $badge_code, $business_id);
                $stmt->execute();

                $result = $stmt->get_result();
                if($result->num_rows == 0) {
                    $response["return"] = 1; 
                    return $response;
                }
                else {
                    $row = $result->fetch_assoc();
                    $response["return"] = 0;
                    array_push($response, $row);
                    return $response;
                }
            }
        }
        
    }

    ?>