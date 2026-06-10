<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Suppliers API Endpoint
 */

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


// Global error/exception handler for debugging
set_exception_handler(function($e) {
    error_log('UNCAUGHT EXCEPTION: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    file_put_contents(__DIR__ . '/../logs/suppliers_api_debug.log', "UNCAUGHT EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error', 'error' => $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = "ERROR [$errno] $errstr in $errfile on line $errline";
    error_log($msg);
    file_put_contents(__DIR__ . '/../logs/suppliers_api_debug.log', $msg . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error', 'error' => $errstr]);
    exit;
});

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        handleGet($id);
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($id, $input);
        break;
    case 'DELETE':
        handleDelete($id);
        break;
    default:
        sendJSON(false, null, 'Invalid request method');
}

function handleGet($id) {
    if ($id) {
        // Get single supplier
        $sql = "SELECT * FROM suppliers WHERE supplierId = ? AND isArchive = 0";
        $result = executeQuery($sql, [$id], 's');
        $supplier = $result ? $result[0] : null;
        sendJSON(true, $supplier);
    } else {
        // Get all suppliers
        $sql = "SELECT * FROM suppliers WHERE isArchive = 0 ORDER BY name ASC";
        $result = executeQuery($sql);
        sendJSON(true, $result ?: []);
    }
}

function handlePost($data) {
        require_once '../database/db_config.php';
        $perms = function_exists('getUserPermissions') ? getUserPermissions() : [];
        file_put_contents(__DIR__ . '/suppliers_api_debug.log', "DEBUG handlePost: data=" . json_encode($data) . ", SESSION=" . json_encode($_SESSION) . ", PERMISSIONS=" . json_encode($perms) . "\n", FILE_APPEND);
    if (empty($data['name']) || empty($data['contactPerson']) || empty($data['phoneNumber']) ||
        empty($data['email']) || empty($data['address'])) {
        sendJSON(false, null, 'All fields are required');
    }
    requirePermission('Suppliers.create');

    // Validate email uniqueness
    $checkEmail = executeQuery("SELECT supplierId FROM suppliers WHERE email = ? AND isArchive = 0", [$data['email']], 's');
    if ($checkEmail) {
        sendJSON(false, null, 'Email already exists');
    }

    // Generate supplier ID
    $counter = getNextCounter('Sup');
    $date = date('YmdHi');
    $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    $supplierId = "SUP{$date}{$random}";

    $sql = "INSERT INTO suppliers (supplierId, name, contactPerson, phoneNumber, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $result = executeQuery($sql, [
        $supplierId,
        sanitizeInput($data['name']),
        sanitizeInput($data['contactPerson']),
        sanitizeInput($data['phoneNumber']),
        sanitizeInput($data['email']),
        sanitizeInput($data['address']),
        $data['status'] ?? 'active'
    ], 'sssssss');

    if ($result !== false) {
        $accountId = $_SESSION['accountId'] ?? 'system';
        $username = $_SESSION['username'] ?? 'system';
        logActivity($accountId, $username, 'CREATE', 'SUPPLIERS', $supplierId, "Created supplier: {$data['name']}");

        sendJSON(true, ['id' => $supplierId], 'Supplier created successfully');
    } else {
        sendJSON(false, null, 'Failed to create supplier');
    }
}

function handlePut($id, $data) {
        require_once '../database/db_config.php';
        $perms = function_exists('getUserPermissions') ? getUserPermissions() : [];
        file_put_contents(__DIR__ . '/suppliers_api_debug.log', "DEBUG handlePut: id=" . json_encode($id) . ", data=" . json_encode($data) . ", SESSION=" . json_encode($_SESSION) . ", PERMISSIONS=" . json_encode($perms) . "\n", FILE_APPEND);
    if (!$id) {
        sendJSON(false, null, 'Supplier ID is required');
    }
    requirePermission('Suppliers.update');

    // Check if supplier exists and is not archived
    $checkSql = "SELECT supplierId FROM suppliers WHERE supplierId = ? AND isArchive = 0";
    $existing = executeQuery($checkSql, [$id], 's');
    if (!$existing) {
        sendJSON(false, null, 'Supplier not found or archived');
    }

    $sql = "UPDATE suppliers SET name = ?, contactPerson = ?, phoneNumber = ?, email = ?, address = ?, status = ?, updatedAt = NOW() WHERE supplierId = ? AND isArchive = 0";
    $result = executeQuery($sql, [
        sanitizeInput($data['name']),
        sanitizeInput($data['contactPerson']),
        sanitizeInput($data['phoneNumber']),
        sanitizeInput($data['email']),
        sanitizeInput($data['address']),
        $data['status'] ?? 'active',
        $id
    ], 'sssssss');

    if ($result > 0) {
        $accountId = $_SESSION['accountId'] ?? 'system';
        $username = $_SESSION['username'] ?? 'system';
        logActivity($accountId, $username, 'UPDATE', 'SUPPLIERS', $id, "Updated supplier: {$data['name']}");

        sendJSON(true, null, 'Supplier updated successfully');
    } else {
        sendJSON(false, null, 'Failed to update supplier or no changes made');
    }
}

function handleDelete($id) {
        require_once '../database/db_config.php';
        $perms = function_exists('getUserPermissions') ? getUserPermissions() : [];
        file_put_contents(__DIR__ . '/suppliers_api_debug.log', "DEBUG handleDelete: id=" . json_encode($id) . ", SESSION=" . json_encode($_SESSION) . ", PERMISSIONS=" . json_encode($perms) . "\n", FILE_APPEND);
        require_once '../database/db_config.php';
        $perms = function_exists('getUserPermissions') ? getUserPermissions() : [];
        file_put_contents(__DIR__ . '/suppliers_api_debug.log', "DEBUG handleGet: id=" . json_encode($id) . ", SESSION=" . json_encode($_SESSION) . ", PERMISSIONS=" . json_encode($perms) . "\n", FILE_APPEND);
    if (!$id) {
        sendJSON(false, null, 'Supplier ID is required');
    }
    requirePermission('Suppliers.delete');

    // RBAC now controls delete access; legacy admin/manager check removed

    $accountId = $_SESSION['accountId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';

    // Archive supplier: set isArchive=1 and copy to archive table
    $sql = "UPDATE suppliers SET isArchive = 1 WHERE supplierId = ?";
    $updateResult = executeQuery($sql, [$id], 's');

    // Prevent duplicate archive
    $checkSql = "SELECT supplierId FROM archive_suppliers WHERE supplierId = ?";
    $exists = executeQuery($checkSql, [$id], 's');
    if ($exists && count($exists) > 0) {
        sendJSON(false, null, 'Supplier is already archived.');
    }

    $sql = "INSERT INTO archive_suppliers (
        supplierId, name, contactPerson, phoneNumber, email, address, status, isActive, createdAt, updatedAt, isArchive
    ) SELECT 
        supplierId, name, contactPerson, phoneNumber, email, address, status, isActive, createdAt, updatedAt, isArchive
    FROM suppliers WHERE supplierId = ?";
    $insertResult = executeQuery($sql, [$id], 's');
    if ($insertResult === false) {
        sendJSON(false, null, 'Failed to archive supplier (DB error).');
    }
    logActivity($accountId, $username, 'ARCHIVE', 'SUPPLIERS', $id, "Archived supplier");
    sendJSON(true, null, 'Supplier archived successfully');
}

function getNextCounter($type) {
    $sql = "CALL GetNextCounter(?, @nextVal)";
    executeQuery($sql, [$type], 's');

    $result = executeQuery("SELECT @nextVal as counter");
    return $result ? $result[0]['counter'] : 1;
}
?>
