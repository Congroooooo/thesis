<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../ADMIN/admin_page.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'ADMIN')) {
    header("Location: ../Pages/home.php");
    exit();
}
?>
<div class="header-section">
    <div class="container">
        <div class="header-flex">
            <div>
                <h1><i class="fas fa-users-cog"></i> Admin Dashboard</h1>
                <p class="mb-0">Manage user accounts and system administration</p>
            </div>
            <div class="header-right">
                <?php
                    if (session_status() === PHP_SESSION_NONE) { session_start(); }
                    $displayName = isset($_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['last_name']) ? $_SESSION['last_name'] : 'Admin');
                ?>
                <div class="welcome-text">Welcome, <strong><?php echo htmlspecialchars($displayName); ?></strong></div>
                <button class="btn logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container mt-0" style="margin-top: -10px;">
  <div class="admin-grid">
    <aside class="admin-sidebar">
      <div class="filters-card">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Filters & Search</h5>
        </div>
        <div class="card-body">
            <div class="form-group"><label>Search:</label>
              <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by name..." id="searchInput" pattern="[A-Za-z\s]*" title="Only letters and spaces are allowed">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
              </div>
            </div>
            <div class="form-group"><label>Role:</label>
              <select class="form-control" id="roleFilter" onchange="filterUsers()">
                <option value="all">All Roles</option>
                <option value="shs">SHS</option>
                <option value="college student">College Student</option>
                <option value="employee">Employee</option>
            </select>
        </div>
            <div class="form-group"><label>Program:</label>
              <select class="form-control" id="programFilter" onchange="filterUsers()">
                <option value="all">All Programs/Positions</option>
                <?php
                require_once '../Includes/connection.php';
                $stmt = $conn->query("SELECT COALESCE(NULLIF(TRIM(abbreviation), ''), name) AS abbr, category FROM programs_positions WHERE is_active = 1 ORDER BY abbr ASC");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $abbrMap = [];
                $seen = [];
                foreach ($rows as $row) {
                    $abbr = $row['abbr'];
                    if (!$abbr || isset($seen[$abbr])) { continue; }
                    $seen[$abbr] = true;
                    $abbrMap[$abbr] = strtolower($row['category']);
                    echo '<option value="' . htmlspecialchars($abbr) . '">' . htmlspecialchars($abbr) . '</option>';
                }
                echo '<script>window.abbrToCategory = ' . json_encode($abbrMap) . ';</script>';
                ?>
            </select>
        </div>
            <div class="form-group"><label>Status:</label>
              <select class="form-control" id="statusFilter" onchange="filterUsers()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
    </div>

      <div class="action-buttons-card mt-3">
        <div class="card-header">
            <h5><i class="fas fa-tools"></i> Actions</h5>
    </div>
        <div class="card-body">
            <button class="btn btn-primary action-btn" onclick="openAddAccountModal()">
        <i class="fas fa-plus"></i> Add Account
    </button>
            <button class="btn btn-success action-btn" onclick="window.location.href='manage_programs.php'">
                <i class="fas fa-graduation-cap"></i> Manage Programs
            </button>
            <button class="btn btn-secondary action-btn" onclick="changePassword()" id="changePasswordBtn" disabled>
        <i class="fas fa-key"></i> Change Password
    </button>
            <button class="btn btn-warning action-btn" onclick="updateStatus()" id="updateStatusBtn" disabled>
        <i class="fas fa-sync"></i> Update Status
    </button>
            <button class="btn btn-info action-btn" onclick="bulkUpdateStatus()" id="bulkUpdateStatusBtn" disabled>
        <i class="fas fa-users-cog"></i> Bulk Update Status
    </button>
</div>
        
        <div class="selection-status mt-2" id="selectionStatus" style="display: none;">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i>
                <span id="selectionStatusText">Single row selection active</span>
            </small>
        </div>
        
        <div class="status-restriction-info mt-2" id="statusRestrictionInfo" style="display: none;">
            <small class="text-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Only users with matching status can be selected together</span>
            </small>
        </div>
</div>
    </aside>

    <section class="admin-main">
      <div class="table-card">
        <div class="card-header">
            <h5><i class="fas fa-users"></i> User Accounts</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()"> Select All</th>
                <th onclick="sortTable(1)">First Name</th>
                <th onclick="sortTable(2)">Last Name</th>
                <th onclick="sortTable(3)">Birthday</th>
                <th onclick="sortTable(4)">ID Number</th>
                <th onclick="sortTable(5)">Role</th>
                <th onclick="sortTable(6)">Position/Program</th>
                <th onclick="sortTable(7)">Status</th>
            </tr>
        </thead>
        <tbody id="accountsTbody">
            <?php
            require_once '../Includes/connection.php';

            $sql = "SELECT a.*, 
                           COALESCE(NULLIF(pp.abbreviation, ''), a.program_or_position) AS program_abbr
                    FROM account a 
                    LEFT JOIN programs_positions pp ON a.program_or_position = pp.name";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($accounts as $account) {
                $identifier = $account['id_number'] ? $account['id_number'] : $account['email'];
                echo "<tr data-id='" . htmlspecialchars($identifier) . "' class='account-row'>";
                echo "<td><input type='checkbox' class='user-checkbox' value='" . htmlspecialchars($identifier) . "' onchange='handleCheckboxChange(this)'></td>";
                echo "<td>" . htmlspecialchars($account['first_name']) . "</td>";
                echo "<td>" . htmlspecialchars($account['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($account['birthday'] ? date('M d, Y', strtotime($account['birthday'])) : 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($account['id_number'] ?: 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($account['role_category']) . "</td>";
                $programText = htmlspecialchars($account['program_or_position']);
                $abbreviation = htmlspecialchars($account['program_abbr']);
                echo "<td class='has-tooltip' data-fulltext='".$programText."'>" . $abbreviation . "</td>";
                echo "<td>" . htmlspecialchars($account['status']) . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
                </div>
            </div>
        </div>
    </section>
  </div>
</div>

<div class="modal" id="updateStatusModal">
    <div class="modal-content">
        <h3>Update Status</h3>
        <form id="updateStatusForm">
            <input type="hidden" id="selectedUserId" name="userId">
            <div class="form-group">
                <label for="statusSelect">Select Status:</label>
                <select name="status" id="statusSelect" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update Status</button>
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('updateStatusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="bulkUpdateStatusModal">
    <div class="modal-content">
        <h3>Bulk Update Status</h3>
        <form id="bulkUpdateStatusForm">
            <div class="form-group">
                <label>Selected Users:</label>
                <div id="selectedUsersList" class="selected-users-list"></div>
            </div>
            <div class="form-group">
                <label for="bulkStatusSelect">Select New Status:</label>
                <select name="status" id="bulkStatusSelect" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update All Selected</button>
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('bulkUpdateStatusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="changePasswordModal">
    <div class="modal-content">
        <h3>Change Password</h3>
        <form id="changePasswordForm">
            <input type="hidden" id="changePasswordUserId" name="id">
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                <div class="error-feedback" id="newPasswordError"></div>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                <div class="error-feedback" id="confirmPasswordError"></div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Change Password</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="userTypeSelectionModal">
    <div class="modal-content" style="max-width:400px">
        <h3>Select Account Type</h3>
        <p>What type of user account do you want to create?</p>
        <div class="user-type-buttons">
            <button type="button" class="btn btn-primary user-type-btn" onclick="openStudentAccountModal()">
                <i class="fas fa-graduation-cap"></i> Student Account
            </button>
            <button type="button" class="btn btn-success user-type-btn" onclick="openEmployeeAccountModal()">
                <i class="fas fa-briefcase"></i> Employee Account
            </button>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal('userTypeSelectionModal')">Cancel</button>
        </div>
    </div>
</div>
            
<div class="modal" id="addStudentAccountModal">
    <div class="modal-content" style="max-width:700px">
        <h3>Add New Student Account</h3>
        <form id="addStudentAccountFormModal" class="add-account-form">
            <div class="form-group"><label>First Name<span class="text-danger" style="color: red;"> *</span></label>
                <input type="text" name="firstName" id="modalFirstName" class="form-control" required pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
            </div>
            <div class="form-group"><label>Last Name<span class="text-danger" style="color: red;"> *</span></label>
                <input type="text" name="lastName" id="modalLastName" class="form-control" required pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
            </div>
            <div class="form-group"><label>Extension Name</label>
                <input type="text" name="extensionName" id="modalExtensionName" class="form-control" maxlength="10" pattern="^[A-Za-z. \-]*$" title="Only letters, spaces, hyphen, and period (e.g., Jr., Sr., III) are allowed">
            </div>
            <div class="form-group"><label>Birthday<span class="text-danger" style="color: red;"> *</span></label>
                <input type="date" name="birthday" class="form-control" required>
            </div>
            <div class="form-group id-number-group">
                <label>ID Number<span class="text-danger" style="color: red;"> *</span></label>
                <div class="input-wrapper">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="background: #f8f9fa; border-right: 0; font-weight: 600; color: #495057;">02000</span>
                        </div>
                        <input type="text" name="idNumberSuffix" id="modalIdNumberSuffix" class="form-control" 
                               pattern="\d{6}" maxlength="6" placeholder="Enter last 6 digits" required
                               style="border-left: 0; padding-left: 0;">
                        <input type="hidden" name="idNumber" id="modalIdNumber">
                    </div>
                </div>
            </div>
            <div class="form-group"><label>Role Category<span class="text-danger" style="color: red;"> *</span></label>
                <select name="role_category" id="modalRoleCategory" class="form-control" required>
                    <option value="">Select Role Category</option>
                    <option value="SHS">SHS</option>
                    <option value="COLLEGE STUDENT">COLLEGE STUDENT</option>
                </select>
            </div>
            <div class="form-group"><label>Program/Position<span class="text-danger" style="color: red;"> *</span></label>
                <select name="program_position" id="modalProgramPosition" class="form-control" required>
                    <option value="">Select Program/Position</option>
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Create Student Account</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentAccountModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="addEmployeeAccountModal">
    <div class="modal-content" style="max-width:700px">
        <h3>Add New Employee Account</h3>
        <form id="addEmployeeAccountFormModal" class="add-account-form">
            <div class="form-group"><label>First Name<span class="text-danger" style="color: red;"> *</span></label>
                <input type="text" name="firstName" id="employeeModalFirstName" class="form-control" required pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
            </div>
            <div class="form-group"><label>Last Name<span class="text-danger" style="color: red;"> *</span></label>
                <input type="text" name="lastName" id="employeeModalLastName" class="form-control" required pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
            </div>
            <div class="form-group"><label>Extension Name</label>
                <input type="text" name="extensionName" id="employeeModalExtensionName" class="form-control" maxlength="10" pattern="^[A-Za-z. \-]*$" title="Only letters, spaces, hyphen, and period (e.g., Jr., Sr., III) are allowed">
            </div>
            <div class="form-group"><label>Birthday<span class="text-danger" style="color: red;"> *</span></label>
                <input type="date" name="birthday" class="form-control" required>
            </div>
            <div class="form-group"><label>Position<span class="text-danger" style="color: red;"> *</span></label>
                <select name="program_position" id="employeeModalPosition" class="form-control" required>
                    <option value="">Select Position</option>
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Create Employee Account</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addEmployeeAccountModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="successModal">
    <div class="modal-content">
        <div class="text-center">
            <i class="fas fa-check-circle success-icon"></i>
            <h4>Success!</h4>
            <p>Account status has been updated successfully.</p>
            <button class="btn btn-primary" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>
</div>

<script>
    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = '../logout.php';
        }
    }

    function filterUsers() {
        const roleFilter = document.getElementById('roleFilter').value.toLowerCase();
        const programFilter = document.getElementById('programFilter').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const tbody = document.getElementById('accountsTbody');
        const dataRows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.id !== 'emptyStateRow');

        dataRows.forEach(row => {
            const getText = (idx) => (row.cells[idx] ? row.cells[idx].textContent.toLowerCase() : '');
            const firstName = getText(1);
            const lastName = getText(2);
            const role = getText(5);
            const program = getText(6);
            const status = getText(7);

            const matchesProgram = programFilter === 'all' || program === programFilter;
            const matchesRole = roleFilter === 'all' || role === roleFilter;
            const matchesStatus = statusFilter === 'all' || status === statusFilter;

            const matchesSearch = (() => {
                if (!searchTerm.trim()) return true;
                
                const searchWords = searchTerm.trim().split(/\s+/); 
                
                if (searchWords.length === 1) {
                    const singleWord = searchWords[0];
                    return firstName.includes(singleWord) || lastName.includes(singleWord);
                } else if (searchWords.length >= 2) {
                    const fullName = `${firstName} ${lastName}`.toLowerCase();
                    const reverseName = `${lastName} ${firstName}`.toLowerCase();

                    const allWordsInFullName = searchWords.every(word => fullName.includes(word));
                    const allWordsInReverseName = searchWords.every(word => reverseName.includes(word));

                    const phraseMatch = fullName.includes(searchTerm) || reverseName.includes(searchTerm);
                    
                    return allWordsInFullName || allWordsInReverseName || phraseMatch;
                }
                
                return false;
            })();

            row.style.display = (matchesRole && matchesProgram && matchesStatus && matchesSearch) ? '' : 'none';
        });

        const anyVisible = dataRows.some(r => r.style.display !== 'none');
        let emptyRow = document.getElementById('emptyStateRow');
        if (!anyVisible) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'emptyStateRow';
                const td = document.createElement('td');
                const thCount = (document.querySelector('.table thead')?.querySelectorAll('th').length) || 7;
                td.colSpan = thCount;
                td.style.textAlign = 'center';
                td.style.padding = '24px';
                td.textContent = 'No records match the current filters.';
                emptyRow.appendChild(td);
                tbody.appendChild(emptyRow);
            }
        } else if (emptyRow) {
            emptyRow.remove();
        }

        if (selectedUserIds.length > 0) {
            updateBulkButtons();
        } else {
            firstSelectedStatus = null;
            setCheckboxesEnabledByStatus(null);
            updateBulkButtons();
        }

        if (selectedUserId) {
            const selectedRow = document.querySelector(`tr[data-id="${selectedUserId}"]`);
            if (selectedRow && selectedRow.style.display !== 'none') {
                selectedRow.classList.add('selected');
                setBulkCheckboxesEnabled(false);
            }
        }
    }

    function sortTable(columnIndex) {
        const table = document.querySelector('.table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.id !== 'emptyStateRow');
        const currentOrder = tbody.dataset.order || 'asc';

        rows.sort((a, b) => {
            const aValue = a.cells[columnIndex] ? a.cells[columnIndex].textContent.toLowerCase() : '';
            const bValue = b.cells[columnIndex] ? b.cells[columnIndex].textContent.toLowerCase() : '';

            if (currentOrder === 'asc') {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });

        tbody.dataset.order = currentOrder === 'asc' ? 'desc' : 'asc';

        const emptyRow = document.getElementById('emptyStateRow');
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        if (emptyRow) tbody.appendChild(emptyRow);

        if (selectedUserIds.length > 0 && firstSelectedStatus) {
            setCheckboxesEnabledByStatus(firstSelectedStatus);
        }

        updateBulkButtons();

        if (selectedUserId) {
            const selectedRow = document.querySelector(`tr[data-id="${selectedUserId}"]`);
            if (selectedRow) {
                selectedRow.classList.add('selected');
                setBulkCheckboxesEnabled(false);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        filterUsers();
        updateBulkButtons();

        document.getElementById('searchInput').addEventListener('input', filterUsers);
            document.getElementById('searchInput').addEventListener('input', function(){
                this.value = this.value.replace(/[^A-Za-z\s]/g, '');
            });
            const roleSel = document.getElementById('roleFilter');
            const programSel = document.getElementById('programFilter');

            roleSel.addEventListener('change', function(){
                const roleVal = this.value.toLowerCase();
                const current = programSel.value;
                Array.from(programSel.options).forEach((opt, idx) => {
                    if (idx === 0) return;
                    const abbr = opt.value;
                    const category = (window.abbrToCategory && window.abbrToCategory[abbr]) ? window.abbrToCategory[abbr].toLowerCase() : '';
                    const visible = roleVal === 'all' || (category && category === roleVal);
                    opt.hidden = !visible;
                });
                const currentCategory = window.abbrToCategory && window.abbrToCategory[current] ? window.abbrToCategory[current].toLowerCase() : '';
                if (roleVal !== 'all' && current && currentCategory !== roleVal) {
                    programSel.value = 'all';
                }
                filterUsers();
            });

            programSel.addEventListener('change', function(){
                const abbr = this.value;
                if (abbr !== 'all') {
                    const cat = window.abbrToCategory && window.abbrToCategory[abbr] ? window.abbrToCategory[abbr].toLowerCase() : '';
                    if (cat) {
                        roleSel.value = cat;
                        roleSel.disabled = true;
                    }
                } else {
                    roleSel.disabled = false;
                }
                filterUsers();
            });

        document.getElementById('roleFilter').addEventListener('change', filterUsers);
        document.getElementById('programFilter').addEventListener('change', filterUsers);
        document.getElementById('statusFilter').addEventListener('change', filterUsers);
        
        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function(e){
                e.preventDefault();
                ['currentPasswordError','newPasswordError','confirmPasswordError'].forEach(id=>{
                    const el = document.getElementById(id);
                    if (el) el.textContent = '';
                });
                const formData = new FormData(changePasswordForm);
                fetch('change_password.php', { method: 'POST', body: formData })
                    .then(r=>r.json())
                    .then(data=>{
                        if (data.success) {
                            const toast = document.createElement('div');
                            toast.textContent = 'Password successfully updated';
                            toast.style.position = 'fixed';
                            toast.style.top = '16px';
                            toast.style.right = '16px';
                            toast.style.background = '#28a745';
                            toast.style.color = '#fff';
                            toast.style.padding = '10px 14px';
                            toast.style.borderRadius = '6px';
                            toast.style.zIndex = '10000';
                            document.body.appendChild(toast);
                            setTimeout(()=>toast.remove(), 2000);
                            closeModal('changePasswordModal');
                            changePasswordForm.reset();
                        } else if (data.messages) {
                            if (data.messages.newPassword) document.getElementById('newPasswordError').textContent = data.messages.newPassword;
                            if (data.messages.confirmPassword) document.getElementById('confirmPasswordError').textContent = data.messages.confirmPassword;
                        }
                    })
                    .catch(err=>console.error(err));
            });
        }

        const modalRole = document.getElementById('modalRoleCategory');
        const modalProgram = document.getElementById('modalProgramPosition');
        if (modalRole && modalProgram) {
            modalRole.addEventListener('change', async function(){
                modalProgram.innerHTML = '<option value="">Select Program/Position</option>';
                const role = this.value;
                if (!role) return;
                try {
                    const resp = await fetch(`get_programs.php?category=${encodeURIComponent(role)}`);
                    if (!resp.ok) throw new Error('HTTP '+resp.status);
                    const programs = await resp.json();
                    if (Array.isArray(programs)) {
                        programs.forEach(p=>{
                            const opt = document.createElement('option');
                            opt.value = p.abbreviation || p.name;
                            opt.textContent = p.name;
                            modalProgram.appendChild(opt);
                        });
                    }
                } catch(e) { /* silent */ }
            });
            const onlyLetters = (el)=> el && el.addEventListener('input', ()=>{ el.value = el.value.replace(/[^A-Za-z\s]/g,''); });
            const onlySuffixChars = (el)=> el && el.addEventListener('input', ()=>{ el.value = el.value.replace(/[^A-Za-z.\s-]/g,''); });
            const onlyDigits = (el)=> el && el.addEventListener('input', ()=>{ el.value = el.value.replace(/\D/g,'').slice(0,11); });
            const onlyDigitsSuffix = (el, maxLength)=> el && el.addEventListener('input', ()=>{ 
                el.value = el.value.replace(/\D/g,'').slice(0, maxLength);
                
                const fullIdInput = document.getElementById('modalIdNumber');
                if (fullIdInput) {
                    fullIdInput.value = '02000' + el.value;
                }
            });
            const capitalizeNames = (el)=> el && el.addEventListener('input', ()=>{ 
                const words = el.value.split(' ');
                el.value = words.map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
            });
            onlyLetters(document.getElementById('modalFirstName'));
            onlyLetters(document.getElementById('modalLastName'));
            onlySuffixChars(document.getElementById('modalExtensionName'));
            onlyDigitsSuffix(document.getElementById('modalIdNumberSuffix'), 6);

            capitalizeNames(document.getElementById('modalFirstName'));
            capitalizeNames(document.getElementById('modalLastName'));
            capitalizeNames(document.getElementById('modalExtensionName'));

            onlyLetters(document.getElementById('employeeModalFirstName'));
            onlyLetters(document.getElementById('employeeModalLastName'));
            onlySuffixChars(document.getElementById('employeeModalExtensionName'));

            capitalizeNames(document.getElementById('employeeModalFirstName'));
            capitalizeNames(document.getElementById('employeeModalLastName'));
            capitalizeNames(document.getElementById('employeeModalExtensionName'));
        }

        const addStudentForm = document.getElementById('addStudentAccountFormModal');
        if (addStudentForm) {
            addStudentForm.addEventListener('submit', function(e){
                e.preventDefault();

                const suffixInput = document.getElementById('modalIdNumberSuffix');
                const fullIdInput = document.getElementById('modalIdNumber');
                if (suffixInput && fullIdInput) {
                    const suffix = suffixInput.value.trim();
                    if (suffix.length !== 6) {
                        alert('Please enter exactly 6 digits for the ID number suffix.');
                        suffixInput.focus();
                        return;
                    }
                    fullIdInput.value = '02000' + suffix;
                }
                
                const formData = new FormData(addStudentForm);
                fetch('add_account.php', { method: 'POST', body: formData })
                    .then(async (r) => {
                        let data = null;
                        try { data = await r.json(); } catch (e) { /* no body or not JSON */ }
                        if (!r.ok || !data || data.success === false) {
                            const msg = (data && data.message) ? data.message : `Request failed (${r.status})`;
                            throw new Error(msg);
                        }
                        return data;
                    })
                    .then((data)=>{
                        closeModal('addStudentAccountModal');
                        addStudentForm.reset();

                        const suffixInput = document.getElementById('modalIdNumberSuffix');
                        const fullIdInput = document.getElementById('modalIdNumber');
                        if (suffixInput && fullIdInput) {
                            suffixInput.value = '';
                            fullIdInput.value = '02000';
                        }

                        const modalHtml = `
                        <div class="modal" id="createSuccessModal" style="display:flex; align-items:center; justify-content:center;">
                          <div class="modal-content" style="max-width:520px">
                            <div class="text-center">
                              <i class="fas fa-check-circle success-icon"></i>
                              <h4>Account Created Successfully!</h4>
                              <p>The account has been created with the following credentials:</p>
                              <div class="credentials-display">
                                <div class="credential-item"><label>Email:</label><strong>${data.generated_email}</strong></div>
                                <div class="credential-item"><label>Password:</label><strong>${data.generated_password}</strong></div>
                              </div>
                              <button class="btn btn-primary mt-3" id="createStudentSuccessOk">OK</button>
                            </div>
                          </div>
                        </div>`;
                        document.body.insertAdjacentHTML('beforeend', modalHtml);
                        document.getElementById('createStudentSuccessOk').addEventListener('click', ()=>{
                            try {
                                const tbody = document.getElementById('accountsTbody');
                                const tr = document.createElement('tr');
                                tr.className = 'account-row';
                                tr.dataset.id = (data.id_number || '');
                                const role = data.role_category || '';
                                const program = data.program_or_position || '';
                                const status = 'active';
                                const fmtBirthday = (iso)=>{
                                    if (!iso) return 'N/A';
                                    const parts = String(iso).split('-');
                                    if (parts.length !== 3) return 'N/A';
                                    const [y,m,d] = parts.map(p=>parseInt(p,10));
                                    if (!y || !m || !d) return 'N/A';
                                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                    return `${months[m-1]} ${String(d).padStart(2,'0')}, ${y}`;
                                };
                                tr.innerHTML = `
                                    <td><input type="checkbox" class="user-checkbox" value="${data.id_number || ''}" onchange="handleCheckboxChange(this)"></td>
                                    <td>${data.first_name || ''}</td>
                                    <td>${data.last_name || ''}</td>
                                    <td>${fmtBirthday(data.birthday)}</td>
                                    <td>${data.id_number || ''}</td>
                                    <td>${role}</td>
                                    <td class="has-tooltip" data-fulltext="${program}">${program}</td>
                                    <td>${status}</td>
                                `;
                                if (tbody) tbody.prepend(tr);
                                tr.addEventListener('click', function(e){
                                    if (e.target.type === 'checkbox') {
                                        return;
                                    }

                                    if (this.classList.contains('row-selection-disabled')) {
                                        return;
                                    }
                                    
                                    const isSelected = this.classList.contains('selected');
                                    document.querySelectorAll('.account-row').forEach(r => r.classList.remove('selected'));
                                    if (isSelected) {
                                        this.classList.remove('selected');
                                        selectedUserId = null;
                                        document.getElementById('changePasswordBtn').disabled = true;
                                        document.getElementById('updateStatusBtn').disabled = true;

                                        setBulkCheckboxesEnabled(true);
                                        updateSelectionStatus('row', 0);
                                    } else {
                                        this.classList.add('selected');
                                        selectedUserId = this.dataset.id;
                                        document.getElementById('changePasswordBtn').disabled = false;
                                        document.getElementById('updateStatusBtn').disabled = false;

                                        setBulkCheckboxesEnabled(false);
                                        updateSelectionStatus('row', 1);
                                    }
                                });
                                filterUsers();
                            } catch(e) { /* ignore */ }
                            const m = document.getElementById('createSuccessModal');
                            if (m) m.remove();
                            closeModal('addStudentAccountModal');
                        });
                    })
                    .catch((err)=>{
                        const toast = document.createElement('div');
                        toast.textContent = (err && err.message) ? err.message : 'Error creating account';
                        toast.style.position = 'fixed';
                        toast.style.top = '16px';
                        toast.style.right = '16px';
                        toast.style.background = '#dc3545';
                        toast.style.color = '#fff';
                        toast.style.padding = '10px 14px';
                        toast.style.borderRadius = '6px';
                        toast.style.zIndex = '10000';
                        document.body.appendChild(toast);
                        setTimeout(()=>toast.remove(), 2500);
                    });
            });
        }

        const addEmployeeForm = document.getElementById('addEmployeeAccountFormModal');
        if (addEmployeeForm) {
            addEmployeeForm.addEventListener('submit', function(e){
                e.preventDefault();
                const formData = new FormData(addEmployeeForm);
                fetch('add_employee_account.php', { method: 'POST', body: formData })
                    .then(async (r) => {
                        let data = null;
                        try { data = await r.json(); } catch (e) { /* no body or not JSON */ }
                        if (!r.ok || !data || data.success === false) {
                            const msg = (data && data.message) ? data.message : `Request failed (${r.status})`;
                            const toast = document.createElement('div');
                            toast.textContent = msg;
                            toast.style.position = 'fixed';
                            toast.style.top = '16px';
                            toast.style.right = '16px';
                            toast.style.background = '#dc3545';
                            toast.style.color = '#fff';
                            toast.style.padding = '10px 14px';
                            toast.style.borderRadius = '6px';
                            toast.style.zIndex = '10000';
                            document.body.appendChild(toast);
                            setTimeout(()=>toast.remove(), 2500);
                            return;
                        }
                        
                        const modalHtml = `
                        <div class="modal" id="createSuccessModal" style="display: flex;">
                          <div class="modal-content" style="max-width: 500px;">
                            <div class="text-center">
                              <i class="fas fa-check-circle" style="font-size: 50px; color: #28a745; margin-bottom: 15px;"></i>
                              <h4>Employee Account Created Successfully!</h4>
                              <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: left;">
                                <strong>Account Details:</strong><br>
                                <strong>Name:</strong> ${data.first_name || ''} ${data.last_name || ''}<br>
                                <strong>Email:</strong> ${data.generated_email || ''}<br>
                                <strong>Password:</strong> ${data.generated_password || ''}<br>
                                <strong>Position:</strong> ${data.program_or_position || ''}
                              </div>
                              <button class="btn btn-primary mt-3" id="createEmployeeSuccessOk">OK</button>
                            </div>
                          </div>
                        </div>`;
                        document.body.insertAdjacentHTML('beforeend', modalHtml);
                        document.getElementById('createEmployeeSuccessOk').addEventListener('click', ()=>{
                            try {
                                const tbody = document.getElementById('accountsTbody');
                                const tr = document.createElement('tr');
                                tr.className = 'account-row';
                                tr.dataset.id = (data.generated_email || '');
                                const role = 'EMPLOYEE';
                                const position = data.program_or_position || '';
                                const status = 'active';
                                const fmtBirthday = (iso)=>{
                                    if (!iso) return 'N/A';
                                    const parts = String(iso).split('-');
                                    if (parts.length !== 3) return 'N/A';
                                    const [y,m,d] = parts.map(p=>parseInt(p,10));
                                    if (!y || !m || !d) return 'N/A';
                                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                    return `${months[m-1]} ${String(d).padStart(2,'0')}, ${y}`;
                                };
                                tr.innerHTML = `
                                    <td><input type="checkbox" class="user-checkbox" value="${data.generated_email || ''}" onchange="handleCheckboxChange(this)"></td>
                                    <td>${data.first_name || ''}</td>
                                    <td>${data.last_name || ''}</td>
                                    <td>${fmtBirthday(data.birthday)}</td>
                                    <td>N/A</td>
                                    <td>${role}</td>
                                    <td class="has-tooltip" data-fulltext="${position}">${position}</td>
                                    <td>${status}</td>
                                `;
                                if (tbody) tbody.prepend(tr);
                                tr.addEventListener('click', function(e){
                                    if (e.target.type === 'checkbox') {
                                        return;
                                    }

                                    if (this.classList.contains('row-selection-disabled')) {
                                        return;
                                    }
                                    
                                    const isSelected = this.classList.contains('selected');
                                    document.querySelectorAll('.account-row').forEach(r => r.classList.remove('selected'));
                                    if (isSelected) {
                                        this.classList.remove('selected');
                                        selectedUserId = null;
                                        document.getElementById('changePasswordBtn').disabled = true;
                                        document.getElementById('updateStatusBtn').disabled = true;
                                        setBulkCheckboxesEnabled(true);
                                        updateSelectionStatus('row', 0);
                                    } else {
                                        this.classList.add('selected');
                                        selectedUserId = this.dataset.id;
                                        document.getElementById('changePasswordBtn').disabled = false;
                                        document.getElementById('updateStatusBtn').disabled = false;
                                        setBulkCheckboxesEnabled(false);
                                        updateSelectionStatus('row', 1);
                                    }
                                });
                                filterUsers();
                            } catch(e) { /* ignore */ }
                            const m = document.getElementById('createSuccessModal');
                            if (m) m.remove();
                            closeModal('addEmployeeAccountModal');
                        });
                    })
                    .catch((err)=>{
                        const toast = document.createElement('div');
                        toast.textContent = (err && err.message) ? err.message : 'Error creating employee account';
                        toast.style.position = 'fixed';
                        toast.style.top = '16px';
                        toast.style.right = '16px';
                        toast.style.background = '#dc3545';
                        toast.style.color = '#fff';
                        toast.style.padding = '10px 14px';
                        toast.style.borderRadius = '6px';
                        toast.style.zIndex = '10000';
                        document.body.appendChild(toast);
                        setTimeout(()=>toast.remove(), 2500);
                    });
            });
        }
    });

    let selectedUserId = null;
    let selectedUserIds = [];
    let firstSelectedStatus = null;

    function getUserStatus(row) {
        const statusCell = row.querySelector('td:last-child');
        return statusCell ? statusCell.textContent.trim().toLowerCase() : null;
    }

    function handleCheckboxChange(checkbox) {
        updateBulkButtons();
    }

    function setCheckboxesEnabledByStatus(targetStatus) {
        const rows = document.querySelectorAll('.account-row');
        const restrictionInfo = document.getElementById('statusRestrictionInfo');
        
        rows.forEach(row => {
            if (row.style.display === 'none' || row.id === 'emptyStateRow') return;
            
            const checkbox = row.querySelector('.user-checkbox');
            if (!checkbox) return;
            
            const rowStatus = getUserStatus(row);
            
            if (targetStatus === null) {
                checkbox.disabled = false;
                row.classList.remove('status-disabled');
                checkbox.title = '';
            } else if (rowStatus === targetStatus) {
                checkbox.disabled = false;
                row.classList.remove('status-disabled');
                checkbox.title = '';
            } else {
                checkbox.disabled = true;
                checkbox.checked = false;
                row.classList.add('status-disabled');
                checkbox.title = `Cannot select - different status (${rowStatus} vs ${targetStatus})`;
            }
        });

        if (restrictionInfo) {
            restrictionInfo.style.display = targetStatus ? 'block' : 'none';
        }
    }

    function setBulkCheckboxesEnabled(enabled) {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        
        if (enabled) {
            firstSelectedStatus = null;
            setCheckboxesEnabledByStatus(null);
        } else {
            checkboxes.forEach(checkbox => {
                checkbox.disabled = true;
            });
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.disabled = !enabled;
        }

        if (!enabled) {
            checkboxes.forEach(checkbox => checkbox.checked = false);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
            selectedUserIds = [];
            firstSelectedStatus = null;
            const bulkUpdateBtn = document.getElementById('bulkUpdateStatusBtn');
            if (bulkUpdateBtn) bulkUpdateBtn.disabled = true;
        }
    }

    function setRowSelectionEnabled(enabled) {
        const rows = document.querySelectorAll('.account-row');
        
        rows.forEach(row => {
            if (enabled) {
                row.classList.remove('row-selection-disabled');
                row.style.cursor = '';
            } else {
                row.classList.add('row-selection-disabled');
                row.style.cursor = 'default';
                row.classList.remove('selected');
            }
        });

        if (!enabled) {
            selectedUserId = null;
            document.getElementById('changePasswordBtn').disabled = true;
            document.getElementById('updateStatusBtn').disabled = true;
        }
    }

    function updateSelectionStatus(mode, count, status = null) {
        const statusDiv = document.getElementById('selectionStatus');
        const statusText = document.getElementById('selectionStatusText');
        
        if (!statusDiv || !statusText) return;
        
        if (mode === 'bulk' && count > 0) {
            statusDiv.style.display = 'block';
            const statusInfo = status ? ` (${status.charAt(0).toUpperCase() + status.slice(1)} users only)` : '';
            statusText.innerHTML = `<i class="fas fa-check-square"></i> Bulk selection active (${count} user${count !== 1 ? 's' : ''} selected)${statusInfo}`;
            statusDiv.className = 'selection-status mt-2 text-info';
        } else if (mode === 'row' && count > 0) {
            statusDiv.style.display = 'block';
            statusText.innerHTML = `<i class="fas fa-mouse-pointer"></i> Single row selection active`;
            statusDiv.className = 'selection-status mt-2 text-success';
        } else {
            statusDiv.style.display = 'none';
        }
    }

    function hasMixedStatuses() {
        const visibleRows = Array.from(document.querySelectorAll('.account-row')).filter(row => 
            row.style.display !== 'none' && row.id !== 'emptyStateRow'
        );
        
        const statuses = new Set();
        visibleRows.forEach(row => {
            const status = getUserStatus(row);
            if (status) statuses.add(status);
        });
        
        return statuses.size > 1;
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');

        if (selectAllCheckbox.checked && hasMixedStatuses()) {
            selectAllCheckbox.checked = false;
            
            const toast = document.createElement('div');
            toast.textContent = 'Cannot select all users with different statuses. Please filter by status first.';
            toast.style.position = 'fixed';
            toast.style.top = '16px';
            toast.style.right = '16px';
            toast.style.background = '#ffc107';
            toast.style.color = '#000';
            toast.style.padding = '12px 16px';
            toast.style.borderRadius = '6px';
            toast.style.zIndex = '10000';
            toast.style.maxWidth = '300px';
            toast.style.fontSize = '14px';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
            return;
        }
        
        const visibleRows = Array.from(document.querySelectorAll('.account-row')).filter(row => 
            row.style.display !== 'none' && row.id !== 'emptyStateRow'
        );
        
        visibleRows.forEach(row => {
            const checkbox = row.querySelector('.user-checkbox');
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = selectAllCheckbox.checked;
            }
        });
        
        updateBulkButtons();
    }

    function updateBulkButtons() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        selectedUserIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        const bulkUpdateBtn = document.getElementById('bulkUpdateStatusBtn');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');

        if (selectedUserIds.length > 0) {
            if (firstSelectedStatus === null && checkedBoxes.length > 0) {
                const firstCheckedBox = checkedBoxes[0];
                const firstRow = firstCheckedBox.closest('.account-row');
                firstSelectedStatus = getUserStatus(firstRow);
                setCheckboxesEnabledByStatus(firstSelectedStatus);
                setRowSelectionEnabled(false);
            }
        } else {
            firstSelectedStatus = null;
            setCheckboxesEnabledByStatus(null);
            setRowSelectionEnabled(true);
        }
        const visibleCheckboxes = Array.from(document.querySelectorAll('.account-row')).filter(row => 
            row.style.display !== 'none' && row.id !== 'emptyStateRow'
        ).map(row => row.querySelector('.user-checkbox')).filter(cb => cb && !cb.disabled);
        
        const visibleCheckedBoxes = visibleCheckboxes.filter(cb => cb.checked);

        bulkUpdateBtn.disabled = selectedUserIds.length === 0;

        if (selectedUserIds.length > 0) {
            updateSelectionStatus('bulk', selectedUserIds.length, firstSelectedStatus);
        } else {
            updateSelectionStatus('row', selectedUserId ? 1 : 0);
        }

        if (visibleCheckedBoxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (visibleCheckedBoxes.length === visibleCheckboxes.length && visibleCheckboxes.length > 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    function bulkUpdateStatus() {
        if (selectedUserIds.length === 0) {
            alert('Please select at least one user.');
            return;
        }

        const selectedUsersList = document.getElementById('selectedUsersList');
        selectedUsersList.innerHTML = '';
        
        selectedUserIds.forEach(userId => {
            const row = document.querySelector(`tr[data-id="${userId}"]`);
            if (row) {
                const firstName = row.children[1].textContent;
                const lastName = row.children[2].textContent;
                const userDiv = document.createElement('div');
                userDiv.className = 'selected-user-item';
                userDiv.textContent = `${firstName} ${lastName} (${userId})`;
                selectedUsersList.appendChild(userDiv);
            }
        });

        const modal = document.getElementById('bulkUpdateStatusModal');
        modal.style.display = 'flex';
    }

    document.querySelectorAll('.account-row').forEach(row => {
        row.addEventListener('click', function (e) {
            if (e.target.type === 'checkbox') {
                return;
            }

            if (this.classList.contains('row-selection-disabled')) {
                return;
            }
            
            const isSelected = this.classList.contains('selected');

            document.querySelectorAll('.account-row').forEach(r => r.classList.remove('selected'));

            if (isSelected) {
                this.classList.remove('selected');
                selectedUserId = null;
                document.getElementById('changePasswordBtn').disabled = true;
                document.getElementById('updateStatusBtn').disabled = true;
                setBulkCheckboxesEnabled(true);
                updateSelectionStatus('row', 0);
            } else {
                this.classList.add('selected');
                selectedUserId = this.dataset.id;
                document.getElementById('changePasswordBtn').disabled = false;
                document.getElementById('updateStatusBtn').disabled = false;
                setBulkCheckboxesEnabled(false);
                updateSelectionStatus('row', 1);
            }
        });
    });

    function changePassword() {
        if (!selectedUserId) {
            alert('Please select an account first.');
            return;
        }
        document.getElementById('changePasswordUserId').value = selectedUserId;
        const modal = document.getElementById('changePasswordModal');
        modal.style.display = 'flex';
    }

    function updateStatus() {
        if (!selectedUserId) {
            alert('Please select an account first.');
            return;
        }
        document.getElementById('selectedUserId').value = selectedUserId;
        try {
            const row = document.querySelector(`tr[data-id="${selectedUserId}"]`);
            const statusCell = row ? row.querySelector('td:last-child') : null;
            const currentStatus = statusCell ? statusCell.textContent.trim().toLowerCase() : '';
            const select = document.getElementById('statusSelect');
            if (select && (currentStatus === 'active' || currentStatus === 'inactive')) {
                select.value = currentStatus;
            }
        } catch (e) { /* noop */ }

        const modal = document.getElementById('updateStatusModal');
        modal.style.display = 'flex';
    }

    function setBirthdayMax() {
        try {
            const studentBday = document.querySelector('#addStudentAccountModal input[name="birthday"]');
            const employeeBday = document.querySelector('#addEmployeeAccountModal input[name="birthday"]');
            const bdays = [studentBday, employeeBday].filter(Boolean);
            const today = new Date();
            const maxDate = new Date(today.getFullYear() - 16, today.getMonth(), today.getDate());
            const yyyy = maxDate.getFullYear();
            const mm = String(maxDate.getMonth() + 1).padStart(2, '0');
            const dd = String(maxDate.getDate()).padStart(2, '0');
            const maxStr = `${yyyy}-${mm}-${dd}`;
            
            bdays.forEach(bday => {
                if (!bday) return;
                bday.max = maxStr;

                const validate = () => {
                    if (bday.value && bday.value > maxStr) {
                        bday.setCustomValidity('You must be at least 16 years old.');
                    } else {
                        bday.setCustomValidity('');
                    }
                };
                if (!bday._ageBound) {
                    bday.addEventListener('input', validate);
                    bday.addEventListener('change', validate);
                    bday._ageBound = true;
                }
                validate();
            });
        } catch (e) { /* noop */ }
    }

    function openAddAccountModal(){
        const modal = document.getElementById('userTypeSelectionModal');
        modal.style.display = 'flex';
    }

    function openStudentAccountModal(){
        closeModal('userTypeSelectionModal');

        const f = document.getElementById('addStudentAccountFormModal');
        if (f) f.reset();
        const prog = document.getElementById('modalProgramPosition');
        if (prog) prog.innerHTML = '<option value="">Select Program/Position</option>';
        setBirthdayMax();

        const suffixInput = document.getElementById('modalIdNumberSuffix');
        const fullIdInput = document.getElementById('modalIdNumber');
        if (suffixInput && fullIdInput) {
            suffixInput.value = '';
            fullIdInput.value = '02000';
            suffixInput.focus();
        }

        loadProgramsForCategory(['shs', 'college student'], 'modalProgramPosition');
        
        const modal = document.getElementById('addStudentAccountModal');
        modal.style.display = 'flex';
    }

    function openEmployeeAccountModal(){
        closeModal('userTypeSelectionModal');

        const f = document.getElementById('addEmployeeAccountFormModal');
        if (f) f.reset();
        const pos = document.getElementById('employeeModalPosition');
        if (pos) pos.innerHTML = '<option value="">Select Position</option>';
        setBirthdayMax();

        loadProgramsForCategory(['employee'], 'employeeModalPosition');
        
        const modal = document.getElementById('addEmployeeAccountModal');
        modal.style.display = 'flex';
    }

    async function loadProgramsForCategory(categories, selectElementId) {
        const selectElement = document.getElementById(selectElementId);
        if (!selectElement) return;
        
        selectElement.innerHTML = '<option value="">Loading...</option>';
        
        try {
            const responses = await Promise.all(
                categories.map(cat => fetch(`get_programs.php?category=${encodeURIComponent(cat)}`))
            );
            
            let allPrograms = [];
            for (const resp of responses) {
                if (resp.ok) {
                    const programs = await resp.json();
                    if (Array.isArray(programs)) {
                        allPrograms = allPrograms.concat(programs);
                    }
                }
            }

            selectElement.innerHTML = selectElementId.includes('employee') ? 
                '<option value="">Select Position</option>' : 
                '<option value="">Select Program/Position</option>';
                
            allPrograms.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.abbreviation || p.name;
                opt.textContent = p.name;
                selectElement.appendChild(opt);
            });
        } catch(e) {
            selectElement.innerHTML = selectElementId.includes('employee') ? 
                '<option value="">Select Position</option>' : 
                '<option value="">Select Program/Position</option>';
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function closeSuccessModal() {
        document.getElementById('successModal').style.display = 'none';
    }

    document.getElementById('updateStatusForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);

       try {
           const response = await fetch('update_status.php', {
            method: 'POST',
            body: formData
           });

           const data = await response.json();

           if (!response.ok || !data || data.success === false) {
               const msg = (data && data.message) ? data.message : 'Error updating status. Please try again.';
               const toast = document.createElement('div');
               toast.textContent = msg;
               toast.style.position = 'fixed';
               toast.style.top = '16px';
               toast.style.right = '16px';
               toast.style.background = '#dc3545';
               toast.style.color = '#fff';
               toast.style.padding = '10px 14px';
               toast.style.borderRadius = '6px';
               toast.style.zIndex = '10000';
               document.body.appendChild(toast);
               setTimeout(() => toast.remove(), 2500);
               return;
           }

                    const row = document.querySelector(`tr[data-id="${formData.get('userId')}"]`);
                    if (row) {
               const statusCell = row.querySelector('td:last-child');
               if (statusCell) {
                   statusCell.textContent = formData.get('status');
                   
                   filterUsers();
                   
                   closeModal('updateStatusModal');
                   const modal = document.getElementById('successModal');
                    if (modal) {
                        modal.querySelector('p').textContent = 'Status updated successfully';
                        modal.style.display = 'flex';
                    }

                    selectedUserId = null;
                    document.querySelectorAll('.account-row').forEach(r => r.classList.remove('selected'));
                    document.getElementById('changePasswordBtn').disabled = true;
                    document.getElementById('updateStatusBtn').disabled = true;
                }
           }
       } catch (error) {
                console.error('Error:', error);
           const toast = document.createElement('div');
           toast.textContent = 'Error updating status. Please try again.';
           toast.style.position = 'fixed';
           toast.style.top = '16px';
           toast.style.right = '16px';
           toast.style.background = '#dc3545';
           toast.style.color = '#fff';
           toast.style.padding = '10px 14px';
           toast.style.borderRadius = '6px';
           toast.style.zIndex = '10000';
           document.body.appendChild(toast);
           setTimeout(() => toast.remove(), 2500);
       }
    });

    document.getElementById('bulkUpdateStatusForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        
        if (selectedUserIds.length === 0) {
            alert('No users selected.');
            return;
        }
        
        const status = document.getElementById('bulkStatusSelect').value;
        
        try {
            const response = await fetch('bulk_update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userIds: selectedUserIds,
                    status: status
                })
            });

            const data = await response.json();

            if (!response.ok || !data || data.success === false) {
                const msg = (data && data.message) ? data.message : 'Error updating status. Please try again.';
                const toast = document.createElement('div');
                toast.textContent = msg;
                toast.style.position = 'fixed';
                toast.style.top = '16px';
                toast.style.right = '16px';
                toast.style.background = '#dc3545';
                toast.style.color = '#fff';
                toast.style.padding = '10px 14px';
                toast.style.borderRadius = '6px';
                toast.style.zIndex = '10000';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2500);
                return;
            }

            selectedUserIds.forEach(userId => {
                const row = document.querySelector(`tr[data-id="${userId}"]`);
                if (row) {
                    const statusCell = row.querySelector('td:last-child');
                    if (statusCell) {
                        statusCell.textContent = status;
                    }
                }
            });

            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
            selectedUserIds = [];
            firstSelectedStatus = null;
            setCheckboxesEnabledByStatus(null);
            updateBulkButtons();

            setRowSelectionEnabled(true);
            
            filterUsers();
            
            closeModal('bulkUpdateStatusModal');
            
            const successMsg = data.updated_count ? 
                `Successfully updated ${data.updated_count} user(s) status to ${status}` :
                'Status updated successfully';
                
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.querySelector('p').textContent = successMsg;
                modal.style.display = 'flex';
            }

        } catch (error) {
            console.error('Error:', error);
            const toast = document.createElement('div');
            toast.textContent = 'Error updating status. Please try again.';
            toast.style.position = 'fixed';
            toast.style.top = '16px';
            toast.style.right = '16px';
            toast.style.background = '#dc3545';
            toast.style.color = '#fff';
            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '6px';
            toast.style.zIndex = '10000';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }
    });

    document.head.insertAdjacentHTML('beforeend', `
        <style>
            body {
                background: #f2f2ef;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                min-height: 100vh;
                margin: 0;
            }
            
            .header-section {
                background: linear-gradient(135deg, #0072bc 0%, #003d82 100%);
                color: white;
                padding: 40px 0;
                margin-bottom: 40px;
                position: relative;
                overflow: hidden;
            }
            
            .header-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
                opacity: 0.3;
            }
            
            .header-section .container {
                position: relative;
                z-index: 2;
                max-width: 1320px;
                margin: 0 auto;
                padding: 0 32px;
            }
            .header-flex{
                display:flex;
                align-items: flex-start;
                justify-content: space-between;
                gap:16px;
            }
            .header-right{ display:flex; flex-direction: column; align-items:flex-end; gap:10px; }
            .welcome-text{ color:#fff; font-weight:700; font-size:1.1rem; text-shadow:0 1px 2px rgba(0,0,0,.3); }
            
            .header-section h1 {
                margin: 0;
                font-size: 3rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .header-section p {
                margin: 12px 0 0 0;
                opacity: 0.9;
                font-size: 1.2rem;
                font-weight: 400;
            }
            
            .logout-btn {
                background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
                border: 2px solid #b02a37;
                color: #fff;
                padding: 12px 24px;
                border-radius: 50px;
                font-weight: 700;
                transition: all 0.25s ease;
                box-shadow: 0 6px 16px rgba(220, 53, 69, 0.35);
            }
            
            .logout-btn:hover {
                background: linear-gradient(135deg, #b02a37 0%, #8c1f2a 100%);
                border-color: #8c1f2a;
                transform: translateY(-2px);
                box-shadow: 0 10px 24px rgba(220, 53, 69, 0.45);
                color: #fff;
            }
            
            .filters-card, .action-buttons-card, .table-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                margin-bottom: 32px;
                border: 1px solid rgba(0,114,188,0.1);
                transition: all 0.3s ease;
                overflow: hidden;
            }
            
            .filters-card:hover, .action-buttons-card:hover, .table-card:hover {
                box-shadow: 0 8px 30px rgba(0,0,0,0.12);
                transform: translateY(-2px);
            }
            
            .filters-card {
                background: linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
                border-left: 4px solid #0072bc;
                height: 100%;
            }
            
            .action-buttons-card {
                background: linear-gradient(145deg, #ffffff 0%, #fffef8 100%);
                border-left: 4px solid #fdf005;
            }
            
            .table-card {
                background: white;
                border-left: 4px solid #003d82;
            }
            
            .card-header {
                background: #f7f9fc;
                padding: 16px 20px;
                border-bottom: 2px solid #e1ebf5;
                border-radius: 0;
                position: relative;
            }
            
            .card-header::before {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                width: 80px;
                background: #0072bc;
            }
            
            .card-header h5 {
                margin: 0;
                font-weight: 800;
                color: #003d82;
                font-size: 1.35rem;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .card-header h5 i {
                color: #0072bc;
                font-size: 1.1rem;
            }
            
            .card-body {
                padding: 24px;
            }
            
            .action-btn {
                margin: 8px;
                padding: 14px 28px;
                font-weight: 600;
                border-radius: 12px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: none;
                position: relative;
                overflow: hidden;
                font-size: 15px;
                letter-spacing: 0.02em;
            }
            
            .action-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                transition: left 0.5s ease;
            }
            
            .action-btn:hover::before {
                left: 100%;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #0072bc 0%, #005a94 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(0,114,188,0.3);
            }
            
            .btn-primary:hover {
                background: linear-gradient(135deg, #005a94 0%, #004275 100%);
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0,114,188,0.4);
                color: white;
            }
            
            .btn-success {
                background: linear-gradient(135deg, #fdf005 0%, #e8dc04 100%);
                color: #333;
                box-shadow: 0 4px 15px rgba(253,240,5,0.3);
            }
            
            .btn-success:hover {
                background: linear-gradient(135deg, #e8dc04 0%, #d4c603 100%);
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(253,240,5,0.4);
                color: #333;
            }
            
            .btn-secondary {
                background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(108,117,125,0.3);
            }
            
            .btn-secondary:hover {
                background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(108,117,125,0.4);
                color: white;
            }
            
            .btn-warning {
                background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                color: #333;
                box-shadow: 0 4px 15px rgba(255,193,7,0.3);
            }
            
            .btn-warning:hover {
                background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(255,193,7,0.4);
                color: #333;
            }
            
            .action-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: none !important;
                box-shadow: none !important;
            }
            
            .action-btn:disabled::before {
                display: none;
            }
            
            .table {
                margin: 0;
                table-layout: fixed;
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
            }

            .table th, .table td {
                text-align: left;
                vertical-align: middle;
                padding: 18px 15px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                border-bottom: 1px solid #f0f7ff;
            }

            .table thead th {
                background: #f7f9fc;
                font-weight: 700;
                border-bottom: 3px solid #0072bc;
                cursor: pointer;
                transition: all 0.3s ease;
                color: #003d82;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                position: relative;
                white-space: nowrap;
            }
            
            .table thead th::after {
                content: '';
                position: absolute;
                bottom: -3px;
                left: 0;
                width: 0;
                height: 3px;
                background: #fdf005;
                transition: width 0.3s ease;
            }
            
            .table thead th:hover {
                background: #eef5fb;
            }
            
            .table thead th:hover::after {
                width: 100%;
            }
            
            .table tbody td {
                font-weight: 500;
                color: #495057;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .table tbody tr:nth-child(even){ background:#fbfdff; }
            .table tbody tr:hover{ background:#f2f8ff; }

            .has-tooltip { position: relative; }
            .has-tooltip:hover::after {
                content: attr(data-fulltext);
                position: absolute;
                left: 0;
                top: -28px;
                background: rgba(0,0,0,0.8);
                color: #fff;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 5;
            }

            .table th:nth-child(1), .table td:nth-child(1) { width: 13%; } /* First Name */
            .table th:nth-child(2), .table td:nth-child(2) { width: 13%; } /* Last Name */
            .table th:nth-child(3), .table td:nth-child(3) { width: 12%; } /* Birthday */
            .table th:nth-child(4), .table td:nth-child(4) { width: 14%; } /* ID Number */
            .table th:nth-child(5), .table td:nth-child(5) { width: 12%; } /* Role */
            .table th:nth-child(6), .table td:nth-child(6) { width: 24%; } /* Program/Position */
            .table th:nth-child(7), .table td:nth-child(7) { width: 11%; } /* Status */
            .table th:nth-child(8), .table td:nth-child(8) { width: 11%; } /* Date Created */
            
            .account-row {
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
            }
        
            .account-row::before { display: none; }
            
            .account-row:hover {
                background: linear-gradient(135deg, #f8fbff 0%, #f0f7ff 100%) !important;
                transform: translateX(4px);
                box-shadow: 0 4px 20px rgba(0,114,188,0.1);
            }
            
            .account-row:hover::before {
                width: 4px;
            }
            
            .account-row.selected {
                background: linear-gradient(135deg, #e3f2fd 0%, #d1e8ff 100%) !important;
                border-left: 4px solid #0072bc;
                transform: translateX(0);
            }
            
            .account-row.selected::before {
                width: 4px;
                background: #0072bc;
            }
            
            .modal {
                display: none;
                position: fixed;
                inset: 0;
                display: none;
                align-items: center;
                justify-content: center;
                background-color: rgba(0,0,0,0.5);
                z-index: 1000;
            }
            
            .modal-content {
                background: white;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                max-width: 500px;
                position: relative;
                margin: 0 auto;
            }
            
            .success-icon {
                color: #28a745;
                font-size: 48px;
                margin-bottom: 15px;
            }
            
            .modal-content h3 {
                margin-bottom: 20px;
                color: #333;
                text-align: center;
                font-weight: 600;
            }
            
            .modal-content form {
                margin-bottom: 15px;
            }
            
            .modal-content .form-group {
                margin-bottom: 14px;
                display: flex;
                align-items: center;
            }
            
            /* Special handling for ID number form group containing input-group */
            .modal-content .form-group.id-number-group {
                display: flex;
                flex-direction: row;
                align-items: center;
            }
            
            .modal-content .form-group.id-number-group label {
                margin-bottom: 0;
                margin-right: 35px;
                min-width: 150px;
                flex-shrink: 0;
            }
            
            .modal-content .form-group.id-number-group .input-wrapper {
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            
            .modal-content .form-group.id-number-group .input-group {
                width: 100%;
            }
            
            .modal-content .form-group.id-number-group .form-text {
                margin-top: 3px;
                font-size: 0.75em;
                color: #6c757d;
                align-self: flex-start;
            }
            
            #changePasswordModal .form-group { flex-direction: column; align-items: stretch; }
            #changePasswordModal .form-group label { min-width: 0; width: auto; margin-bottom: 6px; }
            #changePasswordModal .form-group .form-control { width: 100%; }
            #changePasswordModal .error-feedback { align-self: flex-start; margin-top: 6px; }
            
            .modal-content .btn {
                margin: 5px;
                border-radius: 8px;
                padding: 12px 22px;
                font-size: 16px;
                line-height: 1.2;
                min-width: 140px;
            }
            
            .modal-content .mt-3 {
                text-align: center;
            }
            .error-feedback{
                color:#b02a37;
                font-size: 13px;
                margin-top: 6px;
                text-align: left;
                min-height: 18px;
            }
            
            .form-control {
                border-radius: 10px;
                border: 2px solid #e8f2ff;
                padding: 12px 16px;
                background: #fafbff;
                transition: all 0.3s ease;
                font-size: 14px;
                font-weight: 500;
            }
            
            .form-control:focus {
                border-color: #0072bc;
                box-shadow: 0 0 0 4px rgba(0, 114, 188, 0.15);
                background: white;
                outline: none;
            }
            
            .form-control:hover {
                border-color: #b3d9ff;
                background: white;
            }
            
            .form-group label {
                font-weight: 600;
                color: #003d82;
        font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                margin: 0;
                min-width: 190px;
                white-space: nowrap;
            }
            .form-group .form-control, .form-group .input-group { flex: 1; }
            
            .input-group-text {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-left: none;
            }
            
            .table-responsive {
                border-radius: 0 0 10px 10px;
            }
            
            .container {
                max-width: 100%;
                padding: 0 20px;
            }

            .admin-grid { display:grid; grid-template-columns: 360px minmax(0,1fr); gap:16px; }
            .admin-sidebar { position: sticky; top: 16px; height: fit-content; }
            .admin-main { min-width: 0; }

            .admin-sidebar .card-body { padding: 14px 16px; }
            .admin-sidebar .form-group { display:flex; align-items:center; gap:12px; margin-bottom:10px; padding-bottom:10px; border-bottom: 1px dashed #e6eef6; }
            .admin-sidebar .form-group label { margin:0; min-width: 150px; white-space:nowrap; }
            .admin-sidebar .form-group .form-control, .admin-sidebar .form-group .input-group { flex:1; }
            .admin-sidebar .form-control { height: 38px; padding: 6px 10px; }

            .action-buttons-card .card-body { display:flex; flex-direction:row; flex-wrap:wrap; gap:10px; }
            .action-buttons-card .action-btn { flex:1 1 calc(50% - 10px); min-width:160px; justify-content:center; display:inline-flex; align-items:center; gap:10px; padding:12px 16px; border-radius:10px; font-size:15px; }
            .action-buttons-card .action-btn i { font-size:18px; }

            @media (max-width: 992px) {
                .admin-grid { grid-template-columns: 1fr; }
                .admin-sidebar { position: static; }
            }
            
            .filters-card .card-body > .row {
        display: flex;
                flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
            .filters-card .form-group {
        display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 0;
            }
            .filters-card .form-group label {
                margin: 0;
                white-space: nowrap;
                min-width: 70px;
            }
            .filters-card .form-group .form-control { flex: 1; }
            .filters-card .form-group .input-group { flex: 1; }
            
            .section-spacing {
                margin-bottom: 40px;
            }
            
            .input-group {
                position: relative;
                display: flex;
                flex-wrap: wrap;
                align-items: stretch;
                width: 100%;
            }
            
            .input-group-prepend {
                display: flex;
            }
            
            .input-group-prepend .input-group-text {
                display: flex;
                align-items: center;
                padding: 0.375rem 0.75rem;
                margin-bottom: 0;
                font-size: 1rem;
                font-weight: 400;
                line-height: 1.5;
                color: #495057;
                text-align: center;
                white-space: nowrap;
                border: 1px solid #ced4da;
            }
            
            .input-group .form-control {
                position: relative;
                flex: 1 1 auto;
                width: 1%;
                min-width: 0;
                margin-bottom: 0;
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
                border-right: none;
            }
            
            .input-group .input-group-text {
                background: linear-gradient(135deg, #f8fbff 0%, #e8f2ff 100%);
                border: 2px solid #e8f2ff;
                border-left: none;
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                border-top-right-radius: 10px;
                border-bottom-right-radius: 10px;
                color: #0072bc;
            }
            
            /* Specific styling for ID Number input group */
            .input-group .input-group-prepend .input-group-text {
                background: linear-gradient(135deg, #0072bc 0%, #005a94 100%);
                color: white;
                border: 2px solid #0072bc;
                border-right: none;
                border-top-left-radius: 10px;
                border-bottom-left-radius: 10px;
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
                font-weight: 700;
                letter-spacing: 0.5px;
            }
            
            .input-group .input-group-prepend + .form-control {
                border-left: none;
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
            }
            
            .input-group .input-group-prepend + .form-control:focus {
                border-color: #0072bc;
                box-shadow: 0 0 0 0.2rem rgba(0, 114, 188, 0.25);
            }
            
            .status-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            
            .status-active {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .status-inactive {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .loading {
                opacity: 0.7;
                pointer-events: none;
        position: relative;
            }
            
            .loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                margin: -10px 0 0 -10px;
                border: 2px solid #0072bc;
                border-top: 2px solid transparent;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            @media (max-width: 768px) {
                .header-section h1 {
                    font-size: 2.2rem;
                }
                
                .header-section p {
                    font-size: 1rem;
                }
                
                .action-btn {
                    margin: 4px;
                    padding: 12px 20px;
                    font-size: 14px;
                    width: calc(50% - 8px);
                }
                
                .card-body {
                    padding: 16px;
                }
                
                .card-header {
                    padding: 16px 20px;
                }
                
                .card-header h5 {
                    font-size: 1.1rem;
                }
                
                .form-control {
                    padding: 10px 14px;
                    font-size: 16px;
                }
                
                .table thead th {
                    padding: 12px 8px;
                    font-size: 12px;
                }
                
                .table tbody td {
                    padding: 12px 8px;
                    font-size: 14px;
                }
                
                .container {
                    padding: 0 15px;
                }
            }
            
            @media (max-width: 480px) {
                .action-btn {
        width: 100%;
                    margin: 4px 0;
                }
                
                .d-flex.justify-content-between {
                    flex-direction: column;
                    gap: 16px;
                    text-align: center;
                }
                
                .logout-btn {
                    align-self: center;
                }
            }
            
            .user-type-buttons {
                display: flex;
                flex-direction: column;
                gap: 15px;
                margin: 20px 0;
            }
            
            .user-type-btn {
                padding: 20px 30px !important;
                font-size: 18px !important;
                font-weight: 600 !important;
                border-radius: 12px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
                transition: all 0.3s ease !important;
                min-width: 100% !important;
                text-align: center !important;
            }
            
            .user-type-btn i {
                font-size: 24px;
            }
            
            .user-type-btn:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
            }
            
            /* Bulk update styles */
            .selected-users-list {
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #f9f9f9;
            }
            
            .selected-user-item {
                padding: 4px 8px;
                margin: 2px 0;
                background: #e3f2fd;
                border-radius: 3px;
                font-size: 0.9em;
            }
            
            .user-checkbox {
                margin-right: 8px;
            }
            
            #bulkUpdateStatusBtn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            th:first-child {
                width: 80px;
                text-align: center;
            }
            
            td:first-child {
                text-align: center;
            }
            
            /* Disabled checkbox styling */
            .user-checkbox:disabled {
                opacity: 0.3;
                cursor: not-allowed;
            }
            
            /* Row styling when checkbox is disabled due to status mismatch */
            .account-row.status-disabled {
                opacity: 0.5;
                background-color: #f8f9fa;
            }
            
            .account-row.status-disabled .user-checkbox {
                opacity: 0.3;
            }
            
            /* Disabled row styling */
            .account-row.row-selection-disabled {
                cursor: default !important;
            }
            
            .account-row.row-selection-disabled:hover {
                background-color: inherit !important;
            }
            
            /* Visual indicator for disabled select all */
            #selectAllCheckbox:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            /* Selection status indicator */
            .selection-status {
                padding: 8px 12px;
                border-radius: 4px;
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
            }
            
            .selection-status.text-info {
                background-color: #d1ecf1;
                border-color: #bee5eb;
                color: #0c5460;
            }
            
            .selection-status.text-success {
                background-color: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            }
            
            /* Status restriction info */
            .status-restriction-info {
                padding: 6px 10px;
                border-radius: 4px;
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                font-size: 0.85em;
            }
</style>
    `);
</script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">