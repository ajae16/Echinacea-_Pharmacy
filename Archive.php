<?php
session_start();
define('DB_ACCESS', true);
require_once 'database/db_config.php';
requirePermission('Archive.view');

// Get user information
$userName = $_SESSION['fullName'] ?? $_SESSION['username'];
$userRole = ucfirst($_SESSION['userRole'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - Echinacea Pharmacy MIS</title>
    <link rel="stylesheet" href="css/Archive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="page-outer-box">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-title-section">
                    <h1><i class="fas fa-archive"></i> Archive</h1>
                    <p>View and restore archived inventory, suppliers, and employees</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card blue">
                <div class="stat-header">
                    <div class="stat-title">Archived Products</div>
                    <div class="stat-icon"><i class="fas fa-box-open"></i></div>
                </div>
                <div class="stat-value" id="archivedInventoryCount">0</div>
                <div class="stat-footer">Inventory items</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div class="stat-title">Archived Suppliers</div>
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                </div>
                <div class="stat-value" id="archivedSuppliersCount">0</div>
                <div class="stat-footer">Supplier records</div>
            </div>

            <div class="stat-card purple">
                <div class="stat-header">
                    <div class="stat-title">Archived Deliveries</div>
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                </div>
                <div class="stat-value" id="archivedDeliveriesCount">0</div>
                <div class="stat-footer">Supply deliveries</div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div class="stat-title">Archived Accounts</div>
                    <div class="stat-icon"><i class="fas fa-user-circle"></i></div>
                </div>
                <div class="stat-value" id="archivedAccountsCount">0</div>
                <div class="stat-footer">Account records</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('inventory')">
                <i class="fas fa-boxes"></i> Inventory Archive
            </button>
            <button class="tab-btn" onclick="switchTab('suppliers')">
                <i class="fas fa-truck-loading"></i> Suppliers Archive
            </button>
            <button class="tab-btn" onclick="switchTab('deliveries')">
                <i class="fas fa-truck"></i> Supply Deliveries Archive
            </button>
            <button class="tab-btn" onclick="switchTab('accounts')">
                <i class="fas fa-user-circle"></i> Accounts Archive
            </button>
        </div>

        <!-- Inventory Archive Tab -->
        <div id="inventoryTab" class="tab-content active">
            <div class="archive-section">
                <div class="section-header">
                    <h2><i class="fas fa-box-open"></i> Archived Inventory</h2>
                    <span id="inventoryCount" style="color: #64748b; font-size: 14px;">0 items</span>
                </div>
                
                <table class="archive-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Brand</th>
                            <th>Generic Name</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Supplier</th>
                            <th>Expiration</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr>
                            <td colspan="10" class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>Loading archived inventory...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Suppliers Archive Tab -->
        <div id="suppliersTab" class="tab-content">
            <div class="archive-section">
                <div class="section-header">
                    <h2><i class="fas fa-building"></i> Archived Suppliers</h2>
                    <span id="suppliersCount" style="color: #64748b; font-size: 14px;">0 suppliers</span>
                </div>

                <table class="archive-table">
                    <thead>
                        <tr>
                            <th>Supplier ID</th>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Phone Number</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody">
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-building"></i>
                                <p>Loading archived suppliers...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deliveries Archive Tab -->
        <div id="deliveriesTab" class="tab-content">
            <div class="archive-section">
                <div class="section-header">
                    <h2><i class="fas fa-truck"></i> Archived Supply Deliveries</h2>
                    <span id="deliveriesCount" style="color: #64748b; font-size: 14px;">0 deliveries</span>
                </div>
                <table class="archive-table">
                    <thead>
                        <tr>
                            <th>Delivery ID</th>
                            <th>Supplier</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Delivery Date</th>
                            <th>Received By</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTableBody">
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-truck"></i>
                                <p>Loading archived deliveries...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Accounts Archive Tab -->
        <div id="accountsTab" class="tab-content">
            <div class="archive-section">
                <div class="section-header">
                    <h2><i class="fas fa-user-circle"></i> Archived Accounts</h2>
                    <span id="accountsCount" style="color: #64748b; font-size: 14px;">0 accounts</span>
                </div>

                <table class="archive-table">
                    <thead>
                        <tr>
                            <th>Account ID</th>
                            <th>Full Name</th>
                            <th>Phone Number</th>
                            <th>Role</th>
                            <th>Schedule</th>
                            <th>Birthday</th>
                            <th>Address</th>
                            <th>Archived Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="accountsTableBody">
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-user-circle"></i>
                                <p>Loading archived accounts...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>



    <script src="js/api-adapter.js"></script>
    <script>
        let archivedInventory = [];
        let archivedSuppliers = [];
        let archivedAccounts = [];

        // Load archived data on page load
        document.addEventListener('DOMContentLoaded', async function() {
            await loadArchivedInventory();
            await loadArchivedSuppliers();
            await loadArchivedAccounts();
            await loadArchivedDeliveries();
            updateStatistics();

        });

        // Switch tabs
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.tab-btn').classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            if (tab === 'inventory') {
                document.getElementById('inventoryTab').classList.add('active');
            } else if (tab === 'suppliers') {
                document.getElementById('suppliersTab').classList.add('active');
            } else if (tab === 'deliveries') {
                document.getElementById('deliveriesTab').classList.add('active');
                loadArchivedDeliveries();
            } else if (tab === 'accounts') {
                document.getElementById('accountsTab').classList.add('active');
                loadArchivedAccounts(); // Always reload accounts when switching to this tab
            }
        }

        // ============================================
        // DELIVERIES ARCHIVE
        // ============================================

        var archivedDeliveries = [];

        async function loadArchivedDeliveries() {
            try {
                const response = await fetch('api/archive.php?type=deliveries');
                const result = await response.json();
                if (result.success && result.data) {
                    archivedDeliveries = result.data;
                    displayArchivedDeliveries(archivedDeliveries);
                } else {
                    archivedDeliveries = [];
                    displayArchivedDeliveries([]);
                }
                updateStatistics();
            } catch (error) {
                console.error('Error loading archived deliveries:', error);
                archivedDeliveries = [];
                displayArchivedDeliveries([]);
                updateStatistics();
            }
        }

        function displayArchivedDeliveries(deliveries) {
            const tableBody = document.getElementById('deliveriesTableBody');
            const deliveriesCount = document.getElementById('deliveriesCount');

            if (deliveries.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-truck"></i>
                            <p>No archived deliveries</p>
                            <small>Deleted deliveries will appear here</small>
                        </td>
                    </tr>
                `;
                deliveriesCount.textContent = '0 deliveries';
                return;
            }

            tableBody.innerHTML = '';
            deliveries.forEach(delivery => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${delivery.supplyId || ''}</td>
                    <td>${delivery.supplierId || ''}</td>
                    <td>${delivery.productId || ''}</td>
                    <td>${delivery.quantity || ''}</td>
                    <td>${delivery.deliveryDate && !isNaN(Date.parse(delivery.deliveryDate)) ? new Date(delivery.deliveryDate).toLocaleDateString() : 'N/A'}</td>
                    <td>${delivery.receivedBy || ''}</td>
                    <td>${delivery.archivedAt ? new Date(delivery.archivedAt).toLocaleDateString() : 'N/A'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-restore" onclick="restoreDelivery('${delivery.supplyId}')">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            deliveriesCount.textContent = `${deliveries.length} deliver${deliveries.length !== 1 ? 'ies' : 'y'}`;
        }

            async function restoreDelivery(id) {
                if (!confirm('Are you sure you want to restore this delivery?')) {
                    return;
                }
                try {
                    const payload = {
                        action: 'restore',
                        type: 'deliveries',
                        supplyId: id
                    };
                    console.debug('[restoreDelivery] Payload:', payload);
                    const response = await fetch('api/archive.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const text = await response.text();
                    console.debug('[restoreDelivery] Raw response:', text);
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        console.error('[restoreDelivery] Failed to parse JSON:', e, text);
                        alert('Restore failed: Invalid server response.');
                        return;
                    }
                    if (result.success) {
                        alert('Delivery restored successfully!');
                        await loadArchivedDeliveries();
                        updateStatistics();
                    } else {
                        alert('Error: ' + (result.message || 'Failed to restore delivery'));
                    }
                } catch (error) {
                    console.error('Error restoring delivery:', error);
                    alert('Error restoring delivery: ' + error.message);
                }
            }
        async function deleteDeliveryPermanent(id) {
            if (!confirm('Are you sure you want to PERMANENTLY delete this delivery? This action cannot be undone!')) {
                return;
            }
            if (!confirm('This will permanently remove the delivery from the database. Are you absolutely sure?')) {
                return;
            }
            try {
                const response = await fetch('api/archive.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'deliveries',
                        supplyId: id
                    })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Delivery permanently deleted!');
                    await loadArchivedDeliveries();
                    updateStatistics();
                } else {
                    alert('Error: ' + (result.message || 'Failed to delete delivery'));
                }
            } catch (error) {
                console.error('Error deleting delivery:', error);
                alert('Error deleting delivery: ' + error.message);
            }
        }


        

        // Update statistics
        function updateStatistics() {
            document.getElementById('archivedInventoryCount').textContent = archivedInventory.length;
            document.getElementById('archivedSuppliersCount').textContent = archivedSuppliers.length;
            // Always use the latest archivedDeliveries array
            document.getElementById('archivedDeliveriesCount').textContent = Array.isArray(archivedDeliveries) ? archivedDeliveries.length : 0;
            // Only update if element exists
            const archivedAccountsCount = document.getElementById('archivedAccountsCount');
            if (archivedAccountsCount) archivedAccountsCount.textContent = archivedAccounts.length;
            const archivedEmployeesCount = document.getElementById('archivedEmployeesCount');
            if (archivedEmployeesCount) archivedEmployeesCount.textContent = archivedEmployees ? archivedEmployees.length : 0;
        }

        // ============================================
        // INVENTORY ARCHIVE
        // ============================================

        // Load archived inventory
        async function loadArchivedInventory() {
            try {
                const response = await fetch('api/archive.php?type=inventory');
                const result = await response.json();
                if (result.success && result.data) {
                    archivedInventory = result.data;
                    displayArchivedInventory(archivedInventory);
                } else {
                    archivedInventory = [];
                    displayArchivedInventory([]);
                }
            } catch (error) {
                console.error('Error loading archived inventory:', error);
                archivedInventory = [];
                displayArchivedInventory([]);
            }
        }

        // Display archived inventory
        function displayArchivedInventory(items) {
            const tableBody = document.getElementById('inventoryTableBody');
            const inventoryCount = document.getElementById('inventoryCount');

            if (items.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="10" class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>No archived inventory</p>
                            <small>Deleted inventory items will appear here</small>
                        </td>
                    </tr>
                `;
                inventoryCount.textContent = '0 items';
                return;
            }

            tableBody.innerHTML = '';
            items.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${item.productId || ''}</td>
                    <td><strong>${item.brand || ''}</strong></td>
                    <td>${item.genericName || ''}</td>
                    <td>${item.category || ''}</td>
                    <td>${item.stock || ''}</td>
                    <td>${item.price || ''}</td>
                    <td>${item.supplier || ''}</td>
                    <td>${item.expiration ? new Date(item.expiration).toLocaleDateString() : 'N/A'}</td>
                    <td>${item.archivedAt ? new Date(item.archivedAt).toLocaleDateString() : 'N/A'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-restore" onclick="restoreInventory('${item.productId}')">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            inventoryCount.textContent = `${items.length} item${items.length !== 1 ? 's' : ''}`;
        }

        // ============================================
        // Load archived accounts
        // ============================================
        async function loadArchivedAccounts() {
            try {
                const response = await fetch('api/archive.php?type=accounts');
                let text = await response.text();
                let result = null;
                try {
                    if (text && text.trim().startsWith('{')) {
                        result = JSON.parse(text);
                    } else {
                        throw new Error('Empty or invalid JSON response');
                    }
                } catch (jsonError) {
                    console.error('Error parsing archived accounts JSON:', jsonError, text);
                    archivedAccounts = [];
                    displayArchivedAccounts([]);
                    return;
                }
                if (result && result.success && result.data) {
                    archivedAccounts = result.data;
                    displayArchivedAccounts(archivedAccounts);
                } else {
                    archivedAccounts = [];
                    displayArchivedAccounts([]);
                }
            } catch (error) {
                console.error('Error loading archived accounts:', error);
                archivedAccounts = [];
                displayArchivedAccounts([]);
            }
        }

        // Display archived accounts
        function displayArchivedAccounts(accounts) {
            const tableBody = document.getElementById('accountsTableBody');
            const accountsCount = document.getElementById('accountsCount');

            if (accounts.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="9" class="empty-state">
                            <i class="fas fa-user-circle"></i>
                            <p>No archived accounts</p>
                            <small>Deleted accounts will appear here</small>
                        </td>
                    </tr>
                `;
                accountsCount.textContent = '0 accounts';
                return;
            }

            tableBody.innerHTML = '';
            accounts.forEach(account => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${account.accountId || ''}</td>
                    <td><strong>${account.fullName || ''}</strong></td>
                    <td>${account.phoneNumber || ''}</td>
                    <td>${account.role || ''}</td>
                    <td>${account.schedule || ''}</td>
                    <td>${account.birthday ? new Date(account.birthday).toLocaleDateString() : 'N/A'}</td>
                    <td>${account.address || ''}</td>
                    <td>${account.archivedAt ? new Date(account.archivedAt).toLocaleDateString() : 'N/A'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-restore" onclick="restoreAccount('${account.accountId}')">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            accountsCount.textContent = `${accounts.length} account${accounts.length !== 1 ? 's' : ''}`;
        }

        // Restore account
        async function restoreAccount(id) {
            if (!confirm('Are you sure you want to restore this account?')) {
                return;
            }

            try {
                const response = await fetch('api/archive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'restore',
                        type: 'accounts',
                        accountId: id
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Account restored successfully!');
                    await loadArchivedAccounts();
                    updateStatistics();
                } else {
                    alert('Error: ' + (result.message || 'Failed to restore account'));
                }
            } catch (error) {
                console.error('Error restoring account:', error);
                alert('Error restoring account: ' + error.message);
            }
        }


        // Restore inventory item
        async function restoreInventory(id) {
            if (!confirm('Are you sure you want to restore this inventory item?')) {
                return;
            }

            try {
                const response = await fetch('api/archive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'restore',
                        type: 'inventory',
                        productId: id
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Inventory item restored successfully!');
                    await loadArchivedInventory();
                    updateStatistics();
                } else {
                    alert('Error: ' + (result.message || 'Failed to restore item'));
                }
            } catch (error) {
                console.error('Error restoring inventory:', error);
                alert('Error restoring inventory: ' + error.message);
            }
        }

        // Delete inventory item permanently
        async function deleteInventoryPermanent(id) {
            if (!confirm('Are you sure you want to PERMANENTLY delete this item? This action cannot be undone!')) {
                return;
            }

            if (!confirm('This will permanently remove the item from the database. Are you absolutely sure?')) {
                return;
            }

            try {
                const response = await fetch('api/archive.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'inventory',
                        id: id
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Inventory item permanently deleted!');
                    await loadArchivedInventory();
                    updateStatistics();
                } else {
                    alert('Error: ' + (result.message || 'Failed to delete item'));
                }
            } catch (error) {
                console.error('Error deleting inventory:', error);
                alert('Error deleting inventory: ' + error.message);
            }
        }

        // ============================================
        // SUPPLIERS ARCHIVE
        // ============================================

        // Load archived suppliers
        async function loadArchivedSuppliers() {
            try {
                const response = await fetch('api/archive.php?type=suppliers');
                const result = await response.json();
                if (result.success && result.data) {
                    archivedSuppliers = result.data;
                    displayArchivedSuppliers(archivedSuppliers);
                } else {
                    archivedSuppliers = [];
                    displayArchivedSuppliers([]);
                }
            } catch (error) {
                console.error('Error loading archived suppliers:', error);
                archivedSuppliers = [];
                displayArchivedSuppliers([]);
            }
        }

        // Display archived suppliers
        function displayArchivedSuppliers(suppliers) {
            const tableBody = document.getElementById('suppliersTableBody');
            const suppliersCount = document.getElementById('suppliersCount');

            if (suppliers.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>No archived suppliers</p>
                            <small>Deleted suppliers will appear here</small>
                        </td>
                    </tr>
                `;
                suppliersCount.textContent = '0 suppliers';
                return;
            }

            tableBody.innerHTML = '';
            suppliers.forEach(supplier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${supplier.supplierId || ''}</td>
                    <td><strong>${supplier.name || ''}</strong></td>
                    <td>${supplier.contactPerson || ''}</td>
                    <td>${supplier.phoneNumber || ''}</td>
                    <td>${supplier.email || ''}</td>
                    <td>${supplier.address || ''}</td>
                    <td>${supplier.archivedAt ? new Date(supplier.archivedAt).toLocaleDateString() : 'N/A'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-restore" onclick="restoreSupplier('${supplier.supplierId}')">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            suppliersCount.textContent = `${suppliers.length} supplier${suppliers.length !== 1 ? 's' : ''}`;
        }

        // Restore supplier
        async function restoreSupplier(id) {
            if (!confirm('Are you sure you want to restore this supplier?')) {
                return;
            }

            try {
                const response = await fetch('api/archive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'restore',
                        type: 'suppliers',
                        supplierId: id
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Supplier restored successfully!');
                    await loadArchivedSuppliers();
                    updateStatistics();
                } else {
                    alert('Error: ' + (result.message || 'Failed to restore supplier'));
                }
            } catch (error) {
                console.error('Error restoring supplier:', error);
                alert('Error restoring supplier: ' + error.message);
            }
        }

        // Delete supplier permanently
        async function deleteSupplierPermanent(id) {
            if (!confirm('Are you sure you want to PERMANENTLY delete this supplier? This action cannot be undone!')) {
                return;
            }

            if (!confirm('This will permanently remove the supplier from storage. Are you absolutely sure?')) {
                return;
            }

            try {
                const archive = JSON.parse(localStorage.getItem('archive_suppliers') || '[]');
                const updatedArchive = archive.filter(s => s.id !== id);
                localStorage.setItem('archive_suppliers', JSON.stringify(updatedArchive));

                alert('Supplier permanently deleted!');
                await loadArchivedSuppliers();
                updateStatistics();
            } catch (error) {
                console.error('Error deleting supplier:', error);
                alert('Error deleting supplier: ' + error.message);
            }
        }

        // Make functions globally accessible
            // Add to global scope
            window.restoreDelivery = restoreDelivery;
            window.deleteDeliveryPermanent = deleteDeliveryPermanent;
            window.switchTab = switchTab;
            window.restoreInventory = restoreInventory;
            window.deleteInventoryPermanent = deleteInventoryPermanent;
            window.restoreSupplier = restoreSupplier;
            window.deleteSupplierPermanent = deleteSupplierPermanent;
            window.restoreAccount = restoreAccount;
    </script>
</body>
</html>