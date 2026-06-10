<?php
session_start();

// Include database configuration
define('DB_ACCESS', true);
require_once '../database/db_config.php';

header('Content-Type: application/json');

// Helper functions
function logRegisterError($message) {
    $logFile = __DIR__ . '/register_error.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time = date('Y-m-d H:i:s');
    $logMsg = "[$time][$ip] $message\n";
    file_put_contents($logFile, $logMsg, FILE_APPEND);
}

function sendError($message) {
    logRegisterError($message);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($message, $data = []) {
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method');
}


// Get and sanitize input
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? 'employee';

// Get database connection before any DB usage
$conn = getDBConnection();
if (!$conn) {
    sendError('Database connection failed. Please try again later.');
}

// Validation: Check empty fields
if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
    sendError('All fields are required');
}

// Validation: Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Invalid email address');
}
// Validation: Check if email already exists
$stmt = $conn->prepare("SELECT accountId FROM accounts WHERE email = ?");
if (!$stmt) {
    throw new Exception('Database prepare failed: ' . $conn->error);
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    sendError('Email already exists. Please use a different email.');
}
$stmt->close();

// Validation: Username format (alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    sendError('Username must be 3-20 characters (letters, numbers, underscore only)');
}

// Validation: Password length
if (strlen($password) < 6) {
    sendError('Password must be at least 6 characters long');
}

// Validation: Password confirmation
if ($password !== $confirmPassword) {
    sendError('Passwords do not match');
}

// Password strength validation
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $password)) {
    sendError('Password must contain at least one uppercase letter, one lowercase letter, and one number');
}



try {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT accountId FROM accounts WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        sendError('Username already exists. Please choose a different username.');
    }
    $stmt->close();
    
    // Hash password using bcrypt
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Generate account ID
    $accountId = generateAccountId();

    // Create full name
    $fullName = trim($firstName . ' ' . $lastName);
    
    // Insert new account (now includes email)
    $stmt = $conn->prepare("INSERT INTO accounts (accountId, firstName, lastName, fullName, email, username, password, phoneNumber, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $defaultPhoneNumber = 'N/A'; // Will be updated in profile later
    $stmt->bind_param("ssssssss", $accountId, $firstName, $lastName, $fullName, $email, $username, $hashedPassword, $defaultPhoneNumber);
    if ($stmt->execute()) {
        $stmt->close();
        // Assign default Staff role (roleId=4) in user_roles table (RBAC)
        $assignSql = $conn->prepare("INSERT INTO user_roles (accountId, roleId) VALUES (?, ?)");
        $defaultRoleId = 5; // Pending User
        $assignSql->bind_param("si", $accountId, $defaultRoleId);
        $assignSql->execute();
        $assignSql->close();
        // Log activity
        logActivity($accountId, $username, 'CREATE', 'ACCOUNTS', $accountId, "New account registered: $accountId ($username)");
        sendSuccess('Registration successful! Please login with your credentials.');
    } else {
        $stmt->close();
        throw new Exception('Registration failed: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    $errMsg = "Registration Error: " . $e->getMessage();
    logRegisterError($errMsg);
    error_log($errMsg);
    sendError('An error occurred during registration. Please try again.');
}

// Function to generate Account ID
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

