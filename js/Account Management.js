// ...existing code...
                // Helper: set checklist from array or legacy string
                function setScheduleChecklist(schedule) {
                    const days = ["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"];
                    let selected = [];
                    if (Array.isArray(schedule)) {
                        selected = schedule;
                    } else if (typeof schedule === 'string') {
                        // Support legacy comma-separated string
                        selected = schedule.split(',').map(s => s.trim()).filter(Boolean);
                    }
                    document.querySelectorAll('#schedule-checklist input[type="checkbox"]').forEach(cb => {
                        cb.checked = selected.includes(cb.value);
                        cb.disabled = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea')[0].disabled;
                    });
                }

                // Helper: get checked days as array
                function getScheduleChecklist() {
                    return Array.from(document.querySelectorAll('#schedule-checklist input[type="checkbox"]:checked')).map(cb => cb.value);
                }
                // --- Assign Role Modal Logic (from Role Management.js) ---
                function showAssignRoleModal(userId) {
                    // Show modal to pick a role
                    const modal = document.getElementById('assignRoleModal');
                    const userIdInput = document.getElementById('assignUserId');
                    const select = document.getElementById('assignRoleSelect');
                    if (!modal || !userIdInput || !select) return;
                    userIdInput.value = userId;
                    select.innerHTML = '';
                    // Fetch user's current role and pre-select
                    fetch(`api/roles.php?action=userRole&accountId=${encodeURIComponent(userId)}`)
                        .then(res => res.json())
                        .then(data => {
                            const currentRoleId = data.success && data.data && data.data.roleId ? String(data.data.roleId) : null;
                            (window._allRoles || []).forEach(role => {
                                const opt = document.createElement('option');
                                opt.value = role.roleId;
                                opt.textContent = role.displayName || role.roleName;
                                select.appendChild(opt);
                            });
                            if (currentRoleId) select.value = currentRoleId;
                            modal.style.display = 'block';
                        });
                }

                function closeAssignRoleModal() {
                    document.getElementById('assignRoleModal').style.display = 'none';
                }

                async function submitAssignRole() {
                    const userId = document.getElementById('assignUserId').value;
                    const roleId = document.getElementById('assignRoleSelect').value;
                    await assignRole(userId, roleId);
                    closeAssignRoleModal();
                }

                async function assignRole(userId, roleId) {
                    const res = await fetch('api/roles.php?action=assign', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({accountId: userId, roleId}),
                        credentials: 'include'
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert('Role assigned');
                        loadUsers();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }
                // Helper to format roles for display
                function formatRoles(user) {
                    if (user.roles && Array.isArray(user.roles) && user.roles.length) {
                        return user.roles.map(r => `<span class="badge">${r}</span>`).join(' ');
                    }
                    return `<span class="badge">${(user.roles && user.roles.length ? user.roles[0] : 'Employee').toUpperCase()}</span>`;
                }
        // Delete user
        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            try {
                // You should implement the actual API call here
                // Example: await deleteEmployee(id);
                alert('User deleted (API call not implemented)');
                await loadUsers();
            } catch (error) {
                alert('Error deleting user: ' + error.message);
            }
        }
// Account Management UI logic
        let editingId = null;
        let allUsers = [];

        // Load users on page load
        document.addEventListener('DOMContentLoaded', async function() {
            await loadUsers();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
            const roleFilter = document.getElementById('roleFilter');
            if (roleFilter) {
                roleFilter.addEventListener('change', applyFilters);
            }
        }

        // Load and display users
        async function loadUsers() {
            try {
                const users = await readEmployees();
                allUsers = users;
                displayUsers(users);
            } catch (error) {
                console.error('Error loading users:', error);
                const tableBody = document.getElementById('accountUsersTableBody');
                if (tableBody) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading users</p>
                            </td>
                        </tr>
                    `;
                }
            }
        }


        // Display users in table
        function displayUsers(users) {
            const tableBody = document.getElementById('accountUsersTableBody');
            if (!tableBody) return;
            if (users.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users found</p>
                            <button class="btn-primary" onclick="showCreateModal()" style="margin-top: 15px;">
                                <i class="fas fa-user-plus"></i> Add Your First User
                            </button>
                        </td>
                    </tr>
                `;
                document.getElementById('userCount').textContent = '0 users';
                return;
            }
            tableBody.innerHTML = '';
            users.forEach(user => {
                const row = document.createElement('tr');
                const roleClass = (Array.isArray(user.roles) && user.roles.includes('admin')) ? 'admin' : 'employee';
                const statusClass = user.status === 'active' ? 'active' : 'inactive';
                const createdAt = user.createdAt ? new Date(user.createdAt).toLocaleDateString() : 'N/A';
                row.innerHTML = `
                    <td>#${user.accountId || ''}</td>
                    <td><strong>${user.username || ''}</strong></td>
                    <td>${user.fullName || ''}</td>
                    <td>${user.email ? user.email : 'N/A'}</td>
                    <td>${formatRoles(user)}</td>
                    <td>
                        <button class="status-toggle-btn badge ${statusClass}" onclick="toggleUserStatus('${user.accountId}', '${user.status}')">
                            ${(user.status || 'ACTIVE').toUpperCase()}
                        </button>
                    </td>
                    <td>${createdAt}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewEmployeeRecords('${user.accountId}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-edit" onclick="editUser('${user.accountId}')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-assign" onclick="showAssignRoleModal('${user.accountId}')">
                                <i class="fas fa-user-tag"></i> Role
                            </button>
                            <button class="btn-delete" onclick="archiveUser('${user.accountId}')">
                                <i class="fas fa-trash"></i> Archive
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            // Archive user function
            window.archiveUser = async function(accountId) {
                if (!confirm('Are you sure you want to archive this user?')) return;
                try {
                    const res = await fetch('api/accounts.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: accountId })
                    });
                    const result = await res.json();
                    if (result.success) {
                        alert('User archived successfully!');
                        await loadUsers();
                    } else {
                        alert('Failed to archive user: ' + (result.message || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Error archiving user: ' + err.message);
                }
            }
            // Toggle user status (active/inactive) and update in database
            async function toggleUserStatus(accountId, currentStatus) {
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                try {
                    const res = await fetch('api/accounts.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: accountId, status: newStatus })
                    });
                    const result = await res.json();
                    if (result.success) {
                        await loadUsers();
                    } else {
                        alert('Failed to update status: ' + (result.message || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Error updating status: ' + err.message);
                }
            }
            // Expose toggleUserStatus globally for inline onclick
            window.toggleUserStatus = toggleUserStatus;
            document.getElementById('userCount').textContent = `${users.length} user${users.length !== 1 ? 's' : ''}`;
        }

        // Apply filters
        function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value.toLowerCase();

            let filtered = allUsers.filter(user => {
                // Search filter
                const matchesSearch = !searchTerm || 
                    (user.username && user.username.toLowerCase().includes(searchTerm)) ||
                    (user.fullName && user.fullName.toLowerCase().includes(searchTerm)) ||
                    (user.email && user.email.toLowerCase().includes(searchTerm));

                // Role filter
                const matchesRole = !roleFilter || 
                    (Array.isArray(user.roles) && user.roles.some(r => r.toLowerCase() === roleFilter));

                return matchesSearch && matchesRole;
            });

            displayUsers(filtered);
        }

        // Show create modal
        function showCreateModal() {
            editingId = null;
            document.getElementById('modalTitle').innerText = 'Add New User';
            document.getElementById('userForm').reset();
            document.getElementById('userModal').style.display = 'block';
        }

        // View employee records
        function viewEmployeeRecords(id) {
            showViewModal(id);
        }

        // Show view modal
        async function showViewModal(id) {
            const users = await readEmployees(id);
            const user = users[0];
            if (user) {
                document.getElementById('modalTitle').innerText = 'View User Details';
                document.getElementById('username').value = user.username || '';
                document.getElementById('firstName').value = user.firstName || '';
                document.getElementById('middleName').value = user.middleName || '';
                document.getElementById('lastName').value = user.lastName || '';
                document.getElementById('email').value = user.email || '';
                // User Role input removed; nothing to set
                document.getElementById('status').value = user.status || 'active';
                document.getElementById('phoneNumber').value = user.phoneNumber || '';
                setScheduleChecklist(user.schedule);
                document.getElementById('birthday').value = user.birthday || '';
                document.getElementById('sex').value = user.sex || '';
                document.getElementById('address').value = user.address || '';
                // Hide password fields
                document.querySelectorAll('.password-toggle').forEach(el => el.style.display = 'none');
                // Disable all inputs
                const inputs = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea');
                inputs.forEach(input => {
                    input.disabled = true;
                });
                // Change submit button to close
                const submitBtn = document.querySelector('.btn-submit');
                submitBtn.innerHTML = '<i class="fas fa-times"></i> Close';
                submitBtn.type = 'button';
                submitBtn.onclick = closeModal;
                // Hide cancel button
                document.getElementById('modalCancel').style.display = 'none';
                // Hide close button
                document.getElementById('modalClose').style.display = 'none';
                document.getElementById('userModal').style.display = 'block';
            }
        }



        // Edit user
        async function editUser(id) {
            const users = await readEmployees(id);
            const user = users[0];

            if (user) {
                editingId = id;
                document.getElementById('modalTitle').innerText = 'Update User';

                // Account information (all data is now in accounts table)
                document.getElementById('username').value = user.username || '';
                document.getElementById('firstName').value = user.firstName || '';
                document.getElementById('middleName').value = user.middleName || '';
                document.getElementById('lastName').value = user.lastName || '';
                document.getElementById('email').value = user.email || '';
                // User Role input removed; nothing to set
                document.getElementById('status').value = user.status || 'active';
                document.getElementById('phoneNumber').value = user.phoneNumber || '';
                // Always set the checklist to the user's current schedule (array or string)
                setScheduleChecklist(user.schedule);
                document.getElementById('birthday').value = user.birthday || '';
                document.getElementById('sex').value = user.sex || '';
                document.getElementById('address').value = user.address || '';
                document.getElementById('password').value = '';
                document.getElementById('confirmPassword').value = '';
                document.getElementById('password').removeAttribute('required');
                document.getElementById('confirmPassword').removeAttribute('required');

                document.getElementById('userModal').style.display = 'block';
            }
        }

        // Validate user form
        function validateUserForm() {
            let isValid = true;
            const errors = [];

            // Name validation pattern (letters, spaces, hyphens, apostrophes only)
            const namePattern = /^[a-zA-Z\s\-']+$/;
            // Username validation pattern (alphanumeric, underscore, hyphen, 3-20 chars)
            const usernamePattern = /^[a-zA-Z0-9_-]{3,20}$/;
            // Email validation pattern
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            // Phone number validation pattern (must start with 09 and be exactly 11 digits)
            const phonePattern = /^09\d{9}$/;
            // Validate Phone Number
            const phoneNumber = document.getElementById('phoneNumber').value.trim();
            if (!phoneNumber) {
                errors.push('Phone Number is required');
                isValid = false;
            } else if (!phonePattern.test(phoneNumber)) {
                errors.push('Phone Number must start with 09 and be exactly 11 digits');
                isValid = false;
            }

            // Validate Username
            const username = document.getElementById('username').value.trim();
            if (!username) {
                errors.push('Username is required');
                isValid = false;
            } else if (username.length < 3) {
                errors.push('Username must be at least 3 characters');
                isValid = false;
            } else if (username.length > 20) {
                errors.push('Username must not exceed 20 characters');
                isValid = false;
            } else if (!usernamePattern.test(username)) {
                errors.push('Username can only contain letters, numbers, underscores, and hyphens');
                isValid = false;
            }

            // Validate First Name
            const firstName = document.getElementById('firstName').value.trim();
            if (!firstName) {
                errors.push('First Name is required');
                isValid = false;
            } else if (firstName.length < 2) {
                errors.push('First Name must be at least 2 characters');
                isValid = false;
            } else if (firstName.length > 50) {
                errors.push('First Name must not exceed 50 characters');
                isValid = false;
            } else if (!namePattern.test(firstName)) {
                errors.push('First Name can only contain letters, spaces, hyphens, and apostrophes');
                isValid = false;
            }

            // Validate Last Name
            const lastName = document.getElementById('lastName').value.trim();
            if (!lastName) {
                errors.push('Last Name is required');
                isValid = false;
            } else if (lastName.length < 2) {
                errors.push('Last Name must be at least 2 characters');
                isValid = false;
            } else if (lastName.length > 50) {
                errors.push('Last Name must not exceed 50 characters');
                isValid = false;
            } else if (!namePattern.test(lastName)) {
                errors.push('Last Name can only contain letters, spaces, hyphens, and apostrophes');
                isValid = false;
            }

            // Validate Email
            const email = document.getElementById('email').value.trim();
            if (!email) {
                errors.push('Email Address is required');
                isValid = false;
            } else if (!emailPattern.test(email)) {
                errors.push('Please enter a valid email address (e.g., user@example.com)');
                isValid = false;
            } else if (email.length > 100) {
                errors.push('Email address must not exceed 100 characters');
                isValid = false;
            }

            // User Role input removed; skip role validation

            // Validate Status
            const status = document.getElementById('status').value;
            if (!status) {
                errors.push('Please select a Status');
                isValid = false;
            }

            // Validate Password (only for new users or if password field is filled)
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!editingId || password) {
                if (!password) {
                    errors.push('Password is required');
                    isValid = false;
                } else if (password.length < 6) {
                    errors.push('Password must be at least 6 characters');
                    isValid = false;
                } else if (password.length > 100) {
                    errors.push('Password must not exceed 100 characters');
                    isValid = false;
                } else if (!(/[a-z]/.test(password) && /[A-Z]/.test(password) && /[0-9]/.test(password))) {
                    errors.push('Password must contain at least one lowercase letter, one uppercase letter, and one number');
                    isValid = false;
                }

                if (!confirmPassword) {
                    errors.push('Please confirm your password');
                    isValid = false;
                } else if (password !== confirmPassword) {
                    errors.push('Passwords do not match');
                    isValid = false;
                }
            }

            // Show errors if any
            if (!isValid) {
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }

            return isValid;
        }

        // Submit user
        async function submitUser() {
            // Validate form first
            if (!validateUserForm()) {
                return;
            }

            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const middleName = document.getElementById('middleName').value.trim();
            const fullName = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`.trim();
            const password = document.getElementById('password').value;

            // Account data (now includes all fields since records table was merged)
            // Always set new users to 'Staff' role; admin/manager will assign role later
            const isEdit = !!editingId;
            const accountData = {
                username: document.getElementById('username').value.trim(),
                firstName: firstName,
                lastName: lastName,
                fullName: fullName,
                email: document.getElementById('email').value.trim().toLowerCase(),
                status: document.getElementById('status').value,
                middleName: document.getElementById('middleName').value.trim(),
                phoneNumber: document.getElementById('phoneNumber').value.trim(),
                schedule: getScheduleChecklist(),
                birthday: document.getElementById('birthday').value,
                sex: document.getElementById('sex').value,
                address: document.getElementById('address').value.trim()
            };
// Helper: set checklist from array or legacy string
function setScheduleChecklist(schedule) {
    const days = ["MON", "TUE", "WED", "THU", "FRI", "SAT", "SUN"];
    let selected = [];
    if (Array.isArray(schedule)) {
        selected = schedule;
    } else if (typeof schedule === 'string') {
        // Support legacy comma-separated string
        selected = schedule.split(',').map(s => s.trim()).filter(Boolean);
    }
    document.querySelectorAll('#schedule-checklist input[type="checkbox"]').forEach(cb => {
        cb.checked = selected.includes(cb.value);
        cb.disabled = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea')[0].disabled;
    });
}

// Helper: get checked days as array
function getScheduleChecklist() {
    return Array.from(document.querySelectorAll('#schedule-checklist input[type="checkbox"]:checked')).map(cb => cb.value);
}

            // Only include password if it's provided
            if (password) {
                accountData.password = password;
            }

            try {
                if (editingId) {
                    // Update account (all data is now in accounts table)
                    await updateEmployee(editingId, accountData);
                    alert('User updated successfully!');
                } else {
                    // Create new account
                    const result = await createEmployee(accountData);
                    alert('User created successfully!');
                }

                closeModal();
                await loadUsers();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('userModal').style.display = 'none';

            // Reset modal to edit/create mode
            document.getElementById('modalTitle').innerText = 'Add New User';

            // Show password fields
            document.querySelectorAll('.password-toggle').forEach(el => el.style.display = 'block');

            // Enable all inputs
            const inputs = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea');
            inputs.forEach(input => {
                input.disabled = false;
            });

            // Reset submit button
            const submitBtn = document.querySelector('.btn-submit');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save User';
            submitBtn.type = 'submit';
            submitBtn.onclick = null;

            // Show cancel button
            document.getElementById('modalCancel').style.display = 'inline-block';

            // Reset form requirements
            document.getElementById('password').setAttribute('required', 'required');
            document.getElementById('confirmPassword').setAttribute('required', 'required');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeModal();
            }
        }

// Role Management UI logic

document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if roles section exists (for Account Management integration)
    if (document.getElementById('rolesTableBody')) {
        loadRoles();
        window.editRole = editRole;
    }
    if (document.getElementById('accountUsersTableBody')) {
        // loadAccountUsers removed; unified user loading logic is used
        window.showAssignRoleModal = showAssignRoleModal;
        window.closeAssignRoleModal = closeAssignRoleModal;
        window.submitAssignRole = submitAssignRole;
    }
});

// Load roles for Account Management roles table
async function loadRoles() {
    const res = await fetch('api/roles.php');
    const result = await res.json();
    const tbody = document.getElementById('rolesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (result.success && result.data) {
        result.data.forEach(role => {
            const row = document.createElement('tr');
            row.innerHTML = `<td>${role.roleName}</td><td>${role.description || ''}</td><td><button class="btn-edit" onclick="editRole(${role.roleId})">Edit</button></td>`;
            tbody.appendChild(row);
        });
        const roleCount = document.getElementById('roleCount');
        if (roleCount) roleCount.textContent = `${result.data.length} roles`;
    }
    // Store roles for assign modal
    window._allRoles = result.data || [];
        // Load permissions for role modal
        loadPermissions();
    }

    // Load permissions for checklist
    async function loadPermissions(selectedPermissions = [], roleId = null) {
        let url = 'api/roles.php?action=permissions';
        if (roleId) url += `&roleId=${roleId}`;
        const res = await fetch(url);
        const result = await res.json();
        const checklist = document.getElementById('permissionsChecklist');
        if (!checklist) return;
        checklist.innerHTML = '';
        checklist.className = 'permission-checklist';
        if (result.success && result.data && Array.isArray(result.data.permissions)) {
            // Use assigned permissions if present (edit mode)
            let assigned = selectedPermissions;
            if (Array.isArray(result.data.assigned)) {
                assigned = result.data.assigned;
            }
            result.data.permissions.forEach(perm => {
                const checked = assigned.includes(perm.permissionId) ? 'checked' : '';
                checklist.innerHTML += `<label class="permission-item"><input type="checkbox" value="${perm.permissionId}" ${checked}> ${perm.permissionName} <span style='color:#64748b;font-size:12px;'>${perm.description || ''}</span></label>`;
            });
        }
    }


function showRoleModal() {
    document.getElementById('roleModalTitle').textContent = 'Add Role';
    document.getElementById('roleForm').reset();
    loadPermissions([]);
    window._editingRoleId = null;
    document.getElementById('roleModal').style.display = 'block';
}

function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
}

async function submitRole() {
    const roleName = document.getElementById('roleName').value.trim();
    const description = document.getElementById('description').value.trim();
    const permissions = Array.from(document.querySelectorAll('#permissionsChecklist input[type="checkbox"]:checked')).map(cb => cb.value);
    if (!roleName) { alert('Role name required'); return; }
    const payload = {roleName, description, permissions};
    let method = 'POST';
    if (window._editingRoleId) {
        payload.roleId = window._editingRoleId;
        method = 'PUT';
    }
    const res = await fetch('api/roles.php', {
        method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
        alert(window._editingRoleId ? 'Role updated' : 'Role added');
        closeRoleModal();
        loadRoles();
    } else {
        alert('Error: ' + result.message);
    }
}

function showAssignRoleModal(accountId) {
    // Show modal to pick a role
    const modal = document.getElementById('assignRoleModal');
    const accountIdInput = document.getElementById('assignUserId');
    const select = document.getElementById('assignRoleSelect');
    if (!modal || !accountIdInput || !select) return;
    accountIdInput.value = accountId;
    select.innerHTML = '';
    (window._allRoles || []).forEach(role => {
        const opt = document.createElement('option');
        opt.value = role.roleId;
        opt.textContent = role.roleName;
        select.appendChild(opt);
    });
    modal.style.display = 'block';
}

function closeAssignRoleModal() {
    document.getElementById('assignRoleModal').style.display = 'none';
}

async function submitAssignRole() {
    const accountId = document.getElementById('assignUserId').value;
    const roleId = document.getElementById('assignRoleSelect').value;
    await assignRole(accountId, roleId);
    closeAssignRoleModal();
}

// Add editRole function
function editRole(roleId) {
    const role = (window._allRoles || []).find(r => r.roleId == roleId);
    if (!role) return alert('Role not found');
    const modalTitle = document.getElementById('roleModalTitle');
    const roleNameInput = document.getElementById('roleName');
    const descriptionInput = document.getElementById('description');
    const modal = document.getElementById('roleModal');
    if (!modalTitle || !roleNameInput || !descriptionInput || !modal) return;
    modalTitle.textContent = 'Edit Role';
    roleNameInput.value = role.roleName;
    descriptionInput.value = role.description || '';
    window._editingRoleId = roleId;
    // Load permissions for edit (fetch assigned from API)
    loadPermissions([], roleId);
    modal.style.display = 'block';
}

async function assignRole(accountId, roleId) {
    const res = await fetch('api/roles.php?action=assign', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({accountId, roleId}),
        credentials: 'include'
    });
    const result = await res.json();
    if (result.success) {
        alert('Role assigned');
        loadUsers();
    } else {
        alert('Error: ' + result.message);
    }
}

// Password Checklist Live Validation
function updatePasswordChecklist(inputId, checklistId) {
    const password = document.getElementById(inputId).value;
    const checklist = document.getElementById(checklistId);
    if (!checklist) return;
    checklist.querySelector('[data-type="lower"] i').className = /[a-z]/.test(password) ? 'fas fa-check' : 'fas fa-times';
    checklist.querySelector('[data-type="upper"] i').className = /[A-Z]/.test(password) ? 'fas fa-check' : 'fas fa-times';
    checklist.querySelector('[data-type="number"] i').className = /[0-9]/.test(password) ? 'fas fa-check' : 'fas fa-times';
}
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordChecklist('password', 'password-checklist');
        });
        updatePasswordChecklist('password', 'password-checklist');
    }
});
