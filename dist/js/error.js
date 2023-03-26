function responseByID(id) {
    if (id == 5) {
        alert("You are not loggedin.");
        window.location.href = 'login.html';
    } else if (id == 7) {
        alert("Error while intersecating information into the DB");
    } else if (id == 34) {
        alert("You have NOT the permission to be here.");
    } else if (id == 37) {
        // alert("No Logs Found.");
    } else {
        alert("Error not specified");
    }
}

function expandRole(role) {
    if(role == "USR") {
        return "User";
    } else if (role == "CO") {
        return "Customer Operator";
    } else if (role == "CA") {
        return "Customer Administrator"
    } else if (role == "SA") {
        return "System Administrator"
    }
}