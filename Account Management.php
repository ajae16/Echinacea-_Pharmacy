<?php
session_start();
define('DB_ACCESS', true);
require_once 'database/db_config.php';
requirePermission('Account.view');

// Get user information
$userName = $_SESSION['fullName'] ?? $_SESSION['username'];
$userRole = ucfirst($_SESSION['userRole'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Echinacea Pharmacy MIS</title>
    <link rel="stylesheet" href="css/Account Management.css">
    <link rel="stylesheet" href="css/Pharmacy.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="page-outer-box">
        

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <div class="search-filter-grid">
                <div class="input-group">
                    <label><i class="fas fa-search"></i> Search Users</label>
                    <input type="text" id="searchInput" placeholder="Search by username, full name, or email...">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-filter"></i> Filter by Role</label>
                    <select id="roleFilter">
                        <option value="">All Roles</option>
                        <option value="admin">Administrator</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>&nbsp;</label>
                    <button class="btn-secondary" onclick="applyFilters()">
                        <i class="fas fa-sync-alt"></i> Apply
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> System Users</h2>
                <div class="header-actions">
                </div>
            </div>
            <!-- User and Roles Table Grid -->
            <div class="tables-grid">
                <div class="user-table-card">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> System Users</h2>
                        <div class="header-actions">
                            <button class="btn-primary" onclick="showCreateModal()">
                                <i class="fas fa-user-plus"></i>
                                Add New User
                            </button>
                        </div>
                        <span id="userCount" style="color: #64748b; font-size: 14px;">0 users</span>
                    </div>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="accountUsersTableBody">
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>Loading users...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="roles-table-card">
                    <div class="section-header">
                        <h2><i class="fas fa-user-shield"></i> Roles</h2>
                        <div class="header-actions">
                            <button class="btn-primary" onclick="showRoleModal()">
                                <i class="fas fa-plus"></i> Add Role
                            </button>
                        </div>
                        <span id="roleCount" style="color: #64748b; font-size: 14px;"></span>
                    </div>
                    <table class="roles-table">
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rolesTableBody">
                            <tr>
                                <td colspan="3" class="empty-state">
                                    <i class="fas fa-user-shield"></i>
                                    <p>Loading roles...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- User Modal -->
    <div id="userModal" class="user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New User</h2>
                <span id="modalClose" class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="userForm" onsubmit="event.preventDefault(); submitUser();">
                    <div class="form-columns">
                        <div class="form-column">
                            <h3><i class="fas fa-user-shield"></i> Account Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="username"><i class="fas fa-user"></i> Username *</label>
                                    <input type="text" id="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="firstName"><i class="fas fa-id-card"></i> First Name *</label>
                                    <input type="text" id="firstName" required>
                                </div>
                                <div class="form-group">
                                    <label for="middleName"><i class="fas fa-id-card"></i> Middle Name</label>
                                    <input type="text" id="middleName">
                                </div>
                                <div class="form-group">
                                    <label for="lastName"><i class="fas fa-id-card"></i> Last Name *</label>
                                    <input type="text" id="lastName" required>
                                </div>
                                <div class="form-group">
                                    <label for="status"><i class="fas fa-toggle-on"></i> Status *</label>
                                    <select id="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                                    <input type="email" id="email" required pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$">
                                </div>
                                <!-- User Role input removed: role will be assigned by admin/manager only -->

                                    <div class="form-group password-toggle">
                                        <label for="password"><i class="fas fa-lock"></i> Password *</label>
                                        <input type="password" id="password" required minlength="8">
                                        <button type="button" class="password-toggle-btn" onclick="togglePassword('password')">
                                            <i class="fas fa-eye" id="password-icon"></i>
                                        </button>
                                    </div>

                                    <div class="form-group password-toggle">
                                        <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm Password *</label>
                                        <input type="password" id="confirmPassword" required minlength="8">
                                        <button type="button" class="password-toggle-btn" onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye" id="confirmPassword-icon"></i>
                                        </button>
                                    </div>
                                    <!-- Password Strength Checklist -->
                                    <div id="password-checklist" class="password-checklist">
                                        <div class="checklist-item" data-type="length">
                                            <i class="fas fa-times"></i>
                                            <span>At least 8 characters</span>
                                        </div>
                                        <div class="checklist-item" data-type="lower">
                                            <i class="fas fa-times"></i>
                                            <span>One lowercase letter (a-z)</span>
                                        </div>
                                        <div class="checklist-item" data-type="upper">
                                            <i class="fas fa-times"></i>
                                            <span>One uppercase letter (A-Z)</span>
                                        </div>
                                        <div class="checklist-item" data-type="number">
                                            <i class="fas fa-times"></i>
                                            <span>One number (0-9)</span>
                                        </div>
                                    </div>
                                <script>
                                // Password checklist visual update for user creation modal
                                function updatePasswordChecklistMgmt(password) {
                                    // Length
                                    const lengthItem = document.querySelector('#password-checklist [data-type="length"]');
                                    if (lengthItem) {
                                        if (password.length >= 8) {
                                            lengthItem.querySelector('i').className = 'fas fa-check';
                                            lengthItem.classList.add('met');
                                        } else {
                                            lengthItem.querySelector('i').className = 'fas fa-times';
                                            lengthItem.classList.remove('met');
                                        }
                                    }
                                    // Lowercase
                                    const lowerItem = document.querySelector('#password-checklist [data-type="lower"]');
                                    if (lowerItem) {
                                        if (/[a-z]/.test(password)) {
                                            lowerItem.querySelector('i').className = 'fas fa-check';
                                            lowerItem.classList.add('met');
                                        } else {
                                            lowerItem.querySelector('i').className = 'fas fa-times';
                                            lowerItem.classList.remove('met');
                                        }
                                    }
                                    // Uppercase
                                    const upperItem = document.querySelector('#password-checklist [data-type="upper"]');
                                    if (upperItem) {
                                        if (/[A-Z]/.test(password)) {
                                            upperItem.querySelector('i').className = 'fas fa-check';
                                            upperItem.classList.add('met');
                                        } else {
                                            upperItem.querySelector('i').className = 'fas fa-times';
                                            upperItem.classList.remove('met');
                                        }
                                    }
                                    // Number
                                    const numberItem = document.querySelector('#password-checklist [data-type="number"]');
                                    if (numberItem) {
                                        if (/[0-9]/.test(password)) {
                                            numberItem.querySelector('i').className = 'fas fa-check';
                                            numberItem.classList.add('met');
                                        } else {
                                            numberItem.querySelector('i').className = 'fas fa-times';
                                            numberItem.classList.remove('met');
                                        }
                                    }
                                }
                                document.addEventListener('DOMContentLoaded', function() {
                                    const passwordInput = document.getElementById('password');
                                    if (passwordInput) {
                                        passwordInput.addEventListener('input', function() {
                                            updatePasswordChecklistMgmt(passwordInput.value);
                                        });
                                        // Initial update in case of autofill
                                        updatePasswordChecklistMgmt(passwordInput.value);
                                    }
                                });
                                // Add some CSS for .met class for visual feedback
                                const style = document.createElement('style');
                                style.innerHTML = `.password-checklist .met { color: #16a34a; font-weight: bold; }`;
                                document.head.appendChild(style);
                                </script>
                            </div>
                        </div>

                        <div class="form-column">
                            <h3><i class="fas fa-id-card"></i> Additional Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="phoneNumber"><i class="fas fa-phone"></i> Phone Number</label>
                                    <input type="tel" id="phoneNumber" required pattern="09[0-9]{9}" maxlength="11" minlength="11" title="Phone number must start with 09 and be exactly 11 digits">
                                    <span class="error-message" id="phoneNumberError">Phone number must start with 09 and be exactly 11 digits</span>
                                </div>
                                <div class="form-group">
                                    <label for="birthday"><i class="fas fa-birthday-cake"></i> Birthday</label>
                                    <input type="date" id="birthday">
                                </div>
                                <div class="form-group">
                                    <label for="sex"><i class="fas fa-venus-mars"></i> Sex</label>
                                    <select id="sex">
                                        <option value="">Select Sex</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group full-width">
                                    <label><i class="fas fa-calendar"></i> Schedule</label>
                                    <div id="schedule-checklist" class="schedule-checklist" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">
                                        <label><input type="checkbox" value="MON"> MON</label>
                                        <label><input type="checkbox" value="TUE"> TUE</label>
                                        <label><input type="checkbox" value="WED"> WED</label>
                                        <label><input type="checkbox" value="THU"> THU</label>
                                        <label><input type="checkbox" value="FRI"> FRI</label>
                                        <label><input type="checkbox" value="SAT"> SAT</label>
                                        <label><input type="checkbox" value="SUN"> SUN</label>
                                    </div>
                                </div>
                                <div class="form-group full-width">
                                    <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                                    <textarea id="address"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="modalCancel" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit" form="userForm">
                    <i class="fas fa-save"></i> Save User
                </button>
            </div>
        </div>
    </div>

<!-- Role Modal -->
<div id="roleModal" class="role-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="roleModalTitle">Add Role</h2>
            <span class="close" onclick="closeRoleModal()">&times;</span>
        </div>
        <div class="modal-body">
        <form id="roleForm" onsubmit="event.preventDefault(); submitRole();">
            <div class="form-group">
                <label for="roleName">Role Name *</label>
                <input type="text" id="roleName" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description"></textarea>
            </div>
            <div class="form-group">
                <label>Permissions</label>
                <div id="permissionsChecklist">
                    <!-- Permissions will be loaded here by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeRoleModal()">Cancel</button>
                <button type="submit" class="btn-submit">Save Role</button>
            </div>
        </form>
        </div>
    </div>
</div>


<!-- Assign Role Modal -->
<div id="assignRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Role</h2>
            <span class="close" onclick="closeAssignRoleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="assignRoleForm" onsubmit="event.preventDefault(); submitAssignRole();">
                <input type="hidden" id="assignUserId">
                <div class="form-group">
                    <label for="assignRoleSelect">Select Role</label>
                    <select id="assignRoleSelect" required></select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeAssignRoleModal()">Cancel</button>
            <button type="submit" class="btn-submit" form="assignRoleForm"><i class="fas fa-save"></i> Assign Role</button>
        </div>
    </div>
</div>

    <script src="js/api-adapter.js"></script>
    <script src="js/Account Management.js"></script>
    
</body>
</html>