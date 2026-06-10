<?php
session_start();

// Include database configuration
define('DB_ACCESS', true);
require_once '../database/db_config.php';

$username = $_SESSION['username'] ?? null;
$accountId = $_SESSION['accountId'] ?? null;

// Remove from online users. Prefer using accountId if available.
$conn = getDBConnection();
if ($conn) {
    if (!empty($accountId)) {
        $stmt = $conn->prepare("DELETE FROM online_users WHERE accountId = ?");
        $stmt->bind_param("s", $accountId);
        $stmt->execute();
        $stmt->close();

        // Log activity
        logActivity($accountId, $username, 'LOGOUT', 'AUTH', $accountId, 'User logged out');
    } elseif ($username) {
        $stmt = $conn->prepare("DELETE FROM online_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();

        if ($username) {
            logActivity('', $username, 'LOGOUT', 'AUTH', '', 'User logged out (username)');
        }
    }
}

// Destroy session
session_unset();
session_destroy();


// Redirect to login page
header('Location: ../index.php?logout=success');
exit;
?>
