<?php
// config.php
// Database connection
require_once 'vendor/autoload.php';
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$db_name = $_ENV['DB_NAME'];
$server = $_ENV['LDAP_SERVER'];

// config.php
// Database connection
$conn = new mysqli($host, $user, $password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// LDAP configuration
define('LDAP_SERVER', $server);
define('BASE_DN', "DC=leavetrackr,DC=com");

// Function to get all usernames from AD
function getAllADUsernames($ldapConn, $baseDN) {
    $usernames = [];

    $filter = "(objectClass=user)";
    $attributes = ['sAMAccountName'];

    $search = ldap_search($ldapConn, $baseDN, $filter, $attributes);
    $entries = ldap_get_entries($ldapConn, $search);

    for ($i = 0; $i < $entries['count']; $i++) {
        if (isset($entries[$i]['samaccountname'][0])) {
            $usernames[] = $entries[$i]['samaccountname'][0];
        }
    }

    return $usernames;
}
?>


