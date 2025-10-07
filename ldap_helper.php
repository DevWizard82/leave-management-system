<?php
require_once 'config.php'; // MySQL connection
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$LDAP_SERVER = $_ENV['LDAP_SERVER'];

/**
 * Connect to LDAP using given credentials.
 * Returns LDAP link on success, null on failure.
 */
function ldapConnect($username, $password) {
    global $LDAP_SERVER;

    $ldap = ldap_connect($LDAP_SERVER);
    if (!$ldap) return null;

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    // Bind with provided credentials
    if (!@ldap_bind($ldap, $username, $password)) {
        ldap_unbind($ldap);
        return null;
    }

    return $ldap;
}

/**
 * Authenticate a user with their username and password.
 * Returns true if credentials are valid, false otherwise.
 */
function authenticateUser($username, $password) {
    $userDN = $username . '@leavetrackr.com';
    $ldapConn = ldapConnect($userDN, $password);
    if ($ldapConn) {
        ldap_unbind($ldapConn);
        return true;
    }
    return false;
}

/**
 * Sync user information from Active Directory using a service account.
 * Returns user info array or null on failure.
 */
function syncUserFromAD($username, $conn, $serviceUser, $servicePass) {
    $ldap = ldapConnect($serviceUser, $servicePass);
    if (!$ldap) return null;

    $baseDN = "DC=leavetrackr,DC=com";
    $filter = "(sAMAccountName=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")";
    $attrs = ['givenName','sn','displayName','mail','memberOf','distinguishedName'];

    $search = @ldap_search($ldap, $baseDN, $filter, $attrs);
    if (!$search) return null;

    $entries = ldap_get_entries($ldap, $search);
    if ($entries['count'] == 0) {
        error_log("LDAP search failed for username: $username");
        error_log("Filter used: $filter, Base DN: $baseDN");
        return null;
    }

    $entry = $entries[0];
    $userDN = $entry['distinguishedname'][0] ?? '';
    $fullName = $entry['displayname'][0] ?? $username;
    $nameParts = explode(' ', $fullName, 2);

    $first_name = $entry['givenname'][0] ?? $nameParts[0];
    $last_name  = $entry['sn'][0] ?? ($nameParts[1] ?? '');
    $email      = $entry['mail'][0] ?? strtolower($first_name . '.' . $last_name . '@leavetrackr.com');

    // Default role
    $role = 'Employee';
    $buName = null;

    if (!empty($entry['memberof'])) {
        for ($i = 0; $i < $entry['memberof']['count']; $i++) {
            $group = $entry['memberof'][$i];
            if (stripos($group, 'HR_Group') !== false || stripos($group, 'HumanRessources') !== false) {
                $role = 'HR';
                break;
            }
            if (preg_match('/CN=([^,]+)/i', $group, $matches)) {
                $buName = $matches[1];

                // Check if this user is the manager of the BU
                $groupSearch = @ldap_search($ldap, $baseDN, "(CN=$buName)", ['managedBy']);
                if ($groupSearch) {
                    $groupEntries = ldap_get_entries($ldap, $groupSearch);
                    if ($groupEntries['count'] > 0 && !empty($groupEntries[0]['managedby'][0])) {
                        if (strcasecmp($groupEntries[0]['managedby'][0], $userDN) === 0) {
                            $role = 'Manager';
                        }
                    }
                }
            }
        }
    }

    // Sync to MySQL
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_id = $result->fetch_assoc()['id'];
        $update = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role=?, bu_name=? WHERE username=?");
        $update->bind_param("ssssss", $first_name, $last_name, $email, $role, $buName, $username);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, role, bu_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("ssssss", $username, $first_name, $last_name, $email, $role, $buName);
        $insert->execute();
        $user_id = $conn->insert_id;
        $insert->close();
    }

    ldap_unbind($ldap);

    return [
        'id' => $user_id,
        'username' => $username,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'role' => $role,
        'bu_name' => $buName
    ];
}

/**
 * Batch sync multiple users
 */
function syncUsersFromAD($usernames, $conn, $serviceUser, $servicePass) {
    $users = [];
    foreach ($usernames as $u) {
        $user = syncUserFromAD($u, $conn, $serviceUser, $servicePass);
        if ($user) $users[] = $user;
    }
    return $users;
}


function getUserPhoto($username, $ldapServer, $ldapUser, $ldapPass) {
    $ldap = ldap_connect($ldapServer);

        // Always check if connection was successful
    if (!$ldap) {
        error_log("Failed to connect to LDAP server: $ldapServer");
        return null;
    }


    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    if (!@ldap_bind($ldap, $ldapUser, $ldapPass)) {
        return null;
    }

    $filter = "(sAMAccountName=$username)";
    $result = ldap_search($ldap, "DC=leavetrackr,DC=com", $filter, ['thumbnailPhoto']);
    $entries = ldap_get_entries($ldap, $result);

    if (isset($entries[0]['thumbnailphoto'][0])) {
        $photo = base64_encode($entries[0]['thumbnailphoto'][0]);
        return "data:image/jpeg;base64,$photo";
    }
    return null;

}



