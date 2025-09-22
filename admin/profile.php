<?php
session_start();
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../includes/session_check.php';

// Allow only Admin (Role_ID = 2); validateSession handles redirects
if (!validateSession($conn, 2)) {
    exit();
}

// Also ensure logged in fallback
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user info (read-only fields)
$stmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name, Email, phone_number, address, sex, civil_status, Role_ID, status, Profile_Pic, DATE_FORMAT(COALESCE(hired_date, Created_At), '%Y-%m-%d') as join_date FROM users WHERE User_ID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo 'User not found.';
    exit;
}

$profilePic = (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) ? $user['Profile_Pic'] : '../images/default_profile.png';

// Map role id to label (basic)
$roles = [1=>'Super Admin',2=>'Admin',3=>'HR',4=>'Accounting',5=>'Guard'];
$roleLabel = isset($roles[$user['Role_ID']]) ? $roles[$user['Role_ID']] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Green Meadows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Poppins', sans-serif; background-color: #dedede; }
        .profile-pic { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; }
        .card-tight { border-radius: 14px; border: 1px solid #e9ecef; }
        .readonly-input { pointer-events: none; background-color: #f8f9fa;  border: 1px solid #dee2e6;  color: #495057;  cursor: not-allowed; font-weight: 500;     }
        .pill { font-size: 12px; padding: 2px 8px; border-radius: 999px; background:#eef6ff; color:#0d6efd; border:1px solid #cfe2ff; }
        .joined { font-size: 13px; color:#6c757d; }
        .upload-hint { font-size: 12px; }
        /* Avatar camera overlay */
        .avatar-wrap { position: relative; display: inline-block; cursor: pointer; }
        .avatar-wrap .camera-overlay {
            position: absolute; left: 50%; bottom: 0; transform: translate(-50%, 30%);
            width: 44px; height: 44px; border-radius: 50%;
            background: #ede7f6; /* soft purple */
            border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,.12);
            display: flex; align-items: center; justify-content: center;
            color: #6f42c1; opacity: .95; transition: .2s ease-in-out;
        }
        .avatar-wrap:hover .camera-overlay { opacity: 1; transform: translate(-50%, 20%); }
        .avatar-wrap input { display: none; }
    </style>
</head>
<body>
    <div class="container-xxl py-4">
        <div class="d-flex justify-content-end mb-3">
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><span class="material-icons" style="font-size:18px; vertical-align:middle;">arrow_back</span> Back</a>
        </div>

        <div class="row g-3">
            <!-- Left column -->
            <div class="col-lg-5">
                <!-- Your profile card: avatar + joined + upload -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center" style="gap:14px;">
                                <label for="profilePic" class="avatar-wrap" title="Change profile picture">
                                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" class="profile-pic" id="currentProfileImage">
                                    <div class="camera-overlay"><span class="material-icons">photo_camera</span></div>
                                </label>
                                <div>
                                    <div class="h5 mb-0"><?php echo htmlspecialchars($user['First_Name'].' '.$user['Last_Name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($roleLabel); ?> • <span class="text-<?php echo ($user['status']==='Active'?'success':'danger'); ?>"><?php echo htmlspecialchars($user['status']); ?></span></div>
                                </div>
                            </div>
                            <div class="joined">Joined <?php echo $user['join_date'] ? date('M j, Y', strtotime($user['join_date'])) : '—'; ?></div>
                        </div>
                        <hr>
                        <form method="POST" action="update_profile.php" enctype="multipart/form-data" class="mt-2">
                            <input type="hidden" name="firstName" value="<?php echo htmlspecialchars($user['First_Name']); ?>">
                            <input type="hidden" name="lastName" value="<?php echo htmlspecialchars($user['Last_Name']); ?>">
                            <input type="hidden" name="redirect_page" value="profile.php">
                            <div class="row g-2 align-items-center">
                                <div class="col-8">
                                    <input type="file" class="form-control form-control-sm" id="profilePic" name="profilePic" accept=".jpg,.jpeg,.png,.avif,.jfif">
                                    <div class="upload-hint text-muted mt-1">JPG, PNG, AVIF, JFIF up to 5MB</div>
                                </div>
                                <div class="col-4 text-end">
                                    <button type="submit" class="btn btn-success btn-sm">Upload</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Emails -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="pill me-2">Primary</span>
                            <div class="fw-semibold">Emails</div>
                        </div>
                        <div><?php echo htmlspecialchars($user['Email'] ?: 'Not set'); ?></div>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="pill me-2">Primary</span>
                            <div class="fw-semibold">Phone Number</div>
                        </div>
                        <div><?php echo htmlspecialchars($user['phone_number'] ?: 'Not set'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="col-lg-7">
                <!-- Address -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="h6 mb-0">Address</div>
                            <span class="pill">Primary</span>
                        </div>
                        <input type="text" value="<?php echo $user['address'] ? htmlspecialchars($user['address']) : 'Not set'; ?>" class="form-control readonly-input" rows="2" readonly>
                    </div>
                </div>

                <!-- Account Summary (readonly inputs) -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="h6 mb-3">Account Summary</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars(trim($user['First_Name'].' '.$user['Last_Name'])); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($user['phone_number'] ?: ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control readonly-input" value="<?php echo htmlspecialchars($user['Email'] ?: ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($roleLabel); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($user['status']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Joined</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo $user['join_date'] ? htmlspecialchars(date('M d, Y', strtotime($user['join_date']))) : ''; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sex</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($user['sex'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Civil Status</label>
                                <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($user['civil_status'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">For any wrong details please contact HR department or Super Admin.</div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card card-tight shadow-sm mb-3">
                    <div class="card-body">
                        <div class="h6 mb-3">Change Password</div>
                        <form method="POST" action="change_password.php" id="changePasswordForm" autocomplete="off" novalidate>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" id="currentPassword" placeholder="Enter current password" aria-describedby="currentPasswordHelp" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword" aria-label="Show password">
                                            <span class="material-icons" aria-hidden="true">visibility</span>
                                        </button>
                                    </div>
                                    <div class="form-text" id="currentPasswordHelp">
                                        Can't remember your current password? <a href="../forgot_password.php" target="_blank" rel="noopener">Reset it here</a>.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="newPassword" placeholder="Enter new password" minlength="8" required pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/])[A-Za-z0-9!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/]{8,}$" aria-describedby="passwordHelp">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" aria-label="Show password">
                                            <span class="material-icons" aria-hidden="true">visibility</span>
                                        </button>
                                    </div>
                                    <div class="form-text" id="passwordHelp">
                                        Must include at least 1 uppercase, 1 lowercase, 1 number, and 1 special from: ! @ # $ % ^ & * ( ) _ - + = { } [ ] : ; , . ? /
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" placeholder="Re-type new password" minlength="8" required pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/])[A-Za-z0-9!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/]{8,}$">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" aria-label="Show password">
                                            <span class="material-icons" aria-hidden="true">visibility</span>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback" id="pwMatchFeedback">Passwords do not match</div>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </div>
                            </div>
                        </form>
                        <small class="text-muted d-block mt-2">Tip: Use a strong password with letters, numbers, and symbols.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Lightweight preview in-place
        const input = document.getElementById('profilePic');
        const img = document.getElementById('currentProfileImage');
        const preview = document.getElementById('previewImage');
        if (input) {
            input.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    if (preview) preview.src = ev.target.result;
                    if (img) img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        // Simple password match validation
        const newPw = document.getElementById('newPassword');
        const confPw = document.getElementById('confirmPassword');
        const matchFeedback = document.getElementById('pwMatchFeedback');
        function checkPwMatch() {
            if (!newPw || !confPw) return true;
            const ok = newPw.value && confPw.value && newPw.value === confPw.value;
            if (!ok && confPw.value) {
                confPw.classList.add('is-invalid');
            } else {
                confPw.classList.remove('is-invalid');
            }
            return ok;
        }
        if (newPw && confPw) {
            newPw.addEventListener('input', checkPwMatch);
            confPw.addEventListener('input', checkPwMatch);
            const form = document.getElementById('changePasswordForm');
            form && form.addEventListener('submit', (e) => {
                if (!checkPwMatch()) {
                    e.preventDefault();
                    confPw.focus();
                }
            });
            // Disallowed characters live check
            const disallowed = /['"`\\|<>~]/;
            function validateComplexity(el) {
                const val = el.value || '';
                if (disallowed.test(val)) {
                    el.setCustomValidity('Contains disallowed characters.');
                    return;
                }
                const errs = [];
                if (val.length > 0) {
                    if (val.length < 8) errs.push('Min 8 characters');
                    if (!/[A-Z]/.test(val)) errs.push('1 uppercase');
                    if (!/[a-z]/.test(val)) errs.push('1 lowercase');
                    if (!/[0-9]/.test(val)) errs.push('1 number');
                    if (!/[!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/]/.test(val)) errs.push('1 special (!@#$%^&*()_-+={}[]:;,.?/ )');
                }
                el.setCustomValidity(errs.length ? errs.join(' • ') : '');
            }
            newPw.addEventListener('input', () => validateComplexity(newPw));
            confPw.addEventListener('input', () => validateComplexity(confPw));
            validateComplexity(newPw);
            validateComplexity(confPw);
        }

        // Toggle password visibility helpers
        function wireToggle(btnId, inputId) {
            const btn = document.getElementById(btnId);
            const inp = document.getElementById(inputId);
            if (!btn || !inp) return;
            const icon = btn.querySelector('.material-icons');
            btn.addEventListener('click', () => {
                const isPassword = inp.getAttribute('type') === 'password';
                inp.setAttribute('type', isPassword ? 'text' : 'password');
                if (icon) icon.textContent = isPassword ? 'visibility_off' : 'visibility';
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }
        wireToggle('toggleCurrentPassword', 'currentPassword');
        wireToggle('toggleNewPassword', 'newPassword');
        wireToggle('toggleConfirmPassword', 'confirmPassword');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($_SESSION['profilepic_success'])): ?>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: <?php echo json_encode($_SESSION['profilepic_success']); ?> });
    </script>
    <?php unset($_SESSION['profilepic_success']); endif; ?>
    <?php if (isset($_SESSION['profilepic_error'])): ?>
    <script>
        const ToastErr = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
        ToastErr.fire({ icon: 'error', title: <?php echo json_encode($_SESSION['profilepic_error']); ?> });
    </script>
    <?php unset($_SESSION['profilepic_error']); endif; ?>
    <?php if (isset($_SESSION['password_success'])): ?>
    <script>
        const ToastPwOk = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        ToastPwOk.fire({ icon: 'success', title: <?php echo json_encode($_SESSION['password_success']); ?> });
    </script>
    <?php unset($_SESSION['password_success']); endif; ?>
    <?php if (isset($_SESSION['password_error'])): ?>
    <script>
        const ToastPwErr = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        ToastPwErr.fire({ icon: 'error', title: <?php echo json_encode($_SESSION['password_error']); ?> });
    </script>
    <?php unset($_SESSION['password_error']); endif; ?>
</body>
</html>
