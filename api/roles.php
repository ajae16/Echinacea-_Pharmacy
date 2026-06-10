<?php
// DEBUG: Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../database/db_config.php';
// Handle assign/unassign actions BEFORE any method/input logic to guarantee no fallthrough
if (isset($_GET['action'])) {
        // Fetch current role for a user
        if ($_GET['action'] === 'userRole' && isset($_GET['accountId'])) {
            $accountId = $_GET['accountId'];
            $row = executeQuery("SELECT roleId FROM user_roles WHERE accountId = ? LIMIT 1", [$accountId], 's');
            $roleId = ($row && isset($row[0]['roleId'])) ? $row[0]['roleId'] : null;
            sendJSON(true, ['roleId' => $roleId]);
        }
    // Permissions checklist for role modal
    if ($_GET['action'] === 'permissions') {
        // If roleId is provided, return all permissions and those assigned to the role
        $roleId = isset($_GET['roleId']) ? (int)$_GET['roleId'] : null;
        $permissions = executeQuery("SELECT permissionId, permissionName, description FROM permissions ORDER BY permissionName ASC");
        $assigned = [];
        if ($roleId) {
            $assignedRows = executeQuery("SELECT permissionId FROM role_permissions WHERE roleId = ?", [$roleId], 'i');
            $assigned = array_column($assignedRows, 'permissionId');
        }
        sendJSON(true, [
            'permissions' => $permissions ?: [],
            'assigned' => $assigned
        ]);
    }
        // DEBUG: Output session userId and currentRoles
        $logFile = __DIR__ . '/../logs/roles_error.log';
            file_put_contents($logFile, 'DEBUG accountId: ' . ($_SESSION['accountId'] ?? 'not set') . "\n", FILE_APPEND);
        $debugRoles = getUserRoles();
        file_put_contents($logFile, 'DEBUG roles: ' . json_encode($debugRoles) . "\n", FILE_APPEND);
    $action = $_GET['action'];
    if ($action === 'assign') {
        $data = json_decode(file_get_contents('php://input'), true);
        requirePermission('RBAC.manage');
        if (empty($data['accountId']) || empty($data['roleId'])) sendJSON(false, null, 'accountId and roleId are required');
        // Only one role per user: update if exists, insert if not
        $existing = executeQuery("SELECT * FROM user_roles WHERE accountId = ?", [$data['accountId']], 's');
        if ($existing && count($existing) > 0) {
            // Update existing role
            $res = executeQuery("UPDATE user_roles SET roleId = ? WHERE accountId = ?", [(int)$data['roleId'], $data['accountId']], 'is');
            if ($res !== false) sendJSON(true, null, 'Role updated');
            sendJSON(false, null, 'Failed to update role');
        } else {
            // Insert new role
            $res = executeQuery("INSERT INTO user_roles (accountId, roleId) VALUES (?, ?)", [$data['accountId'], (int)$data['roleId']], 'si');
            if ($res !== false) sendJSON(true, null, 'Assigned');
            sendJSON(false, null, 'Failed to assign');
        }
    }
    if ($action === 'unassign') {
        $data = json_decode(file_get_contents('php://input'), true);
        requireRole(['Super Admin', 'Admin']);
            if (empty($data['accountId']) || empty($data['roleId'])) sendJSON(false, null, 'accountId and roleId are required');
            $res = executeQuery("DELETE FROM user_roles WHERE accountId = ? AND roleId = ?", [$data['accountId'], (int)$data['roleId']], 'si');
        if ($res !== false) sendJSON(true, null, 'Unassigned');
        sendJSON(false, null, 'Failed to unassign');
    }
    exit; // Prevent further processing
}

/**
 * Roles management API
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../database/db_config.php';
file_put_contents(__DIR__ . '/../logs/roles_error.log', "DEBUG roles.php session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/../logs/roles_error.log', "DEBUG roles.php POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($input);
        break;
    case 'DELETE':
        handleDelete($input);
        break;
    default:
        sendJSON(false, null, 'Invalid request method');
}

function handleGet() {
    // list roles
    $roles = executeQuery("SELECT * FROM roles ORDER BY roleId ASC");
    sendJSON(true, $roles ?: []);
}

function handlePost($data) {
    // Only admins/super admins can create roles
    requirePermission('RBAC.manage');

    $logFile = __DIR__ . '/roles_insert_permission_error.log';
    file_put_contents($logFile, "DEBUG handlePost input: " . print_r($data, true) . "\n", FILE_APPEND);

    if (empty($data['roleName'])) {
        file_put_contents($logFile, "DEBUG handlePost error: roleName is required\n", FILE_APPEND);
        sendJSON(false, null, 'roleName is required');
    }

    $roleName = sanitizeInput($data['roleName']);
    $description = sanitizeInput($data['description'] ?? '');

    // Check for duplicate role name (case-insensitive)
    $dupCheck = executeQuery("SELECT roleId FROM roles WHERE LOWER(roleName) = LOWER(?)", [$roleName], 's');
    if ($dupCheck && count($dupCheck) > 0) {
        file_put_contents($logFile, "DEBUG handlePost error: Duplicate role name\n", FILE_APPEND);
        sendJSON(false, null, 'Role name already exists');
    }

    $sql = "INSERT INTO roles (roleName, description) VALUES (?, ?)";
    file_put_contents($logFile, "DEBUG handlePost SQL: $sql | Params: " . json_encode([$roleName, $description]) . "\n", FILE_APPEND);
    try {
        $res = executeQuery($sql, [$roleName, $description], 'ss');
        file_put_contents($logFile, "DEBUG handlePost executeQuery result: " . var_export($res, true) . "\n", FILE_APPEND);

        // Log MySQL error if insert failed
        if ($res === false) {
            $conn = getDBConnection();
            $err = $conn ? $conn->error : 'no connection';
            file_put_contents($logFile, "DEBUG handlePost MySQL error: $err\n", FILE_APPEND);
            file_put_contents($logFile, "DEBUG handlePost error: Failed to create role\n", FILE_APPEND);
            sendJSON(false, null, 'Failed to create role');
        }

        // Insert permissions if provided
        $lastId = getLastInsertId();
        if (isset($data['permissions']) && is_array($data['permissions']) && $lastId) {
            foreach ($data['permissions'] as $permId) {
                $permRes = executeQuery("INSERT INTO role_permissions (roleId, permissionId) VALUES (?, ?)", [$lastId, (int)$permId], 'ii');
                $conn = getDBConnection();
                $permErr = $conn ? $conn->error : 'no connection';
                file_put_contents($logFile, "DEBUG handlePost permission insert: roleId=$lastId, permId=$permId, result=" . var_export($permRes, true) . ", error=$permErr\n", FILE_APPEND);
            }
        }
        file_put_contents($logFile, "DEBUG handlePost success, lastInsertId: $lastId\n", FILE_APPEND);
        sendJSON(true, ['roleId' => $lastId], 'Role created');
    } catch (Throwable $e) {
        file_put_contents($logFile, "DEBUG handlePost EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        sendJSON(false, null, 'Server error: ' . $e->getMessage());
    }
}

function handlePut($data) {
    requireRole(['Super Admin', 'Admin']);
    if (empty($data['roleId'])) sendJSON(false, null, 'roleId is required');

    $roleId = sanitizeInput($data['roleId']);
    $fields = [];
    $params = [];
    $types = '';

    if (isset($data['roleName'])) { $fields[] = 'roleName = ?'; $params[] = sanitizeInput($data['roleName']); $types .= 's'; }
    if (isset($data['displayName'])) { $fields[] = 'displayName = ?'; $params[] = sanitizeInput($data['displayName']); $types .= 's'; }
    if (isset($data['description'])) { $fields[] = 'description = ?'; $params[] = sanitizeInput($data['description']); $types .= 's'; }

    if (!empty($fields)) {
        $params[] = $roleId; $types .= 'i';
        $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE roleId = ?";
        $res = executeQuery($sql, $params, $types);
        if ($res === false) {
            sendJSON(false, null, 'Failed to update role');
        }
    }

    // Sync permissions if provided
    if (isset($data['permissions']) && is_array($data['permissions'])) {
        $newPerms = array_map('intval', $data['permissions']);
        // Get current permissions
        $currentRows = executeQuery("SELECT permissionId FROM role_permissions WHERE roleId = ?", [$roleId], 'i');
        $currentPerms = $currentRows ? array_column($currentRows, 'permissionId') : [];
        // Permissions to add
        $toAdd = array_diff($newPerms, $currentPerms);
        // Permissions to remove
        $toRemove = array_diff($currentPerms, $newPerms);
        // Add new permissions
        foreach ($toAdd as $permId) {
            executeQuery("INSERT INTO role_permissions (roleId, permissionId) VALUES (?, ?)", [$roleId, $permId], 'ii');
        }
        // Remove unchecked permissions
        foreach ($toRemove as $permId) {
            executeQuery("DELETE FROM role_permissions WHERE roleId = ? AND permissionId = ?", [$roleId, $permId], 'ii');
        }
    }

    sendJSON(true, null, 'Role updated');
}

function handleDelete($data) {
    requirePermission('RBAC.manage');
    $roleId = $_GET['id'] ?? ($data['roleId'] ?? null);
    if (!$roleId) sendJSON(false, null, 'roleId is required');

    $sql = "DELETE FROM roles WHERE roleId = ?";
    $res = executeQuery($sql, [(int)$roleId], 'i');
    if ($res !== false) sendJSON(true, null, 'Role deleted');
    sendJSON(false, null, 'Failed to delete role');
}

// Assign/unassign users via action param
// Handle assign/unassign actions BEFORE main switch to prevent falling through to handlePost
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'assign') {
        $data = json_decode(file_get_contents('php://input'), true);
        requireRole(['Super Admin', 'Admin']);
        if (empty($data['accountId']) || empty($data['roleId'])) sendJSON(false, null, 'accountId and roleId are required');
        // Get the role name being assigned
        $roleRow = executeQuery("SELECT roleName FROM roles WHERE roleId = ?", [(int)$data['roleId']], 'i');
        $roleName = $roleRow && isset($roleRow[0]['roleName']) ? $roleRow[0]['roleName'] : '';
        $currentRoles = getUserRoles();
        $isSuperAdmin = in_array('Super Admin', $currentRoles);
        $isAdmin = in_array('Admin', $currentRoles);
        if (strcasecmp($roleName, 'Super Admin') === 0 && !$isSuperAdmin) {
            sendJSON(false, null, 'Only Super Admin can assign Super Admin role');
        }
        if (strcasecmp($roleName, 'Super Admin') !== 0 && !$isSuperAdmin && !$isAdmin) {
            sendJSON(false, null, 'Unauthorized to assign this role');
        }
        // Prevent Admin from assigning Super Admin
        if ($isAdmin && strcasecmp($roleName, 'Super Admin') === 0) {
            sendJSON(false, null, 'Admin cannot assign Super Admin role');
        }
            $res = executeQuery("INSERT INTO user_roles (accountId, roleId) VALUES (?, ?)", [$data['accountId'], (int)$data['roleId']], 'si');
        if ($res !== false) sendJSON(true, null, 'Assigned');
        sendJSON(false, null, 'Failed to assign');
    }
    if ($action === 'unassign') {
        $data = json_decode(file_get_contents('php://input'), true);
        requireRole(['Super Admin', 'Admin']);
        if (empty($data['accountId']) || empty($data['roleId'])) sendJSON(false, null, 'accountId and roleId are required');
            $res = executeQuery("DELETE FROM user_roles WHERE accountId = ? AND roleId = ?", [$data['accountId'], (int)$data['roleId']], 'si');
        if ($res !== false) sendJSON(true, null, 'Unassigned');
        sendJSON(false, null, 'Failed to unassign');
    }
    exit; // Prevent further processing
}

?>