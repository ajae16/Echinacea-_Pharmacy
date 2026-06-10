<?php
/**
 * Inventory API Endpoint
 */

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
define('DB_ACCESS', true);
require_once '../database/db_config.php';

// Start session for user tracking
session_start();

// Disable error display for API responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = __DIR__ . '/inventory_error.log';
    $msg = date('Y-m-d H:i:s') . " ERROR [$errno] $errstr in $errfile on line $errline\n";
    file_put_contents($log, $msg, FILE_APPEND);
});
set_exception_handler(function($e) {
    $log = __DIR__ . '/inventory_error.log';
    $msg = date('Y-m-d H:i:s') . " EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    file_put_contents($log, $msg, FILE_APPEND);
});

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Route to appropriate function
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

/**
 * Handle GET requests - Read inventory
 */
function handleGet() {
        $view = $_GET['view'] ?? null;
        if ($view === 'low_stock') {
            $sql = "SELECT * FROM view_low_stock";
            $result = executeQuery($sql);
            sendJSON(true, $result ?: []);
            return;
        } else if ($view === 'expiring_soon') {
            $sql = "SELECT * FROM view_expiring_soon";
            $result = executeQuery($sql);
            sendJSON(true, $result ?: []);
            return;
        }
    $id = $_GET['id'] ?? null;
    $search = $_GET['search'] ?? null;
    $lowStock = $_GET['lowStock'] ?? null;
    $expiringSoon = $_GET['expiringSoon'] ?? null;
    $expired = $_GET['expired'] ?? null;
    $noStock = $_GET['noStock'] ?? null;
    $unique = $_GET['unique'] ?? null;
    $brand = $_GET['brand'] ?? null;
    $genericName = $_GET['genericName'] ?? null;
    $category = $_GET['category'] ?? null;
    $supplier = $_GET['supplier'] ?? null;
    $requiresPrescription = $_GET['requiresPrescription'] ?? null;
    $limit = $_GET['limit'] ?? null;
    $offset = $_GET['offset'] ?? null;

    if ($id) {
        // Get specific item (for delivery, allow isActive=0)
        $sql = "SELECT i.*, s.supplierId as supplierId, s.name as supplierName
            FROM inventory i
            LEFT JOIN suppliers s ON i.supplierId = s.supplierId
            WHERE i.productId = ? AND i.isArchive = 0";
        $result = executeQuery($sql, [$id], 's');

        if (!empty($result)) {
            sendJSON(true, $result[0]);
        } else {
            sendJSON(false, null, 'Item not found');
        }
    } elseif ($lowStock) {
        // Get low stock items
        $sql = "SELECT * FROM view_low_stock";
        $result = executeQuery($sql);
        sendJSON(true, $result);
    } elseif ($expiringSoon) {
        // Get expiring soon items
        $sql = "SELECT * FROM view_expiring_soon";
        $result = executeQuery($sql);
        sendJSON(true, $result);
    } elseif ($search) {
        // Search inventory (exclude inactive products for statistics)
        $searchTerm = "%$search%";
        $sql = "SELECT * FROM inventory
            WHERE isActive = TRUE AND isArchive = 0
            AND (brand LIKE ? OR genericName LIKE ? OR category LIKE ?)
            ORDER BY brand ASC";
        if ($limit) {
            $sql .= " LIMIT ?";
            if ($offset) {
                $sql .= " OFFSET ?";
                $result = executeQuery($sql, [$searchTerm, $searchTerm, $searchTerm, (int)$limit, (int)$offset], 'sssii');
            } else {
                $result = executeQuery($sql, [$searchTerm, $searchTerm, $searchTerm, (int)$limit], 'sssi');
            }
        } else {
            $result = executeQuery($sql, [$searchTerm, $searchTerm, $searchTerm], 'sss');
        }
        sendJSON(true, $result);
    } elseif ($unique) {
        // Get unique values for filter categories
        $fieldMap = [
            'brands' => 'brand',
            'genericNames' => 'genericName',
            'categories' => 'category',
            'suppliers' => 'supplier',
            'prescriptionRequirements' => 'requiresPrescription'
        ];

        if (isset($fieldMap[$unique])) {
            $field = $fieldMap[$unique];
            if ($field === 'requiresPrescription') {
                $sql = "SELECT DISTINCT $field, CASE WHEN $field = 1 THEN 'Yes' ELSE 'No' END as displayValue FROM inventory WHERE isArchive = 0 ORDER BY $field";
                $result = executeQuery($sql);
                sendJSON(true, $result);
            } else if ($field === 'supplier') {
                // Join inventory and suppliers to get unique supplierId and supplierName
                $sql = "SELECT DISTINCT s.supplierId as value, s.name as displayValue FROM inventory i LEFT JOIN suppliers s ON i.supplierId = s.supplierId WHERE i.isArchive = 0 AND i.supplierId IS NOT NULL AND i.supplierId != '' AND s.supplierId IS NOT NULL ORDER BY s.name";
                $result = executeQuery($sql);
                sendJSON(true, $result);
            } else {
                $sql = "SELECT DISTINCT LOWER($field) as value, $field as displayValue FROM inventory WHERE isArchive = 0 ORDER BY $field";
                $result = executeQuery($sql);
                sendJSON(true, $result);
            }
        } else {
            sendJSON(false, null, 'Invalid unique parameter');
        }
    } elseif ($brand || $genericName || $category || $supplier || $requiresPrescription !== null) {
        // Filter inventory based on provided parameters
        $whereConditions = ["isActive = TRUE"];
        $params = [];
        $types = '';

        if ($brand) {
            $whereConditions[] = "brand = ?";
            $params[] = $brand;
            $types .= 's';
        }
        if ($genericName) {
            $whereConditions[] = "genericName = ?";
            $params[] = $genericName;
            $types .= 's';
        }
        if ($category) {
            $whereConditions[] = "category = ?";
            $params[] = $category;
            $types .= 's';
        }
        if ($supplier) {
            $whereConditions[] = "supplier = ?";
            $params[] = $supplier;
            $types .= 's';
        }
        if ($requiresPrescription !== null) {
            $whereConditions[] = "requiresPrescription = ?";
            $params[] = (int)$requiresPrescription;
            $types .= 'i';
        }

        $sql = "SELECT i.*, s.supplierId as supplierId, s.name as supplierName, 
              CASE
                 WHEN EXISTS (SELECT 1 FROM view_no_stock vns WHERE vns.id = i.productId)
                     AND EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'No Stock & Expired'
                 WHEN EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Low Stock & Expiring Soon'
                 WHEN EXISTS (SELECT 1 FROM view_no_stock vns WHERE vns.id = i.productId) THEN 'No Stock'
                 WHEN EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Expired'
                 WHEN EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Low Stock'
                 WHEN EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Expiring Soon'
                 ELSE 'Good'
              END as status
            FROM inventory i
            LEFT JOIN suppliers s ON i.supplierId = s.supplierId
            WHERE ";
        if (!empty($whereConditions)) {
            $sql .= implode(' AND ', $whereConditions) . ' AND ';
        }
        $sql .= 'i.isArchive = 0 ORDER BY i.createdAt DESC';

        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = (int)$limit;
            $types .= 'i';
            if ($offset) {
                $sql .= " OFFSET ?";
                $params[] = (int)$offset;
                $types .= 'i';
            }
        }

        $result = executeQuery($sql, $params, $types);
        sendJSON(true, $result ?: []);
    } else {
        // Get all inventory (frontend will filter isActive)
           $sql = "SELECT i.*, s.supplierId as supplierId, s.name as supplierName, 
              CASE 
                 WHEN EXISTS (SELECT 1 FROM view_no_stock vns WHERE vns.id = i.productId)
                     AND EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'No Stock & Expired'
                 WHEN EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Low Stock & Expiring Soon'
                 WHEN EXISTS (SELECT 1 FROM view_no_stock vns WHERE vns.id = i.productId) THEN 'No Stock'
                 WHEN EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Expired'
                 WHEN EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Low Stock'
                 WHEN EXISTS (SELECT 1 FROM view_expiring_soon ves WHERE ves.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_low_stock vls WHERE vls.id = i.productId)
                     AND NOT EXISTS (SELECT 1 FROM view_expired vex WHERE vex.id = i.productId) THEN 'Expiring Soon'
                 ELSE 'Good'
              END as status
              FROM inventory i
              LEFT JOIN suppliers s ON i.supplierId = s.supplierId
              WHERE i.isArchive = 0 ORDER BY i.createdAt DESC";
        if ($limit) {
            $sql .= " LIMIT ?";
            if ($offset) {
                $sql .= " OFFSET ?";
                $result = executeQuery($sql, [(int)$limit, (int)$offset], 'ii');
            } else {
                $result = executeQuery($sql, [(int)$limit], 'i');
            }
        } else {
            $result = executeQuery($sql);
        }
        // If paginated, return data and total, else just data array for compatibility
        if ($limit) {
            $countSql = "SELECT COUNT(*) as total FROM inventory WHERE isArchive = 0";
            $totalResult = executeQuery($countSql);
            $total = $totalResult && isset($totalResult[0]['total']) ? (int)$totalResult[0]['total'] : 0;
            sendJSON(true, ['data' => $result ?: [], 'total' => $total]);
        } else {
            sendJSON(true, $result ?: []);
        }
    }
}

/**
 * Handle POST requests - Create new item
 */
function handlePost($data) {
    // Log all POST data for debugging supply new product issues
    $logFile = __DIR__ . '/inventory_post_debug.log';
    $logEntry = sprintf("[%s] INVENTORY POST: %s\n", date('Y-m-d H:i:s'), json_encode($data));
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    // Granular permission: require Inventory permission
    requirePermission('Inventory');
    // Validate required fields
    $required = ['brand', 'genericName', 'category', 'manufacturedDate',
                 'expirationDate', 'stock', 'lowStockThreshold', 'price', 'phoneNumber', 'supplierId'];

    $missing = validateRequired($data, $required);

    if (!empty($missing)) {
        sendJSON(false, null, 'Missing required fields: ' . implode(', ', $missing));
    }

    // Sanitize input
    $brand = sanitizeInput($data['brand']);
    $genericName = sanitizeInput($data['genericName']);
    $category = sanitizeInput($data['category']);
    $manufacturedDate = sanitizeInput($data['manufacturedDate']);
    $expirationDate = sanitizeInput($data['expirationDate']);
    $stock = (int)$data['stock'];
    $lowStockThreshold = (int)$data['lowStockThreshold'];
    $price = (float)$data['price'];
    $supplierId = sanitizeInput($data['supplierId']);
    $phoneNumber = sanitizeInput($data['phoneNumber']);
    $requiresPrescription = isset($data['requiresPrescription']) ? (int)$data['requiresPrescription'] : 0;
    // Accept isActive from payload, default to 1 if not set
    $isActive = isset($data['isActive']) ? (int)$data['isActive'] : 1;

    // Validate dates
    if (strtotime($expirationDate) <= strtotime($manufacturedDate)) {
        sendJSON(false, null, 'Expiration date must be after manufactured date');
    }

    // Generate Product ID
    $productId = generateProductId();

    // Generate Batch Number
    $batchNo = generateBatchNo();


    // Insert into database (now includes lowStockThreshold)
        $sql = "INSERT INTO inventory (productId, brand, genericName, category, manufacturedDate,
            expirationDate, stock, lowStockThreshold, batchNo, price, supplierId, phoneNumber, requiresPrescription, isActive)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Detailed SQL insert logging for debugging supplierId issues
    $sqlLogFile = __DIR__ . '/inventory_sql_debug.log';
    $sqlLogEntry = sprintf("[%s] SQL INSERT: %s\nPARAMS: %s\nTYPES: %s\n", date('Y-m-d H:i:s'),
        "INSERT INTO inventory (productId, brand, genericName, category, manufacturedDate, expirationDate, stock, lowStockThreshold, batchNo, price, supplierId, phoneNumber, requiresPrescription) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        json_encode([
            $productId, $brand, $genericName, $category, $manufacturedDate,
            $expirationDate, $stock, $lowStockThreshold, $batchNo, $price, $supplierId, $phoneNumber, $requiresPrescription
        ]),
        'ssssssiisdssi'
    );
    @file_put_contents($sqlLogFile, $sqlLogEntry, FILE_APPEND);

    // Extra debug: log supplierId and all params before insert
    $debugLogFile = __DIR__ . '/inventory_post_debug.log';
    $debugLogEntry = sprintf("[%s] DEBUG: About to insert with supplierId=%s, params=%s\n", date('Y-m-d H:i:s'), $supplierId, json_encode([
        $productId, $brand, $genericName, $category, $manufacturedDate,
        $expirationDate, $stock, $lowStockThreshold, $batchNo, $price, $supplierId, $phoneNumber, $requiresPrescription
    ]));
    @file_put_contents($debugLogFile, $debugLogEntry, FILE_APPEND);

    $result = executeQuery($sql, [
        $productId, $brand, $genericName, $category, $manufacturedDate,
        $expirationDate, $stock, $lowStockThreshold, $batchNo, $price, $supplierId, $phoneNumber, $requiresPrescription, $isActive
    ], 'ssssssiisdssii');

    $sqlResultLog = sprintf("[%s] SQL RESULT: %s\n", date('Y-m-d H:i:s'), var_export($result, true));
    @file_put_contents($sqlLogFile, $sqlResultLog, FILE_APPEND);

    // Extra debug: fetch and log the just-inserted row
    $fetchDebug = executeQuery("SELECT * FROM inventory WHERE productId = ?", [$productId], 's');
    $fetchDebugLog = sprintf("[%s] POST-INSERT ROW: %s\n", date('Y-m-d H:i:s'), json_encode($fetchDebug));
    @file_put_contents($debugLogFile, $fetchDebugLog, FILE_APPEND);
    
    if ($result !== false) {
        // Log activity
        $accountId = $_SESSION['accountId'] ?? 'system';
        $username = $_SESSION['username'] ?? 'system';
        logActivity($accountId, $username, 'CREATE', 'INVENTORY', $productId, 
               "Created product: $brand - $genericName");
        
        // Return `productId` key to match frontend expectations
        sendJSON(true, ['productId' => $productId], 'Product created successfully');
    } else {
        sendJSON(false, null, 'Failed to create product');
    }
}

/**
 * Handle PUT requests - Update existing item
 */
function handlePut($data) {
    requirePermission('Inventory.update');
    if (empty($data['id'])) {
        sendJSON(false, null, 'Product ID is required');
    }

    $id = sanitizeInput($data['id']);
    
    // Build update query dynamically based on provided fields
    $updateFields = [];
    $params = [];
    $types = '';
    
    // Only allow updating supplierId, not legacy 'supplier'
    $allowedFields = ['brand', 'genericName', 'category', 'manufacturedDate',
                      'expirationDate', 'stock', 'lowStockThreshold', 'price', 'supplierId', 'phoneNumber', 'requiresPrescription'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Map supplierId to correct column
            $col = ($field === 'supplierId') ? 'supplierId' : $field;
            if ($field === 'requiresPrescription') {
                $updateFields[] = "$col = ?";
                $params[] = (int)$data[$field];
                $types .= 'i';
            } else if ($field === 'stock') {
                $updateFields[] = "$col = ?";
                $params[] = (int)$data[$field];
                $types .= 'i';
            } else if ($field === 'price') {
                $updateFields[] = "$col = ?";
                $params[] = (float)$data[$field];
                $types .= 'd';
            } else {
                $updateFields[] = "$col = ?";
                $params[] = sanitizeInput($data[$field]);
                $types .= 's';
            }
        }
    }
    
    if (empty($updateFields)) {
        sendJSON(false, null, 'No fields to update');
    }
    
    // Add ID to params
    $params[] = $id;
    $types .= 's';
    
    $sql = "UPDATE inventory SET " . implode(', ', $updateFields) . " WHERE productId = ?";
    $result = executeQuery($sql, $params, $types);
    
    if ($result !== false) {
        // Log activity
        $accountId = $_SESSION['accountId'] ?? 'system';
        $username = $_SESSION['username'] ?? 'system';
        logActivity($accountId, $username, 'UPDATE', 'INVENTORY', $id, 
               "Updated product: " . ($data['brand'] ?? 'N/A'));
        
        sendJSON(true, null, 'Product updated successfully');
    } else {
        sendJSON(false, null, 'Failed to update product');
    }
}

/**
 * Handle DELETE requests - Archive item
 */
function handleDelete($data) {
    requirePermission('Inventory.delete');
    if (empty($data['productId'])) {
        // Try to get from query string
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendJSON(false, null, 'Product ID is required');
        }
    } else {
        $id = $data['productId'];
    }
    
    $id = sanitizeInput($id);
    $accountId = $_SESSION['accountId'] ?? 'system';
    $username = $_SESSION['username'] ?? 'system';
    
    // Archive inventory: set isArchive=1 and copy to archive table
    $sql = "UPDATE inventory SET isArchive = 1 WHERE productId = ?";
    executeQuery($sql, [$id], 's');
    $sql = "INSERT INTO archive_inventory (
        productId, brand, genericName, category, manufacturedDate, expirationDate, stock, batchNo, price, supplierId, phoneNumber,
        requiresPrescription, lowStockThreshold, isActive, createdAt, updatedAt, isArchive
    ) SELECT 
        productId, brand, genericName, category, manufacturedDate, expirationDate, stock, batchNo, price, supplierId, phoneNumber,
        requiresPrescription, lowStockThreshold, isActive, createdAt, updatedAt, isArchive
    FROM inventory WHERE productId = ?";
    executeQuery($sql, [$id], 's');
    logActivity($accountId, $username, 'ARCHIVE', 'INVENTORY', $id, "Archived product: $id");
    sendJSON(true, null, 'Inventory item archived successfully');
    }

/**
 * Generate Product ID
 * Format: Prod{YYYYMMDD}{counter}
 */
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

/**
 * Generate Batch Number
 * Format: {YYYY}{counter}
 */
function generateBatchNo() {
    $date = date('Ymd');
    $conn = getDBConnection();
    $stmt = $conn->prepare("CALL GetNextCounter('Batch', @counter)");
    $stmt->execute();
    $stmt->close();
    $result = $conn->query("SELECT @counter AS counter");
    $row = $result->fetch_assoc();
    $counter = str_pad($row['counter'], 4, '0', STR_PAD_LEFT);
    return "Batch{$date}{$counter}";
}
?>

