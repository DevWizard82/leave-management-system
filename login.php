<?php
session_start();
require_once 'config.php';
require_once 'ldap_helper.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');


if (!$username || !$password) {
    $_SESSION['login_error'] = "Veuillez saisir un nom d'utilisateur et un mot de passe.";
    header("Location: index.php"); 
    exit();
}

// Step 1: Authenticate user with their own credentials
if (!authenticateUser($username, $password)) {
    $_SESSION['login_error'] = "Nom d'utilisateur ou mot de passe incorrect.";
    header("Location: index.php"); 
    exit();
}

// Step 2: Hash password for local storage
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);


// Step 2.5: check if user already exists in MySQL

$stmtCheck = $conn->prepare("SELECT id FROM users WHERE username=?");
$stmtCheck->bind_param("s", $username);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows == 0) {
    if (isset($_POST['date_embauche']) && !empty($_POST['date_embauche'])) {
        // New user submitted date_embauche -> insert into DB
        $dateEmbauche = $_POST['date_embauche'];

        $stmtInsert = $conn->prepare(
            "INSERT INTO users (username, password, created_at) VALUES (?, ?, ?)"
        );
        $stmtInsert->bind_param("sss", $username, $hashedPassword, $dateEmbauche);
        $stmtInsert->execute();
        $stmtInsert->close();

        // User is now created, unset pending session
        unset($_SESSION['pending_user']);
        unset($_SESSION['pending_user_username']);
        unset($_SESSION['pending_user_password']);
    } else {
        // Show the date_embauche input in index.php
        $_SESSION['pending_user'] = true;
        $_SESSION['pending_user_username'] = $username;
        $_SESSION['pending_user_password'] = $password;
        header("Location: index.php");
        exit();
    }
}


$stmtCheck->close();


// Step 3: Sync user info from AD using service account
$serviceUser = $_ENV['LDAP_USER'];
$servicePass = $_ENV['LDAP_PASS'];

$updatedUser = syncUserFromAD($username, $conn, $serviceUser, $servicePass);

if (!$updatedUser) {
    $_SESSION['login_error'] = "Impossible de récupérer les informations utilisateur depuis l'AD.";
    header("Location: index.php"); 
    exit();
}

// Step 4: Determine manager/HR IDs
$managerId = $hrId = null;

if ($updatedUser['role'] === 'Employee' && $updatedUser['bu_name']) {
    $stmtManager = $conn->prepare("SELECT id, hr_id FROM users WHERE bu_name=? AND role='Manager' LIMIT 1");
    $stmtManager->bind_param("s", $updatedUser['bu_name']);
    $stmtManager->execute();
    $res = $stmtManager->get_result();
    if ($res->num_rows > 0) {
        $mRow = $res->fetch_assoc();
        $managerId = $mRow['id'];
        $hrId = $mRow['hr_id'];
    }
    $stmtManager->close();
} elseif ($updatedUser['role'] === 'Manager') {
    $stmtHR = $conn->prepare("SELECT id FROM users WHERE role='HR' LIMIT 1");
    $stmtHR->execute();
    $resHR = $stmtHR->get_result();
    if ($resHR->num_rows > 0) $hrId = $resHR->fetch_assoc()['id'];
    $stmtHR->close();
}

// Step 5: Update user with manager_id, hr_id, and password
$stmtUpdate = $conn->prepare(
    "UPDATE users SET manager_id=?, hr_id=?, password=? WHERE username=?"
);
$stmtUpdate->bind_param("iiss", $managerId, $hrId, $hashedPassword, $username);
$stmtUpdate->execute();
$stmtUpdate->close();

// Step 6: Set session
$_SESSION['user_id'] = $updatedUser['id'];
$_SESSION['username'] = $username;
$_SESSION['first_name'] = $updatedUser['first_name'];
$_SESSION['last_name'] = $updatedUser['last_name'];
$_SESSION['role'] = $updatedUser['role'];
$_SESSION['email'] = $updatedUser['email'];
$_SESSION['manager_id'] = $managerId;
$_SESSION['hr_id'] = $hrId;
$_SESSION['bu_name'] = $updatedUser['bu_name'];
$_SESSION['password'] = $hashedPassword;

// Step 7: Redirect by role
session_write_close();
session_name('LeaveTrackr_'.$updatedUser['role']);
session_start();

switch ($updatedUser['role']) {
    case 'Manager':
        header("Location: manager_dashboard.php"); 
        break;
    case 'HR':
        header("Location: hr_dashboard.php"); 
        break;
    default:
        header("Location: employee_dashboard.php"); 
        break;
}
exit();
