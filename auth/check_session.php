<?php

session_start();
define('DB_ACCESS', true);
require_once '../database/db_config.php';

header('Content-Type: application/json');

$timeout_duration = 1800; // 30 minutes
$active = false;


if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) <= $timeout_duration) {
        $_SESSION['last_activity'] = time(); // Update last activity
        $active = true;
    } else {
        // Session expired
        session_unset();
        session_destroy();
    }
} else if (isset($_COOKIE['remember_me'])) {
    // Try to restore session from remember_me cookie
    $cookie = $_COOKIE['remember_me'];
    if (strpos($cookie, ':') !== false) {
        list($selector, $validator) = explode(':', $cookie, 2);
        $conn = getDBConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT accountId, validator FROM user_token WHERE selector = ? LIMIT 1");
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($validator, $row['validator'])) {
                    // Valid token, restore session
                    $userId = $row['accountId'];
                    $userStmt = $conn->prepare("SELECT accountId, username, fullName FROM accounts WHERE accountId = ? LIMIT 1");
                    $userStmt->bind_param("s", $userId);
                    $userStmt->execute();
                    $userRes = $userStmt->get_result();
                    if ($user = $userRes->fetch_assoc()) {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['userId'] = $user['accountId'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['fullName'] = $user['fullName'];
                        // Fetch roles for session (RBAC)
                        $roleSql = $conn->prepare("SELECT r.roleName FROM user_roles ur JOIN roles r ON ur.roleId = r.roleId WHERE ur.accountId = ?");
                        $roleSql->bind_param("s", $user['accountId']);
                        $roleSql->execute();
                        $roleRes = $roleSql->get_result();
                        $_SESSION['roles'] = $roleRes ? array_column($roleRes->fetch_all(MYSQLI_ASSOC), 'roleName') : [];
                        $roleSql->close();
                        $_SESSION['last_activity'] = time();
                        $active = true;
                    }
                    $userStmt->close();
                }
            }
            $stmt->close();
        }
    }
    if (!$active) {
        session_unset();
        session_destroy();
    }
} else {
    // Not logged in
    session_unset();
    session_destroy();
}

// Always include username and fullName for frontend
$roles = $_SESSION['roles'] ?? [];
$userId = $_SESSION['userId'] ?? null;
$username = $_SESSION['username'] ?? null;
$fullName = $_SESSION['fullName'] ?? null;
echo json_encode([
    'active' => $active,
    'roles' => $roles,
    'userId' => $userId,
    'username' => $username,
    'fullName' => $fullName
]);
?>

