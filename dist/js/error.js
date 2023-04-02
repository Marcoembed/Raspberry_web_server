var alertShowed = 0;
function responseByID(id) {
    if (id == 0) {
      alert("Utente già collegato!");
      window.location.href = 'index.html';
    } else if (id == 1) {
      alert("Email or Password Field are empty");
    } else if (id == 2) {
      alert("Failed to connect to the Mysql Server");
    } else if (id == 3) {
      alert("Wrong username or password!");
    } else if (id == 4) {
      window.location.href = 'index.html';
    } else if (id == 5) {
        if (alertShowed == 0) {
            alert("You are not loggedin.");
            window.location.href = 'login.html';
            alertShowed = 1;
        }
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