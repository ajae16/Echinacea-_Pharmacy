<?php
session_start();
define('DB_ACCESS', true);
require_once 'database/db_config.php';
requirePermission('Logs.view');

// Get user information
$userName = $_SESSION['fullName'] ?? $_SESSION['username'];
$userRoles = $_SESSION['roles'] ?? [];

// Function to get logs by category
function getLogsByCategory($categoryModules, $limit = 10) {
    $conn = getDBConnection();
    if (!$conn) {
        return [];
    }

    $placeholders = str_repeat('?,', count($categoryModules) - 1) . '?';
    $sql = "SELECT timestamp, username, action, module, details, ipAddress
            FROM activity_logs
            WHERE module IN ($placeholders)
            ORDER BY timestamp DESC
            LIMIT ?";

    $params = array_merge($categoryModules, [$limit]);
    $types = str_repeat('s', count($categoryModules)) . 'i';

    return executeQuery($sql, $params, $types);
}

// Get logs for each module
$inventoryLogs = getLogsByCategory(['INVENTORY']);
$accountsLogs = getLogsByCategory(['ACCOUNTS']);
$authLogs = getLogsByCategory(['AUTH']);
$systemLogs = getLogsByCategory(['SYSTEM']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Echinacea Pharmacy MIS</title>
    <link rel="stylesheet" href="css/Dashboard.css">
    <link rel="stylesheet" href="css/Logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
    <main class="page-outer-box">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-title-section">
                    <h1><i class="fas fa-history"></i> Activity Logs</h1>
                    <p>Monitor system activities and user interactions</p>
                </div>
            </div>
        </div>

        <!-- Granular Activity Categories -->
        <div class="logs-container">
            <div class="logs-grid">
                <!-- Inventory Logs -->
                <div class="logs-column">
                    <div class="logs-card">
                        <div class="logs-header">
                            <h3><i class="fas fa-boxes"></i> Inventory Logs</h3>
                        </div>
                        <div class="logs-content">
                            <?php if (!empty($inventoryLogs)): ?>
                                <?php foreach ($inventoryLogs as $log): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon inventory">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($log['username']); ?> - <?php echo htmlspecialchars($log['action']); ?></div>
                                            <div class="activity-description"><?php echo htmlspecialchars($log['details']); ?> <small>(<?php echo date('M d, H:i', strtotime($log['timestamp'])); ?>)</small></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">No Inventory Activities</div>
                                        <div class="activity-description">No inventory activities logged yet.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Accounts Logs -->
                <div class="logs-column">
                    <div class="logs-card">
                        <div class="logs-header">
                            <h3><i class="fas fa-user"></i> Accounts Logs</h3>
                        </div>
                        <div class="logs-content">
                            <?php if (!empty($accountsLogs)): ?>
                                <?php foreach ($accountsLogs as $log): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon accounts">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($log['username']); ?> - <?php echo htmlspecialchars($log['action']); ?></div>
                                            <div class="activity-description"><?php echo htmlspecialchars($log['details']); ?> <small>(<?php echo date('M d, H:i', strtotime($log['timestamp'])); ?>)</small></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">No Account Activities</div>
                                        <div class="activity-description">No account activities logged yet.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Auth Logs -->
                <div class="logs-column">
                    <div class="logs-card">
                        <div class="logs-header">
                            <h3><i class="fas fa-key"></i> Auth Logs</h3>
                        </div>
                        <div class="logs-content">
                            <?php if (!empty($authLogs)): ?>
                                <?php foreach ($authLogs as $log): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon auth">
                                            <i class="fas fa-key"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($log['username']); ?> - <?php echo htmlspecialchars($log['action']); ?></div>
                                            <div class="activity-description"><?php echo htmlspecialchars($log['details']); ?> <small>(<?php echo date('M d, H:i', strtotime($log['timestamp'])); ?>)</small></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">No Auth Activities</div>
                                        <div class="activity-description">No auth activities logged yet.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- System Logs -->
                <div class="logs-column">
                    <div class="logs-card">
                        <div class="logs-header">
                            <h3><i class="fas fa-server"></i> System Logs</h3>
                        </div>
                        <div class="logs-content">
                            <?php if (!empty($systemLogs)): ?>
                                <?php foreach ($systemLogs as $log): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon system">
                                            <i class="fas fa-server"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($log['username']); ?> - <?php echo htmlspecialchars($log['action']); ?></div>
                                            <div class="activity-description"><?php echo htmlspecialchars($log['details']); ?> <small>(<?php echo date('M d, H:i', strtotime($log['timestamp'])); ?>)</small></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">No System Activities</div>
                                        <div class="activity-description">No system activities logged yet.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<style>
    .user-role-badge .role-badge {
        display: inline-block;
        background: #64748b;
        color: #fff;
        border-radius: 8px;
        padding: 2px 8px;
        font-size: 11px;
        margin-right: 4px;
        margin-bottom: 2px;
    }
</style>
