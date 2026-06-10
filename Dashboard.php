<?php
$currentDate = date('l, F j, Y');
$currentTime = date('g:i A');
session_start();
$userRoles = $_SESSION['roles'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Echinacea Pharmacy MIS</title>
    <link rel="stylesheet" href="css/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/api-adapter.js"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="page-outer-box">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-banner-content">
                <div class="welcome-text">
                    <h1><i class="fas fa-hand-wave" style="color: #fbbf24;"></i> Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p>Here's what's happening in your pharmacy today</p>
                </div>
                <div class="welcome-meta">
                    <div class="user-role-badge">
                        <i class="fas fa-user-shield"></i>
                        <?php if (!empty($userRoles)): ?>
                            <?php foreach ($userRoles as $role): ?>
                                <span class="role-badge"><?php echo htmlspecialchars(ucfirst($role)); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="role-badge">Employee</span>
                        <?php endif; ?>
                    </div>
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
                    <div class="date-time">
                        <i class="fas fa-calendar"></i> <?php echo $currentDate; ?><br>
                        <i class="fas fa-clock"></i> <span id="currentTime"><?php echo $currentTime; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card blue">
                <div class="stat-header">
                    <div class="stat-title">Pending User</div>
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                </div>
                <div class="stat-value" id="pendingUser">0</div>
                <div class="stat-footer">
                    Awaiting approval
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div class="stat-title">Deliveries Today</div>
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                </div>
                <div class="stat-value" id="deliveriesToday">0</div>
                <div class="stat-footer">
                    Pending deliveries
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div class="stat-title">Low Stock Alert</div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-value" id="lowStock">0</div>
                <div class="stat-footer">
                    Items need restocking
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-header">
                    <div class="stat-title">Expiring Soon</div>
                    <div class="stat-icon"><i class="fas fa-calendar-times"></i></div>
                </div>
                <div class="stat-value" id="expiringSoon">0</div>
                <div class="stat-footer">
                    Within 100 days
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Online Users -->
            <div class="section-card section-card-large" style="max-height: 330px; overflow-y: auto;">
                <div class="section-header">
                    <h2><i class="fas fa-user-friends"></i> Online Users</h2>
                </div>
                <div id="onlineUsersGrid" class="online-users-grid">
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <p>No users online</p>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <div class="section-card" style="max-height: 330px; overflow-y: auto;">
                <div class="section-header">
                    <h2><i class="fas fa-bell"></i> Alerts</h2> 
                </div>
                <div id="alertsList">
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No alerts</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Inventory -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-box-open"></i> Recent Inventory</h2>
                <a href="Inventory.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div id="inventoryGrid" class="inventory-grid">
                <!-- Inventory cards will be rendered here -->
            </div>
            <div class="pagination" id="pagination">
                <!-- Pagination will be rendered here -->
            </div>
        </div>
    </main>
    </div>

    <script>
        // Dashboard JavaScript functionality

        // Global variables for pagination
        let currentPagination = 1;
        const itemsPerPage = 10;
        let totalItems = 0;

// Update the current time display every second
setInterval(() => {
    const now = new Date();
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}, 1000);

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', async function() {
    await loadDashboardStatistics();
    await renderDashboardInventory();
    await loadOnlineUsers();
    await loadAlerts();

    // Refresh statistics and online users every 30 seconds
    setInterval(async () => {
        await loadDashboardStatistics();
        await loadOnlineUsers();
    }, 30000);
});

// Load dashboard statistics (total products, sales today, low stock, expiring soon)
async function loadDashboardStatistics() {
    try {
        // Fetch Pending User count from view
        const pendingUserResponse = await fetch('api/accounts.php?view=pending_users');
        const pendingUserResult = await pendingUserResponse.json();
        if (pendingUserResult.success) {
            document.getElementById('pendingUser').textContent = pendingUserResult.data.length;
        }

        // Fetch Deliveries Today count from view
        const deliveriesResponse = await fetch('api/deliveries.php?view=deliveries_today');
        const deliveriesResult = await deliveriesResponse.json();
        if (deliveriesResult.success) {
            document.getElementById('deliveriesToday').textContent = deliveriesResult.data.length;
        }

        // Fetch Low Stock count from view
        const lowStockResponse = await fetch('api/inventory.php?view=low_stock');
        const lowStockResult = await lowStockResponse.json();
        if (lowStockResult.success) {
            document.getElementById('lowStock').textContent = lowStockResult.data.length;
        }

        // Fetch Expiring Soon count from view
        const expSoonResponse = await fetch('api/inventory.php?view=expiring_soon');
        const expSoonResult = await expSoonResponse.json();
        if (expSoonResult.success) {
            document.getElementById('expiringSoon').textContent = expSoonResult.data.length;
        }
    } catch (error) {
        console.error('Error loading dashboard statistics:', error);
    }
}

        // Render inventory cards for the dashboard with pagination
        async function renderDashboardInventory(page = 1) {
            try {
                currentPagination = page;
                const offset = (page - 1) * itemsPerPage;
                // Fetch all inventory with status (uses improved backend logic)
                const response = await fetch(`api/inventory.php?limit=${itemsPerPage}&offset=${offset}`);
                const result = await response.json();
                console.log('Dashboard inventory API response:', result);
                // Handle nested data.data structure if present
                let items = [];
                let total = 0;
                if (result.success && result.data) {
                    if (Array.isArray(result.data)) {
                        items = result.data;
                        total = result.total || result.data.length;
                    } else if (result.data.data && Array.isArray(result.data.data)) {
                        items = result.data.data;
                        total = result.data.total || result.data.data.length;
                    }
                }
                // Filter out inactive products (isActive == 0)
                const activeItems = items.filter(item => item.isActive === undefined || item.isActive === null || item.isActive === 1 || item.isActive === true);
                totalItems = activeItems.length;
                if (activeItems.length > 0) {
                    const grid = document.getElementById('inventoryGrid');
                    grid.innerHTML = '';
                    activeItems.forEach(item => {
                        let cardClass = 'inventory-card';
                        let badgeHTML = '';
                        if (item.status) {
                            const status = item.status.toLowerCase();
                            if (status.includes('no stock & expired')) {
                                cardClass += ' no-stock expired';
                                badgeHTML = '<span class="inventory-badge nostock-expired">NO STOCK & EXPIRED</span>';
                            } else if (status.includes('low stock & expiring soon')) {
                                cardClass += ' low-stock expiring';
                                badgeHTML = '<span class="inventory-badge low-expiring">LOW STOCK & EXPIRING SOON</span>';
                            } else if (status.includes('low stock')) {
                                cardClass += ' low-stock';
                                badgeHTML = '<span class="inventory-badge low">LOW STOCK</span>';
                            } else if (status.includes('expiring soon')) {
                                cardClass += ' expiring';
                                badgeHTML = '<span class="inventory-badge expiring">EXPIRING SOON</span>';
                            } else if (status.includes('expired')) {
                                cardClass += ' expired';
                                badgeHTML = '<span class="inventory-badge expired">EXPIRED</span>';
                            } else if (status.includes('no stock')) {
                                cardClass += ' no-stock';
                                badgeHTML = '<span class="inventory-badge nostock">NO STOCK</span>';
                            } else {
                                cardClass += ' good';
                                badgeHTML = '<span class="inventory-badge good">GOOD</span>';
                            }
                        }
                        const card = document.createElement('div');
                        card.className = cardClass;
                        const supplierDisplay = (item.supplier && item.supplier !== '0' && item.supplier.trim() !== '')
                            ? item.supplier
                            : 'Not specified';
                        card.innerHTML = `
                            <div class="inventory-header">
                                <div>
                                    <div class="inventory-title">${item.brand}</div>
                                    <div class="inventory-generic">${item.genericName}</div>
                                </div>
                                ${badgeHTML}
                            </div>
                            <div class="inventory-details">
                                <div class="inventory-detail">
                                    <div class="inventory-detail-label">Stock</div>
                                    <div class="inventory-detail-value">${item.stock} units</div>
                                </div>
                                <div class="inventory-detail">
                                    <div class="inventory-detail-label">Price</div>
                                    <div class="inventory-detail-value">₱${parseFloat(item.price).toFixed(2)}</div>
                                </div>
                                <div class="inventory-detail">
                                    <div class="inventory-detail-label">Expires</div>
                                    <div class="inventory-detail-value">${new Date(item.expirationDate).toLocaleDateString()}</div>
                                </div>
                                <div class="inventory-detail">
                                    <div class="inventory-detail-label">Supplier</div>
                                    <div class="inventory-detail-value">${supplierDisplay}</div>
                                </div>
                            </div>
                        `;
                        grid.appendChild(card);
                    });
                    generatePagination();
                } else {
                    document.getElementById('inventoryGrid').innerHTML = `
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fas fa-box-open"></i>
                            <p>No inventory items yet</p>
                        </div>
                    `;
                    document.getElementById('pagination').innerHTML = '';
                }
            } catch (error) {
                console.error('Error loading inventory:', error);
            }
        }

        // Generate pagination controls
        function generatePagination() {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) return;

            // Previous button
            const prevBtn = document.createElement('a');
            prevBtn.href = '#';
            prevBtn.textContent = '← Prev';
            prevBtn.className = currentPagination === 1 ? 'disabled' : '';
            prevBtn.onclick = (e) => {
                e.preventDefault();
                if (currentPagination > 1) renderDashboardInventory(currentPagination - 1);
            };
            pagination.appendChild(prevBtn);

            // Page numbers (show max 5 pages around current)
            const startPage = Math.max(1, currentPagination - 2);
            const endPage = Math.min(totalPages, currentPagination + 2);

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('a');
                pageBtn.href = '#';
                pageBtn.textContent = i;
                pageBtn.className = i === currentPagination ? 'active' : '';
                pageBtn.onclick = (e) => {
                    e.preventDefault();
                    renderDashboardInventory(i);
                };
                pagination.appendChild(pageBtn);
            }

            // Next button
            const nextBtn = document.createElement('a');
            nextBtn.href = '#';
            nextBtn.textContent = 'Next →';
            nextBtn.className = currentPagination === totalPages ? 'disabled' : '';
            nextBtn.onclick = (e) => {
                e.preventDefault();
                if (currentPagination < totalPages) renderDashboardInventory(currentPagination + 1);
            };
            pagination.appendChild(nextBtn);
        }


// Load online users and display in a 2-column grid
async function loadOnlineUsers() {
    try {
        const response = await fetch('api/accounts.php?view=online_users');
        const result = await response.json();
        const grid = document.getElementById('onlineUsersGrid');
        grid.innerHTML = '';
        if (result.success && Array.isArray(result.data) && result.data.length > 0) {
            result.data.forEach(user => {
                const userCard = document.createElement('div');
                userCard.className = 'online-user-card';
                userCard.innerHTML = `
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info">
                        <div class="user-fullname">${user.fullName}</div>
                        <div class="user-role">${user.roles ? user.roles.split(',').map(role => `<span class='role-badge'>${role.trim()}</span>`).join(' ') : ''}</div>
                        <div class="user-last-activity">Last active: ${user.lastActivity ? new Date(user.lastActivity).toLocaleString() : 'N/A'}</div>
                    </div>
                `;
                grid.appendChild(userCard);
            });
        } else {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No users online</p>
                </div>
            `;
        }
    } catch (error) {
        const grid = document.getElementById('onlineUsersGrid');
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <p>Error loading online users</p>
            </div>
        `;
        console.error('Error loading online users:', error);
    }
}

// Load alerts (low stock and expiring items)
async function loadAlerts() {
    try {
        const [lowStockRes, expSoonRes] = await Promise.all([
            fetch('api/inventory.php?view=low_stock'),
            fetch('api/inventory.php?view=expiring_soon')
        ]);
        const [lowStockResult, expSoonResult] = await Promise.all([
            lowStockRes.json(),
            expSoonRes.json()
        ]);
        const alertsList = document.getElementById('alertsList');
        alertsList.innerHTML = '';
        let alertCount = 0;
        // Low Stock Alerts
        if (lowStockResult.success && Array.isArray(lowStockResult.data)) {
            lowStockResult.data.slice(0, 5).forEach(item => {
                const div = document.createElement('div');
                div.className = 'alert-item critical';
                div.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="alert-content">
                        <div class="alert-title">Low Stock: ${item.brand}</div>
                        <div class="alert-description">Only ${item.stock} units remaining</div>
                    </div>
                `;
                alertsList.appendChild(div);
                alertCount++;
            });
        }
        // Expiring Soon Alerts
        if (expSoonResult.success && Array.isArray(expSoonResult.data)) {
            expSoonResult.data.slice(0, 5 - alertCount).forEach(item => {
                const expDate = new Date(item.expirationDate);
                const today = new Date();
                const daysToExpiration = Math.round((expDate - today) / (1000 * 60 * 60 * 24));
                const div = document.createElement('div');
                div.className = 'alert-item';
                div.innerHTML = `
                    <i class="fas fa-clock"></i>
                    <div class="alert-content">
                        <div class="alert-title">Expiring Soon: ${item.brand}</div>
                        <div class="alert-description">Expires in ${daysToExpiration} days</div>
                    </div>
                `;
                alertsList.appendChild(div);
                alertCount++;
            });
        }
        if (alertCount === 0) {
            alertsList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No alerts</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading alerts:', error);
    }
}

// Auto-logout functionality disabled
// No automatic session timeout or browser close logout

    </script>
    <style>
    .inventory-badge.good {
        background: #22c55e;
        color: #fff;
        font-weight: bold;
        border-radius: 8px;
        padding: 2px 10px;
        font-size: 12px;
        margin-left: 8px;
        box-shadow: 0 1px 4px rgba(34,197,94,0.10);
        letter-spacing: 0.5px;
    }
    </style>
</body>
</html>