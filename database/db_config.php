<?php
// Always suppress errors for API responses
error_reporting(0);
ini_set('display_errors', 0);
/**
 * Database Configuration File
 * 
 * This file contains database connection settings and helper functions
 * for the Pharmacy MIS system.
 */

// Prevent direct access
defined('DB_ACCESS') or define('DB_ACCESS', true);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Default XAMPP MySQL username
define('DB_PASS', '');      // Default XAMPP MySQL password (empty)
define('DB_NAME', 'pharmacy_mis');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Manila'); // Philippine timezone

/**
 * Get database connection
 * 
 * @return mysqli|false Database connection or false on failure
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return false;
        }
        
        // Set charset
        $conn->set_charset(DB_CHARSET);
    }
    
    return $conn;
}

/**
 * Close database connection
 */
function closeDBConnection() {
    $conn = getDBConnection();
    if ($conn) {
        $conn->close();
    }
}

/**
 * Execute a prepared statement
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for the query
 * @param string $types Parameter types (i=integer, d=double, s=string, b=blob)
 * @return array|bool Result array or boolean
 */
function executeQuery($sql, $params = [], $types = '') {
    $conn = getDBConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    // Bind parameters if provided
    if (!empty($params) && !empty($types)) {
        $bindParams = [$types];
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    
    // Execute
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    // Get result for SELECT queries
    $queryType = strtoupper(substr(trim($sql), 0, 6));
    if ($queryType === 'SELECT') {
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }
    
    // For INSERT/UPDATE/DELETE, return affected rows
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    return $affectedRows;
}

/**
 * Get the last inserted ID
 * 
 * @return int Last insert ID
 */
function getLastInsertId() {
    $conn = getDBConnection();
    return $conn ? $conn->insert_id : 0;
}

/**
 * Escape string for SQL
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
function escapeString($string) {
    $conn = getDBConnection();
    return $conn ? $conn->real_escape_string($string) : $string;
}

/**
 * Start transaction
 */
function beginTransaction() {
    $conn = getDBConnection();
    if ($conn) {
        $conn->begin_transaction();
    }
}

/**
 * Commit transaction
 */
function commitTransaction() {
    $conn = getDBConnection();
    if ($conn) {
        $conn->commit();
    }
}

/**
 * Rollback transaction
 */
function rollbackTransaction() {
    $conn = getDBConnection();
    if ($conn) {
        $conn->rollback();
    }
}

/**
 * Send JSON response
 * 
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message Response message
 */
function sendJSON($success, $data = null, $message = '') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response);
    exit;
}

/**
 * Log activity to database
 *
 * @param string $accountId Account ID
 * @param string $username Username
 * @param string $action Action type
 * @param string $module Module name
 * @param string $details Additional details
 */
function logActivity($accountId, $username, $action, $module, $details = '') {
    $sql = "INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress)
            VALUES (?, ?, ?, ?, ?, ?)";

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    executeQuery($sql, [
        $accountId,
        $username,
        $action,
        $module,
        $details,
        $ipAddress
    ], 'ssssss');
}

/**
 * Get system setting value
 * 
 * @param string $key Setting key
 * @return string|null Setting value or null if not found
 */
function getSystemSetting($key) {
    $sql = "SELECT settingValue FROM system_settings WHERE settingKey = ?";
    $result = executeQuery($sql, [$key], 's');
    return !empty($result) ? $result[0]['settingValue'] : null;
}

/**
 * Update system setting
 * 
 * @param string $key Setting key
 * @param string $value New value
 * @param string $updatedBy Username who updated
 * @return bool Success status
 */
function updateSystemSetting($key, $value, $updatedBy) {
    $sql = "UPDATE system_settings SET settingValue = ?, updatedBy = ? WHERE settingKey = ?";
    return executeQuery($sql, [$value, $updatedBy, $key], 'sss') !== false;
}

/**
 * Sanitize input
 * 
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 * 
 * @param array $data Data array
 * @param array $required Required field names
 * @return array Array of missing fields
 */
function validateRequired($data, $required) {
    $missing = [];
    foreach ($required as $field) {
        // Field not provided at all
        if (!isset($data[$field])) {
            $missing[] = $field;
            continue;
        }

        $value = $data[$field];

        // If value is a string, trim and check for empty string (but '0' is valid)
        if (is_string($value)) {
            if (trim($value) === '') {
                $missing[] = $field;
            }
        } else {
            // For non-strings (numbers, booleans), only null or empty string are missing
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }
    }
    return $missing;
}

/**
 * Get a user's roles (from session or DB, RBAC)
 *
 * @param string|null $accountId
 * @return array Array of role names
 */
function getUserRoles($accountId = null) {
    $logFile = __DIR__ . '/../logs/require_permission_debug.log';
    // Prefer session value when available
    if (!$accountId && isset($_SESSION) && !empty($_SESSION['accountId'])) {
        $accountId = $_SESSION['accountId'];
    }
    file_put_contents($logFile, "DEBUG getUserRoles: accountId=" . json_encode($accountId) . "\n", FILE_APPEND);
    if (!$accountId) {
        file_put_contents($logFile, "DEBUG getUserRoles: NO ACCOUNT ID\n", FILE_APPEND);
        return [];
    }
    $sql = "SELECT r.roleName FROM user_roles ur JOIN roles r ON ur.roleId = r.roleId WHERE ur.accountId = ?";
    $res = executeQuery($sql, [$accountId], 's');
    if ($res && is_array($res)) {
        $roles = array_column($res, 'roleName');
        file_put_contents($logFile, "DEBUG getUserRoles: roles=" . json_encode($roles) . "\n", FILE_APPEND);
        return $roles;
    }
    file_put_contents($logFile, "DEBUG getUserRoles: NO ROLES FOUND\n", FILE_APPEND);
    return [];
}

/**
 * Require that the current user has at least one of the allowed roles (RBAC).
 * If not authorized, sends a JSON error and exits.
 *
 * @param array|string $allowedRoles
 */
function requireRole($allowedRoles) {
    if (is_string($allowedRoles)) $allowedRoles = [$allowedRoles];

    $userRoles = getUserRoles(); // Uses accountId now
    if (empty($userRoles)) {
        sendJSON(false, null, 'Unauthorized: no role assigned');
    }

    foreach ($allowedRoles as $r) {
        foreach ($userRoles as $ur) {
            if (strcasecmp($r, $ur) === 0) {
                return true;
            }
        }
    }

    sendJSON(false, null, 'Forbidden: insufficient permissions');
}

// Initialize connection on include
$db_conn = getDBConnection();
if (!$db_conn) {
    if (!defined('DB_ACCESS')) {
        die("Database connection failed. Please check your configuration.");
    }
    // For API usage, connection will be checked in functions
}

/**
 * Get all permissions for a user (via their roles)
 * @param string|null $accountId
 * @return array Array of permission names
 */
function getUserPermissions($accountId = null) {
    $logFile = __DIR__ . '/../logs/require_permission_debug.log';
    if (!$accountId && isset($_SESSION) && !empty($_SESSION['accountId'])) {
        $accountId = $_SESSION['accountId'];
    }
    file_put_contents($logFile, "DEBUG getUserPermissions: accountId=" . json_encode($accountId) . "\n", FILE_APPEND);
    if (!$accountId) {
        file_put_contents($logFile, "DEBUG getUserPermissions: NO ACCOUNT ID\n", FILE_APPEND);
        return [];
    }
    $sql = "SELECT p.permissionName FROM user_roles ur
            JOIN role_permissions rp ON ur.roleId = rp.roleId
            JOIN permissions p ON rp.permissionId = p.permissionId
            WHERE ur.accountId = ?";
    $res = executeQuery($sql, [$accountId], 's');
    if ($res && is_array($res)) {
        $perms = array_column($res, 'permissionName');
        file_put_contents($logFile, "DEBUG getUserPermissions: permissions=" . json_encode($perms) . "\n", FILE_APPEND);
        return $perms;
    }
    file_put_contents($logFile, "DEBUG getUserPermissions: NO PERMISSIONS FOUND\n", FILE_APPEND);
    return [];
}

/**
 * Require that the current user has at least one of the allowed permissions.
 * If not authorized, sends a JSON error and exits.
 * @param array|string $allowedPermissions
 */
function requirePermission($allowedPermissions) {
    if (is_string($allowedPermissions)) $allowedPermissions = [$allowedPermissions];
    $userPermissions = getUserPermissions();
    // Debug: Log permissions check
    $logFile = __DIR__ . '/../logs/require_permission_debug.log';
    file_put_contents($logFile, "DEBUG requirePermission: required=" . json_encode($allowedPermissions) . ", userPermissions=" . json_encode($userPermissions) . "\n", FILE_APPEND);
    if (empty($userPermissions)) {
        file_put_contents($logFile, "DEBUG requirePermission: NO PERMISSIONS\n", FILE_APPEND);
        sendJSON(false, null, 'Unauthorized: no permissions assigned');
    }
    foreach ($allowedPermissions as $p) {
        foreach ($userPermissions as $up) {
            // Allow exact or prefix match (e.g., 'Inventory' matches 'Inventory.view')
            if (strcasecmp($p, $up) === 0 || stripos($up, $p . '.') === 0) {
                file_put_contents($logFile, "DEBUG requirePermission: GRANTED for $p (matched $up)\n", FILE_APPEND);
                return true;
            }
        }
    }
    file_put_contents($logFile, "DEBUG requirePermission: DENIED\n", FILE_APPEND);
    sendJSON(false, null, 'Forbidden: insufficient permissions');
}

?>

