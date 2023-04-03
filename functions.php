<?php
/**
 * Check the permission for an action
 *
 * This function will check if..
 *
 * @param $id
 * @param $businessid
 * @param $role_requested
 * @param $role_actual
 
 * @return 1 if everything is OK, 0 otherwise.
 */
function check_permission_role($id, $businessid, $role_requested, $role_actual) {
	if ($role_requested == "SA") {
		if ($role_actual == "SA") {
			return 1;
		}
		else {
			return 0;
		}
	}
	else if ($role_requested == "CA") {
		if ($role_actual == "CA" || $role_actual == "SA") {
			return 1;
		}
		else {
			return 0;
		}
	}
	else if ($role_requested == "CO") {
		if ($role_actual == "CO" || $role_actual == "CA" || $role_actual == "SA") {
			return 1;
		}
		else {
			return 0;
		}
	}
	else if ($role_requested == "USR") {
		return 1;
	} else {
		return 0;
	}
}

?>