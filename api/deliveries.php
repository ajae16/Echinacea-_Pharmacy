<?php
/**
 * Supply Deliveries API Endpoint
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Prevent HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

define('DB_ACCESS', true);
require_once '../database/db_config.php';

session_start();

// Map common incoming status values to DB enum values
function mapStatusToEnum($status) {
    if ($status === null) return null;
    $s = strtolower(trim($status));
    if (in_array($s, ['pending', 'pend'], true)) return 'Pending';
    if (in_array($s, ['delivered', 'received', 'received', 'rec'], true)) return 'Received';
    if (in_array($s, ['cancelled', 'canceled', 'cancel'], true)) return 'Cancelled';
    return null;
}

// Ensure API always returns JSON and do not emit HTML error pages
header('Content-Type: application/json; charset=utf-8');
// Turn off HTML error display (errors will be logged);
ini_set('display_errors', 0);

// Convert PHP errors to exceptions so they are handled by exception handler
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Convert uncaught exceptions into JSON responses
set_exception_handler(function($e) {
    http_response_code(500);
    if (ob_get_length()) {
        @ob_end_clean();
    }
    // Log exception details to a file for debugging
    $logFile = __DIR__ . '/deliveries_error.log';
    $err = sprintf("[%s] Uncaught exception: %s in %s on line %d\nStack trace:\n%s\n\n", date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents($logFile, $err, FILE_APPEND);

    // Use sendJSON if available, otherwise echo minimal JSON
    if (function_exists('sendJSON')) {
        sendJSON(false, null, 'Server error: ' . $e->getMessage());
    } else {
        echo json_encode(['success' => false, 'data' => null, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
});

$method = $_SERVER['REQUEST_METHOD'];

// Read raw body for logging and parsing
$rawInput = file_get_contents('php://input');

// Handle input data - support both JSON and form data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode($rawInput, true);
    // If JSON parsing failed, log raw input for debugging
    if ($input === null) {
        $logFile = __DIR__ . '/deliveries_raw_input.log';
        $headers = [];
        foreach (['CONTENT_TYPE','CONTENT_LENGTH','HTTP_USER_AGENT','HTTP_ACCEPT','REQUEST_METHOD'] as $h) {
            $headers[$h] = $_SERVER[$h] ?? '';
        }
        $entry = sprintf("[%s] CONTENT_TYPE=%s CONTENT_LENGTH=%s REQUEST_METHOD=%s\nRAW_BODY_LEN=%d RAW_BODY=%s\nRAW_BODY_HEX=%s\nHEADERS=%s\n\n",
            date('Y-m-d H:i:s'), $contentType, $_SERVER['CONTENT_LENGTH'] ?? '', $_SERVER['REQUEST_METHOD'] ?? '', strlen($rawInput), $rawInput, bin2hex($rawInput), json_encode($headers)
        );
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
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
    $view = $_GET['view'] ?? null;
    // If view=deliveries_today, return from SQL view
    if ($view === 'deliveries_today') {
        $sql = "SELECT * FROM view_deliveries_today";
        $result = executeQuery($sql);
        sendJSON(true, $result ?: []);
    }
    if ($id) {
        // Get single delivery
        $sql = "SELECT sd.*, s.name as supplierName, i.brand, i.genericName
            FROM supply_deliveries sd
            LEFT JOIN suppliers s ON sd.supplierId = s.supplierId AND s.isArchive = 0
            LEFT JOIN inventory i ON sd.productId = i.productId AND i.isArchive = 0
            WHERE sd.supplyId = ? AND sd.isActive = TRUE AND sd.isArchive = 0";
        $result = executeQuery($sql, [$id], 's');
        $delivery = $result ? $result[0] : null;
        if ($delivery) {
            $delivery['status'] = mapStatusForClient($delivery['status'] ?? null);
        }
        sendJSON(true, $delivery);
    } else {
        // Get all deliveries with optional filtering
        $status = $_GET['status'] ?? null;
        $supplierId = $_GET['supplierId'] ?? null;
        $productId = $_GET['productId'] ?? null;

        $whereConditions = ["sd.isActive = TRUE", "sd.isArchive = 0"];
        $params = [];
        $types = '';

        if ($status) {
            // Normalize requested status to DB enum values
            $mapped = mapStatusToEnum($status);
            if ($mapped !== null) {
                $whereConditions[] = "sd.status = ?";
                $params[] = $mapped;
                $types .= 's';
            }
        }
        if ($supplierId) {
            $whereConditions[] = "sd.supplierId = ?";
            $params[] = $supplierId;
            $types .= 's';
        }
        if ($productId) {
            $whereConditions[] = "sd.productId = ?";
            $params[] = $productId;
            $types .= 's';
        }

        $sql = "SELECT sd.*, s.name as supplierName, i.brand, i.genericName
            FROM supply_deliveries sd
            LEFT JOIN suppliers s ON sd.supplierId = s.supplierId AND s.isArchive = 0
            LEFT JOIN inventory i ON sd.productId = i.productId AND i.isArchive = 0
            WHERE " . implode(' AND ', $whereConditions) . "
            ORDER BY sd.createdAt DESC";

        $result = executeQuery($sql, $params, $types);
        // Normalize status values for client (use lowercase 'pending'/'delivered' etc.)
        if ($result) {
            foreach ($result as &$row) {
                $row['status'] = mapStatusForClient($row['status'] ?? null);
            }
            unset($row);
        }
        sendJSON(true, $result ?: []);
    }
}

// Map DB enum status values to client-friendly status strings
function mapStatusForClient($status) {
    if ($status === null) return 'pending';
    $s = strtolower(trim($status));
    if ($s === 'pending') return 'pending';
    if ($s === 'received') return 'delivered';
    if ($s === 'cancelled' || $s === 'canceled') return 'cancelled';
    return strtolower($status);
}

function handlePost($data) {
        // Dedicated log for supply new product errors
        $supplyLogFile = __DIR__ . '/supply_new_product_error.log';
        $supplyLogEntry = sprintf("[%s] SUPPLY NEW PRODUCT POST: %s\n", date('Y-m-d H:i:s'), json_encode($data));
        @file_put_contents($supplyLogFile, $supplyLogEntry, FILE_APPEND);
     requirePermission('Suppliers.create');
    // Log incoming request for debugging
    $logFile = __DIR__ . '/deliveries_raw_input.log';
    $logEntry = sprintf("[%s] POST REQUEST: %s\n", date('Y-m-d H:i:s'), json_encode($data));
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    if (!is_array($data)) {
        http_response_code(400);
        sendJSON(false, null, 'Invalid request body');
    }

    if (empty($data['supplierId']) || empty($data['productId']) || empty($data['quantity']) ||
        empty($data['unitPrice']) || empty($data['deliveryDate'])) {
        $errLog = sprintf("[%s] ERROR: Missing required fields. supplierId=%s, productId=%s, quantity=%s, unitPrice=%s, deliveryDate=%s\n",
            date('Y-m-d H:i:s'),
            var_export($data['supplierId'], true),
            var_export($data['productId'], true),
            var_export($data['quantity'], true),
            var_export($data['unitPrice'], true),
            var_export($data['deliveryDate'], true)
        );
        @file_put_contents($supplyLogFile, $errLog, FILE_APPEND);
        http_response_code(400);
        sendJSON(false, null, 'All required fields must be provided');
    }

    // Log supplier and product validation
    $supplierCheck = executeQuery("SELECT supplierId FROM suppliers WHERE supplierId = ? AND isActive = TRUE AND isArchive = 0", [$data['supplierId']], 's');
    $productCheck = executeQuery("SELECT productId FROM inventory WHERE productId = ? AND isArchive = 0", [$data['productId']], 's');
    if (!$supplierCheck || !$productCheck) {
        $errLog = sprintf("[%s] ERROR: Validation failed. supplierId=%s (exists=%s), productId=%s (exists=%s)\n",
            date('Y-m-d H:i:s'),
            var_export($data['supplierId'], true), $supplierCheck ? 'YES' : 'NO',
            var_export($data['productId'], true), $productCheck ? 'YES' : 'NO'
        );
        @file_put_contents($supplyLogFile, $errLog, FILE_APPEND);
    }

    $logEntry = sprintf("[%s] VALIDATION: supplierId=%s (exists=%s), productId=%s (exists=%s)\n",
        date('Y-m-d H:i:s'), $data['supplierId'], $supplierCheck ? 'YES' : 'NO',
        $data['productId'], $productCheck ? 'YES' : 'NO');
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    if (!$supplierCheck) {
        sendJSON(false, null, 'Supplier not found or inactive');
    }
    if (!$productCheck) {
        sendJSON(false, null, 'Product not found or archived');
    }

    // Generate supply ID
    $counter = getNextCounter('Supply');
    $date = date('YmdHi');
    $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    $supplyId = "DELI{$date}{$random}";


    // Ensure deliveryDate is null if empty or not set, and log it
    $deliveryDate = isset($data['deliveryDate']) && trim($data['deliveryDate']) !== '' ? sanitizeInput($data['deliveryDate']) : null;
    if ($deliveryDate === '') $deliveryDate = null;
    $deliveredDate = !empty($data['deliveredDate']) ? sanitizeInput($data['deliveredDate']) : null;

    // Improved debug log for deliveryDate and deliveredDate
    $debugLog = __DIR__ . '/deliveries_debug.log';
    $debugEntry = sprintf("[%s] supplyId=%s, deliveryDate=%s, deliveredDate=%s, raw_deliveryDate=%s\n", date('Y-m-d H:i:s'), $supplyId, var_export($deliveryDate, true), var_export($deliveredDate, true), var_export($data['deliveryDate'], true));
    @file_put_contents($debugLog, $debugEntry, FILE_APPEND);

    // Normalize status to DB enum values ('Pending','Received','Cancelled')
    $incomingStatus = isset($data['status']) ? sanitizeInput($data['status']) : null;
    if ($incomingStatus !== null) {
        $status = mapStatusToEnum($incomingStatus);
    } else {
        // If deliveredDate is provided but status not set, assume Received
        $status = $deliveredDate ? 'Received' : 'Pending';
    }

    $logEntry = sprintf("[%s] INSERTING: supplyId=%s, status=%s, deliveryDate=%s, deliveredDate=%s\n", date('Y-m-d H:i:s'), $supplyId, $status, var_export($deliveryDate, true), var_export($deliveredDate, true));
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    $sql = "INSERT INTO supply_deliveries (supplyId, supplierId, productId, batchNo, quantity, unitPrice, totalAmount, deliveryDate, deliveredDate, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $result = executeQuery($sql, [
        $supplyId,
        sanitizeInput($data['supplierId']),
        sanitizeInput($data['productId']),
        sanitizeInput($data['batchNo'] ?? ''),
        (int)$data['quantity'],
        (float)$data['unitPrice'],
        (float)$data['totalAmount'],
        $deliveryDate,
        $deliveredDate,
        $status,
        sanitizeInput($data['notes'] ?? '')
    ], 'ssssiddssss');

    if ($result !== false) {
        // If delivery is marked as received, update inventory immediately
        if ($status === 'Received') {
            $logEntry = sprintf("[%s] UPDATING INVENTORY: productId=%s, quantity=%d\n",
                date('Y-m-d H:i:s'), $data['productId'], (int)$data['quantity']);
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
            updateInventoryOnDelivery($data['productId'], (int)$data['quantity']);
        }

        $userId = $_SESSION['userId'] ?? 'system';
        $username = $_SESSION['username'] ?? 'system';
        logActivity($userId, $username, 'CREATE', 'SUPPLIERS', $supplyId, "Created delivery: {$data['productId']} from {$data['supplierId']}");

        sendJSON(true, ['id' => $supplyId], 'Delivery created successfully');
    } else {
        $logEntry = sprintf("[%s] INSERT FAILED: SQL execution returned false\n", date('Y-m-d H:i:s'));
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        sendJSON(false, null, 'Failed to create delivery');
    }
}

function handlePut($id, $data) {
    requireRole(['Super Admin', 'Admin']);
    requirePermission('Suppliers.update');
    if (!$id) {
        sendJSON(false, null, 'Delivery ID is required');
    }
    if (!is_array($data)) {
        http_response_code(400);
        sendJSON(false, null, 'Invalid request body');
    }

    // Accept both 'action' and 'status' for compatibility
    $action = $data['action'] ?? null; // 'delivered' or 'cancelled'
    $notes = $data['notes'] ?? null;
    // If 'action' is missing but 'status' is present, map status to action
    if (!$action && isset($data['status'])) {
        $status = strtolower($data['status']);
        if ($status === 'delivered' || $status === 'received') {
            $action = 'delivered';
        } elseif ($status === 'cancelled' || $status === 'canceled' || $status === 'cancel') {
            $action = 'cancelled';
        }
    }
    if (!$action) {
        sendJSON(false, null, 'Missing action');
    }

    $fields = [];
    $params = [];
    $types = '';

    // Fetch delivery to get productId and quantity
    $delivery = executeQuery("SELECT * FROM supply_deliveries WHERE supplyId = ?", [$id], 's');
    $productId = $delivery && isset($delivery[0]['productId']) ? $delivery[0]['productId'] : null;
    $quantity = $delivery && isset($delivery[0]['quantity']) ? (int)$delivery[0]['quantity'] : null;

    if ($action === 'delivered') {
        $deliveredDate = $data['deliveredDate'] ?? date('Y-m-d');
        $fields[] = 'status = ?';
        $params[] = 'Received';
        $types .= 's';
        $fields[] = 'deliveredDate = ?';
        $params[] = $deliveredDate;
        $types .= 's';
        $fields[] = 'isActive = ?';
        $params[] = 1;
        $types .= 'i';
    } elseif ($action === 'cancelled') {
        $fields[] = 'status = ?';
        $params[] = 'Cancelled';
        $types .= 's';
    } else {
        sendJSON(false, null, 'Invalid action');
    }

    if ($notes !== null) {
        $fields[] = 'notes = ?';
        $params[] = $notes;
        $types .= 's';
    }

    $params[] = $id;
    $types .= 's';

    $sql = "UPDATE supply_deliveries SET ".implode(", ", $fields).", updatedAt = CURRENT_TIMESTAMP WHERE supplyId = ?";
    $result = executeQuery($sql, $params, $types);
    if ($result === false) {
        sendJSON(false, null, 'Failed to update delivery');
    }

    // If delivered, set isActive=1 in inventory and update stock
    if ($action === 'delivered' && $productId && $quantity !== null) {
        // Set isActive=1 in inventory
        executeQuery("UPDATE inventory SET isActive = 1 WHERE productId = ?", [$productId], 's');
        // Call UpdateInventoryOnDelivery procedure
        $sqlProc = "CALL UpdateInventoryOnDelivery(?, ?, @success, @message)";
        executeQuery($sqlProc, [$productId, $quantity], 'si');
        $resultProc = executeQuery("SELECT @success as success, @message as message");
        if ($resultProc && !$resultProc[0]['success']) {
            error_log("Failed to update inventory for delivery: " . $resultProc[0]['message']);
        }
    }

    sendJSON(true, null, 'Supply delivery updated successfully');
}

function handleDelete($id) {
    requireRole(['Super Admin', 'Admin']);
        requirePermission('Suppliers.delete');
    if (!$id) {
        sendJSON(false, null, 'Delivery ID is required');
    }

    $userId = $_SESSION['userId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';

    // Archive supply delivery: set isArchive=1 and copy to archive table
    $sql = "UPDATE supply_deliveries SET isArchive = 1 WHERE supplyId = ?";
    executeQuery($sql, [$id], 's');
    $sql = "INSERT INTO archive_deliveries (
        supplyId, supplierId, productId, batchNo, quantity, unitPrice, totalAmount, deliveryDate, deliveredDate, status, notes, isActive, createdAt, updatedAt, isArchive
    ) SELECT 
        supplyId, supplierId, productId, batchNo, quantity, unitPrice, totalAmount, deliveryDate, deliveredDate, status, notes, isActive, createdAt, updatedAt, isArchive
    FROM supply_deliveries WHERE supplyId = ?";
    executeQuery($sql, [$id], 's');
    logActivity($userId, $username, 'ARCHIVE', 'SUPPLY_DELIVERIES', $id, "Archived delivery");
    sendJSON(true, null, 'Supply delivery archived successfully');
}

function getNextCounter($type) {
    $sql = "CALL GetNextCounter(?, @nextVal)";
    executeQuery($sql, [$type], 's');

    $result = executeQuery("SELECT @nextVal as counter");
    return $result ? $result[0]['counter'] : 1;
}

function generateSupplierId() {
    $counter = getNextCounter('Supplier');
    $date = date('YmdHi');
    $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    return "SUP{$date}{$random}";
}

function productExists($productId) {
    $sql = "SELECT productId FROM inventory WHERE productId = ?";
    $result = executeQuery($sql, [$productId], 's');
    return $result !== false && count($result) > 0;
}

function createNewInventoryItem($data) {
    // Generate Product ID
    $productId = generateProductId();

    // Generate Batch Number
    $batchNo = generateBatchNo($data['brand'], $data['genericName']);

    $sql = "INSERT INTO inventory (productId, brand, genericName, category, manufacturedDate,
            expirationDate, stock, batchNo, price, supplier, phoneNumber, requiresPrescription, isActive)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 0)";

    $result = executeQuery($sql, [
        $productId,
        sanitizeInput($data['brand']),
        sanitizeInput($data['genericName']),
        sanitizeInput($data['category']),
        sanitizeInput($data['manufacturedDate']),
        sanitizeInput($data['expirationDate']),
        $batchNo,
        (float)$data['unitPrice'],
        sanitizeInput($data['supplierId']),
        sanitizeInput($data['phoneNumber'] ?? ''),
        isset($data['requiresPrescription']) ? (int)$data['requiresPrescription'] : 0
    ], 'ssssssssdsi');

    return $result !== false ? $productId : false;
}

function generateProductId() {
    $date = date('Ymd');

    $conn = getDBConnection();
    $stmt = $conn->prepare("CALL GetNextCounter('Prod', @counter)");
    $stmt->execute();
    $stmt->close();

    $result = $conn->query("SELECT @counter AS counter");
    $row = $result->fetch_assoc();
    $counter = str_pad($row['counter'], 4, '0', STR_PAD_LEFT);

    return "Prod{$date}{$counter}";
}

function generateBatchNo($brand, $genericName) {
    $year = date('Y');
    $key = "batch_{$year}_" . preg_replace('/[^a-zA-Z0-9]/', '', $brand . $genericName);

    // Get or create counter
    $sql = "SELECT currentValue FROM counters WHERE counterType = ? AND counterDate = CURDATE()";
    $result = executeQuery($sql, [$key], 's');

    if (!empty($result)) {
        $counter = $result[0]['currentValue'] + 1;
        $sql = "UPDATE counters SET currentValue = ? WHERE counterType = ? AND counterDate = CURDATE()";
        executeQuery($sql, [$counter, $key], 'is');
    } else {
        $counter = 1;
        $sql = "INSERT INTO counters (counterType, counterDate, currentValue) VALUES (?, CURDATE(), ?)";
        executeQuery($sql, [$key, $counter], 'si');
    }

    return $year . str_pad($counter, 4, '0', STR_PAD_LEFT);
}

function updateInventoryOnDelivery($productId, $quantity, $isNewProduct = false) {
    if ($isNewProduct) {
        // For new products, set isActive=1 and add stock
        $sql = "UPDATE inventory SET stock = stock + ?, isActive = 1, updatedAt = NOW() WHERE productId = ?";
        executeQuery($sql, [$quantity, $productId], 'is');
    } else {
        // For existing products, use the stored procedure
        $sql = "CALL UpdateInventoryOnDelivery(?, ?, @success, @message)";
        executeQuery($sql, [$productId, $quantity], 'si');

        $result = executeQuery("SELECT @success as success, @message as message");
        if ($result && !$result[0]['success']) {
            error_log("Failed to update inventory for delivery: " . $result[0]['message']);
        }
    }
}
?>
