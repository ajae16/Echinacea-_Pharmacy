<?php
/**
 * Archive API Endpoint
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

define('DB_ACCESS', true);
require_once '../database/db_config.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? $_GET['type'] ?? 'inventory';

switch ($method) {
    case 'GET':
        handleGet($type);
        break;
    case 'POST':
        handleRestore($type, $input);
        break;
    case 'DELETE':
        handlePermanentDelete($type, $input);
        break;
    default:
        sendJSON(false, null, 'Invalid request method');
}

function handleGet($type) {
    requirePermission('Archive.view');
    $validTypes = ['inventory', 'sales', 'records', 'accounts', 'suppliers', 'deliveries'];

    if (!in_array($type, $validTypes)) {
        sendJSON(false, null, 'Invalid archive type');
    }

    if ($type === 'accounts') {
        file_put_contents(__DIR__ . '/../logs/require_permission_debug.log', "DEBUG handleGet: called for accounts\n", FILE_APPEND);
    }
    $table = "archive_$type";
    $sql = "SELECT * FROM $table WHERE isArchive = 1 ORDER BY archivedAt DESC";
    if ($type === 'accounts') {
        file_put_contents(__DIR__ . '/../logs/require_permission_debug.log', "DEBUG handleGet: about to query accounts: $sql\n", FILE_APPEND);
    }
    $result = executeQuery($sql);

    // Enhanced debug: log SQL, type, and result sample
    $debugMsg = "DEBUG handleGet: type=$type, sql=$sql, rows=" . (is_array($result) ? count($result) : 'ERR');
    if (is_array($result) && count($result) > 0) {
        $sample = json_encode(array_slice($result, 0, 1));
        $debugMsg .= ", sample=" . $sample;
    }
    $debugMsg .= "\n";
    file_put_contents(__DIR__ . '/../logs/require_permission_debug.log', $debugMsg, FILE_APPEND);

    sendJSON(true, $result ?: []);
}

function handleRestore($type, $data) {
    requirePermission('Archive');
    $keyMap = [
        'inventory' => 'productId',
        'sales' => 'salesId',
        'records' => 'accountId',
        'accounts' => 'accountId',
        'suppliers' => 'supplierId',
        'deliveries' => 'supplyId',
    ];
    $key = $keyMap[$type] ?? 'id';
    if (empty($data[$key])) {
        sendJSON(false, null, strtoupper($key) . ' is required');
    }
    $id = sanitizeInput($data[$key]);
    $userId = $_SESSION['userId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';
    beginTransaction();
    try {
        $mainTableMap = [
            'inventory' => 'inventory',
            'sales' => 'sales',
            'records' => 'accounts',
            'accounts' => 'accounts',
            'suppliers' => 'suppliers',
            'deliveries' => 'supply_deliveries',
        ];
        $mainTable = $mainTableMap[$type] ?? '';
        if (!$mainTable) throw new Exception('Invalid type');

        // Restore by updating isArchive=0
        $updateSql = "UPDATE $mainTable SET isArchive = 0 WHERE $key = ?";
        executeQuery($updateSql, [$id], 's');

        // Delete from archive table
        $archiveTable = "archive_$type";
        $deleteSql = "DELETE FROM $archiveTable WHERE $key = ?";
        executeQuery($deleteSql, [$id], 's');

        commitTransaction();

        logActivity($userId, $username, 'RESTORE', strtoupper($type), $id, "Restored from archive");
        sendJSON(true, null, 'Item restored successfully');

    } catch (Exception $e) {
        rollbackTransaction();
        sendJSON(false, null, 'Failed to restore item: ' . $e->getMessage());
    }
}

function handlePermanentDelete($type, $data) {
    requirePermission('Archive');
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        sendJSON(false, null, 'ID is required');
    }
    
    $id = sanitizeInput($id);
    $userId = $_SESSION['userId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';
    
    $table = "archive_$type";
    $sql = "DELETE FROM $table WHERE id = ?";
    $result = executeQuery($sql, [$id], 's');
    
    if ($result !== false) {
        logActivity($userId, $username, 'DELETE', strtoupper($type), $id, "Permanently deleted from archive");
        sendJSON(true, null, 'Item permanently deleted');
    } else {
        sendJSON(false, null, 'Failed to delete item');
    }
}
?>

