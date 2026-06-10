<?php
file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] accounts.php called: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
// Error logging helper
function logAccountApiError($msg, $data = null) {
    $logFile = __DIR__ . '/../logs/accounts_error.log';
    $entry = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    if ($data !== null) {
        $entry .= print_r($data, true) . "\n";
    }
    file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Employee Accounts API Endpoint
 */

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

define('DB_ACCESS', true);
require_once '../database/db_config.php';

session_start();


$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] method: $method\n", FILE_APPEND);
file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] input: " . json_encode($input) . "\n", FILE_APPEND);


    switch ($method) {
        case 'GET':
            file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] case GET\n", FILE_APPEND);
            handleGet();
            break;
        case 'POST':
            file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] case POST\n", FILE_APPEND);
            handlePost($input);
            break;
        case 'PUT':
            file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] case PUT\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] before handlePut call\n", FILE_APPEND);
            handlePut($input);
            break;
        case 'DELETE':
            file_put_contents(__DIR__ . '/../logs/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] case DELETE\n", FILE_APPEND);
            handleDelete($input);
            break;
        default:
            file_put_contents(__DIR__ . '/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] case DEFAULT\n", FILE_APPEND);
            sendJSON(false, null, 'Invalid request method');
    }

function handleGet() {
    $id = $_GET['id'] ?? null;
    $username = $_GET['username'] ?? null;
    $view = $_GET['view'] ?? null;

    // If view=pending_users, return from SQL view
    if ($view === 'pending_users') {
        $sql = "SELECT * FROM view_pending_users";
        $result = executeQuery($sql);
        sendJSON(true, $result ?: []);
    }

    // If view=online_users, return from view_online_users
    if ($view === 'online_users') {
        $sql = "SELECT accountId, username, fullName, loginTime, lastActivity, ipAddress, roles FROM view_online_users ORDER BY lastActivity DESC";
        $result = executeQuery($sql);
        // Ensure roles is always a string (comma separated) for frontend
        if ($result) {
            foreach ($result as &$row) {
                if (isset($row['roles']) && is_array($row['roles'])) {
                    $row['roles'] = implode(', ', $row['roles']);
                }
            }
        }
        sendJSON(true, $result ?: []);
    }

    if ($id) {
        $sql = "SELECT accountId, firstName, middleName, lastName, fullName, phoneNumber, email, status, username, image, birthday, address, sex, createdAt
                FROM accounts WHERE accountId = ? AND isArchive = 0";
        $result = executeQuery($sql, [$id], 's');
        if ($result && isset($result[0]['accountId'])) {
            $roles = executeQuery("SELECT r.roleName FROM user_roles ur JOIN roles r ON ur.roleId = r.roleId WHERE ur.accountId = ?", [$id], 's');
            $result[0]['roles'] = $roles ? array_column($roles, 'roleName') : [];
            // Fetch schedule
            $schedRows = executeQuery("SELECT day FROM user_schedule WHERE accountId = ?", [$id], 's');
            $result[0]['schedule'] = $schedRows ? array_column($schedRows, 'day') : [];
        }
        sendJSON(true, $result[0] ?? null);
    } elseif ($username) {
        $sql = "SELECT accountId, firstName, middleName, lastName, fullName, phoneNumber, email, status, username, image, birthday, address, sex, createdAt
                FROM accounts WHERE username = ? AND isArchive = 0";
        $result = executeQuery($sql, [$username], 's');
        if ($result && isset($result[0]['accountId'])) {
            $roles = executeQuery("SELECT r.roleName FROM user_roles ur JOIN roles r ON ur.roleId = r.roleId WHERE ur.accountId = ?", [$result[0]['accountId']], 's');
            $result[0]['roles'] = $roles ? array_column($roles, 'roleName') : [];
            // Fetch schedule
            $schedRows = executeQuery("SELECT day FROM user_schedule WHERE accountId = ?", [$result[0]['accountId']], 's');
            $result[0]['schedule'] = $schedRows ? array_column($schedRows, 'day') : [];
        }
        sendJSON(true, $result[0] ?? null);
    } else {
        $sql = "SELECT accountId, firstName, middleName, lastName, fullName, phoneNumber, email, status, username, image, birthday, address, sex, createdAt
                FROM accounts WHERE isActive = TRUE AND isArchive = 0 ORDER BY createdAt DESC";
        $result = executeQuery($sql);
        if ($result) {
            foreach ($result as &$row) {
                $roles = executeQuery("SELECT r.roleName FROM user_roles ur JOIN roles r ON ur.roleId = r.roleId WHERE ur.accountId = ?", [$row['accountId']], 's');
                $row['roles'] = $roles ? array_column($roles, 'roleName') : [];
                // Fetch schedule
                $schedRows = executeQuery("SELECT day FROM user_schedule WHERE accountId = ?", [$row['accountId']], 's');
                $row['schedule'] = $schedRows ? array_column($schedRows, 'day') : [];
            }
        }
        sendJSON(true, $result ?: []);
    }
}

function handlePost($data) {
    try {
        requirePermission('Account.create');
        $required = ['firstName', 'lastName', 'email', 'username', 'password'];
        $missing = validateRequired($data, $required);
        if (!empty($missing)) {
            logAccountApiError('Missing required fields', $missing);
            sendJSON(false, null, 'Missing required fields: ' . implode(', ', $missing));
        }
        // Check if username already exists
        $username = sanitizeInput($data['username']);
        $sql = "SELECT accountId FROM accounts WHERE username = ?";
        $existing = executeQuery($sql, [$username], 's');
        if (!empty($existing)) {
            logAccountApiError('Username already exists', $username);
            sendJSON(false, null, 'Username already exists');
        }
            // Birthday validation
            if (isset($data['birthday']) && $data['birthday']) {
                $birthDate = strtotime($data['birthday']);
                $minDate = strtotime('-18 years');
                if ($birthDate > $minDate) {
                    logAccountApiError('User must be at least 18 years old', $data);
                    sendJSON(false, null, 'User must be at least 18 years old.');
                }
            }
        // Check if email already exists
        $email = sanitizeInput($data['email']);
        $sql = "SELECT accountId FROM accounts WHERE email = ?";
        $existing = executeQuery($sql, [$email], 's');
        if (!empty($existing)) {
            logAccountApiError('Email already exists', $email);
            sendJSON(false, null, 'Email already exists');
        }
        $firstName = sanitizeInput($data['firstName']);
        $middleName = sanitizeInput($data['middleName'] ?? '');
        $lastName = sanitizeInput($data['lastName']);
        $fullName = sanitizeInput($data['fullName'] ?? "$firstName $middleName $lastName");
        $phoneNumber = sanitizeInput($data['phoneNumber'] ?? '');
        // $role removed; role assignment handled by RBAC
        $status = sanitizeInput($data['status'] ?? 'active');
        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        $image = sanitizeInput($data['image'] ?? 'Pharmacy Icons/profile icon.png');
        $schedule = $data['schedule'] ?? [];
        $birthday = sanitizeInput($data['birthday'] ?? null);
        $address = sanitizeInput($data['address'] ?? '');
        $sex = sanitizeInput($data['sex'] ?? null);
        $employeeId = generateAccountId();
        $sql = "INSERT INTO accounts (accountId, firstName, middleName, lastName, fullName,
            phoneNumber, email, status, username, password, image, birthday, address, sex)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = executeQuery($sql, [
            $employeeId, $firstName, $middleName, $lastName, $fullName,
            $phoneNumber, $email, $status, $username, $password, $image, $birthday, $address, $sex
        ], 'ssssssssssssss');
        // Assign role (RBAC) and insert schedule
        if ($result !== false) {
            executeQuery("INSERT INTO user_roles (accountId, roleId) VALUES (?, ?)", [$employeeId, 4], 'si');
            // Insert schedule days
            if (is_array($schedule)) {
                foreach ($schedule as $day) {
                    executeQuery("INSERT INTO user_schedule (accountId, day) VALUES (?, ?)", [$employeeId, $day], 'ss');
                }
            }
            $accountId = $_SESSION['accountId'] ?? 'system';
            $sessionUsername = $_SESSION['username'] ?? 'system';
            logActivity($accountId, $sessionUsername, 'CREATE', 'ACCOUNTS', $employeeId, 
                   "Created account: $username");
            sendJSON(true, ['id' => $employeeId], 'Account created successfully');
        } else {
            logAccountApiError('Failed to create account', $data);
            sendJSON(false, null, 'Failed to create account');
        }
    } catch (Exception $e) {
        logAccountApiError('Exception in handlePost', $e->getMessage());
        sendJSON(false, null, 'Server error: ' . $e->getMessage());
    }
}

file_put_contents(__DIR__ . '/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] before handlePut definition\n", FILE_APPEND);
function handlePut($data) {
    file_put_contents(__DIR__ . '/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] inside handlePut start\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/accounts_top_debug.log', "[" . date('Y-m-d H:i:s') . "] handlePut called: " . json_encode($data) . "\n", FILE_APPEND);
    try {
        logAccountApiError('handlePut called', $data);
        try {
            requirePermission('Account.update');
        } catch (Exception $e) {
            logAccountApiError('Permission error in handlePut', $e->getMessage());
            throw $e;
        }
        if (empty($data['id'])) {
            logAccountApiError('Missing id in handlePut', $data);
            sendJSON(false, null, 'Account ID is required');
        }
        $id = sanitizeInput($data['id']);
        // Only allow self-update unless user has Account.manage or Account.admin
        $currentUserId = $_SESSION['accountId'] ?? null;
        $userPerms = getUserPermissions();
        $isAdmin = in_array('Account.update', $userPerms);
        if (!$isAdmin && $id !== $currentUserId) {
            logAccountApiError('Attempt to update another user without admin rights', ['currentUserId' => $currentUserId, 'targetId' => $id]);
            sendJSON(false, null, 'You can only update your own account');
        }
            // Birthday validation
            if (isset($data['birthday']) && $data['birthday']) {
                $birthDate = strtotime($data['birthday']);
                $minDate = strtotime('-18 years');
                if ($birthDate > $minDate) {
                    logAccountApiError('User must be at least 18 years old', $data);
                    sendJSON(false, null, 'User must be at least 18 years old.');
                }
            }
        $updateFields = [];
        $params = [];
        $types = '';
        $allowedFields = ['firstName', 'middleName', 'lastName', 'fullName', 'phoneNumber', 'email', 'status', 'username', 'image', 'birthday', 'address', 'sex'];
                // Handle schedule update
                if (isset($data['schedule']) && is_array($data['schedule'])) {
                    // Remove old schedule
                    executeQuery("DELETE FROM user_schedule WHERE accountId = ?", [$id], 's');
                    // Insert new schedule
                    foreach ($data['schedule'] as $day) {
                        executeQuery("INSERT INTO user_schedule (accountId, day) VALUES (?, ?)", [$id, $day], 'ss');
                    }
                }
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                // Check username uniqueness if updating username
                if ($field === 'username') {
                    $sql = "SELECT accountId FROM accounts WHERE username = ? AND accountId != ?";
                    $existing = executeQuery($sql, [sanitizeInput($data[$field]), $id], 'ss');
                    if (!empty($existing)) {
                        logAccountApiError('Username already exists in handlePut', $data[$field]);
                        sendJSON(false, null, 'Username already exists');
                    }
                }
                // Check email uniqueness if updating email
                if ($field === 'email') {
                    $sql = "SELECT accountId FROM accounts WHERE email = ? AND accountId != ?";
                    $existing = executeQuery($sql, [sanitizeInput($data[$field]), $id], 'ss');
                    if (!empty($existing)) {
                        logAccountApiError('Email already exists in handlePut', $data[$field]);
                        sendJSON(false, null, 'Email already exists');
                    }
                }
                $updateFields[] = "$field = ?";
                $params[] = sanitizeInput($data[$field]);
                $types .= 's';
            }
        }
        // Handle password update separately with current password verification
        if (isset($data['password']) && !empty($data['password'])) {
            logAccountApiError('Password update attempted in handlePut', isset($data['currentPassword']) ? 'With currentPassword' : 'No currentPassword');
            // If currentPassword is provided, verify it first
            if (isset($data['currentPassword'])) {
                $sql = "SELECT password FROM accounts WHERE accountId = ?";
                $result = executeQuery($sql, [$id], 's');
                if (empty($result)) {
                    sendJSON(false, null, 'User not found');
                }
                $currentHash = $result[0]['password'];
                if (!password_verify($data['currentPassword'], $currentHash)) {
                    sendJSON(false, null, 'Current password is incorrect');
                }
            }
            $updateFields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
            $types .= 's';
        }
        if (empty($updateFields)) {
            logAccountApiError('No fields to update in handlePut', $data);
            sendJSON(false, null, 'No fields to update');
        }
        $params[] = $id;
        $types .= 's';
        $sql = "UPDATE accounts SET " . implode(', ', $updateFields) . " WHERE accountId = ?";
        $result = executeQuery($sql, $params, $types);
        if ($result !== false) {
            $accountId = $_SESSION['accountId'] ?? 'system';
            $username = $_SESSION['username'] ?? 'system';
            logActivity($accountId, $username, 'UPDATE', 'ACCOUNTS', $id, "Updated account");
            sendJSON(true, null, 'Account updated successfully');
        } else {
            logAccountApiError('Failed to update account in handlePut', ['sql' => $sql, 'params' => $params, 'types' => $types]);
            sendJSON(false, null, 'Failed to update account');
        }
    } catch (Exception $e) {
        logAccountApiError('Exception in handlePut', $e->getMessage());
        sendJSON(false, null, 'Server error: ' . $e->getMessage());
    }
}

function handleDelete($data) {
    requirePermission('Account.delete');
    $id = $data['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        sendJSON(false, null, 'Account ID is required');
    }
    $id = sanitizeInput($id);
    $accountId = $_SESSION['accountId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';
    beginTransaction();
    try {
        // Archive account: set isArchive=1 and copy to archive table
        $sql = "UPDATE accounts SET isArchive = 1 WHERE accountId = ?";
        executeQuery($sql, [$id], 's');
        $sql = "INSERT INTO archive_accounts (
            accountId, firstName, middleName, lastName, fullName, phoneNumber, email, status, username, password, image, birthday, address, sex, isActive, createdAt, updatedAt, isArchive
        ) SELECT 
            accountId, firstName, middleName, lastName, fullName, phoneNumber, email, status, username, password, image, birthday, address, sex, isActive, createdAt, updatedAt, isArchive
        FROM accounts WHERE accountId = ?";
        executeQuery($sql, [$id], 's');
        commitTransaction();
        logActivity($accountId, $username, 'ARCHIVE', 'ACCOUNTS', $id, "Archived account");
        sendJSON(true, null, 'Account archived successfully');
    } catch (Exception $e) {
        rollbackTransaction();
        sendJSON(false, null, 'Failed to archive account');
    }
}

function generateAccountId() {
    $date = date('Ymd');
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("CALL GetNextCounter('Acc', @counter)");
    $stmt->execute();
    $stmt->close();
    
    $result = $conn->query("SELECT @counter AS counter");
    $row = $result->fetch_assoc();
    $counter = str_pad($row['counter'], 4, '0', STR_PAD_LEFT);
    
    return "Acc{$date}{$counter}";
}
?>