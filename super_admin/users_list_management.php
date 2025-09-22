<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
require_once '../includes/govt_id_formatter.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Auth: only Super Admin using proper session validation
if (!validateSession($conn, 1, false)) {
    ob_clean(); // Clear any output
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Content type
if (in_array($action, ['get_details', 'get_edit_form'], true)) {
    ob_clean(); // Clear any output
    header('Content-Type: text/html; charset=UTF-8');
} else {
    ob_clean(); // Clear any output
    header('Content-Type: application/json');
}

switch ($action) {
    case 'get_details':
        getEmployeeDetails();
        break;
    case 'get_edit_form':
        getEmployeeEditForm();
        break;
    case 'add_user':
        addUser();
        break;
    case 'edit_employee':
        editEmployee();
        break;
    case 'archive_employee':
        archiveEmployee();
        break;
    case 'get_locations':
        getGuardLocations();
        break;
    case 'check_govt_id_duplicate':
        checkGovtIdDuplicate();
        break;
    case 'check_employee_id_duplicate':
        checkEmployeeIdDuplicate();
        break;
    case 'test':
        echo json_encode(['success' => true, 'message' => 'API is working', 'session_user' => $_SESSION['user_id'] ?? 'none']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getEmployeeDetails() {
    global $conn;
    $user_id = $_GET['user_id'] ?? '';
    if ($user_id === '') { echo '<div class="alert alert-danger">User ID is required</div>'; return; }

    try {
        $stmt = $conn->prepare("SELECT u.*, r.Role_Name, gl.location_name as guard_location,
                                       g.sss_number, g.tin_number, g.philhealth_number, g.pagibig_number
                                FROM users u
                                LEFT JOIN roles r ON u.Role_ID = r.Role_ID
                                LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
                                LEFT JOIN govt_details g ON u.User_ID = g.user_id
                                WHERE u.User_ID = ? AND u.archived_at IS NULL");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo '<div class="alert alert-danger">Employee not found</div>'; return; }

        $middleInitial = !empty($user['middle_name']) ? ' ' . substr($user['middle_name'], 0, 1) . '.' : '';
        $extension = !empty($user['name_extension']) ? ' ' . $user['name_extension'] : '';
        $fullName = $user['First_Name'] . $middleInitial . ' ' . $user['Last_Name'] . $extension;

        $profilePic = '../images/default_profile.png';
        if (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) {
            $profilePic = $user['Profile_Pic'];
        }

        // Length of service
        $serviceLength = 'Not available';
        if (!empty($user['hired_date'])) {
            $serviceDate = new DateTime($user['hired_date']);
            $now = new DateTime();
            $interval = $serviceDate->diff($now);
            if ($interval->y > 0) {
                $serviceLength = $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
                if ($interval->m > 0) {
                    $serviceLength .= ', ' . $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
                }
            } elseif ($interval->m > 0) {
                $serviceLength = $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
                if ($interval->d > 0) {
                    $serviceLength .= ', ' . $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
                }
            } else {
                $serviceLength = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
            }
        }

        $html = "
        <div class='row'>
            <div class='col-md-4 text-center'>
                <img src='{$profilePic}' alt='Profile Picture' class='img-fluid rounded-circle mb-3' style='width: 150px; height: 150px; object-fit: cover;'>
                <h5>" . htmlspecialchars($fullName) . "</h5>
                <p class='text-muted'>" . htmlspecialchars($user['Role_Name'] ?? '') . "</p>
            </div>
            <div class='col-md-8'>
                <table class='table table-borderless'>
                    <tr><th>Employee ID:</th><td>" . htmlspecialchars($user['employee_id'] ?? '') . "</td></tr>
                    <tr><th>Email:</th><td>" . htmlspecialchars($user['Email'] ?? '') . "</td></tr>
                    <tr><th>Phone:</th><td>" . htmlspecialchars($user['phone_number'] ?? '') . "</td></tr>
                    <tr><th>Address:</th><td>" . (!empty($user['address']) ? htmlspecialchars($user['address']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                    <tr><th>Sex:</th><td>" . (!empty($user['sex']) ? htmlspecialchars($user['sex']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                    <tr><th>Marital Status:</th><td>" . (!empty($user['civil_status']) ? htmlspecialchars(($user['civil_status'] === 'Separated' ? 'Separated (Annulled)' : $user['civil_status'])) : '<span class="text-muted">Not set</span>') . "</td></tr>
                    <tr><th>Birth Date:</th><td>" . ($user['birthday'] ? date('M j, Y', strtotime($user['birthday'])) : 'Not set') . "</td></tr>
                    <tr><th>Hire Date:</th><td>" . ($user['hired_date'] ? date('M j, Y', strtotime($user['hired_date'])) : 'Not set') . "</td></tr>
                    <tr><th>Length of Service:</th><td>{$serviceLength}</td></tr>
                    <tr><th>Status:</th><td><span class='badge " . ($user['status'] === 'Active' ? 'bg-success' : 'bg-danger') . "'>" . htmlspecialchars($user['status']) . "</span></td></tr>";
        if ((int)$user['Role_ID'] === 5 && !empty($user['guard_location'])) {
            $html .= "<tr><th>Location:</th><td>" . htmlspecialchars($user['guard_location']) . "</td></tr>";
        }
        $html .= "
                </table>
            </div>
        </div>
        <div class='row mt-4'>
            <div class='col-12'>
                <h6 class='text-primary mb-3'><i class='material-icons align-middle me-1'>credit_card</i>Government ID Numbers</h6>
                <div class='row'>
                    <div class='col-md-6'>
                        <table class='table table-sm table-borderless'>
                            <tr><th width='40%'>SSS Number:</th><td>" . (!empty($user['sss_number']) ? formatSSS($user['sss_number']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                            <tr><th>TIN Number:</th><td>" . (!empty($user['tin_number']) ? formatTIN($user['tin_number']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                        </table>
                    </div>
                    <div class='col-md-6'>
                        <table class='table table-sm table-borderless'>
                            <tr><th width='40%'>PhilHealth:</th><td>" . (!empty($user['philhealth_number']) ? formatPhilHealth($user['philhealth_number']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                            <tr><th>Pag-IBIG:</th><td>" . (!empty($user['pagibig_number']) ? formatPagIbig($user['pagibig_number']) : '<span class="text-muted">Not set</span>') . "</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>";

        echo $html;
    } catch (Exception $e) {
        error_log('Error getting employee details: ' . $e->getMessage());
        echo '<div class="alert alert-danger">An error occurred while loading employee details</div>';
    }
}

function getEmployeeEditForm() {
    global $conn;
    $user_id = $_GET['user_id'] ?? '';
    if ($user_id === '') { echo '<div class="alert alert-danger">User ID is required</div>'; return; }

    try {
        $stmt = $conn->prepare("SELECT u.*, r.Role_Name, gl.location_name as guard_location,
                                       g.sss_number, g.tin_number, g.philhealth_number, g.pagibig_number
                                FROM users u
                                LEFT JOIN roles r ON u.Role_ID = r.Role_ID
                                LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
                                LEFT JOIN govt_details g ON u.User_ID = g.user_id
                                WHERE u.User_ID = ? AND u.archived_at IS NULL");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo '<div class="alert alert-danger">Employee not found</div>'; return; }

        $locations = [];
        if ((int)$user['Role_ID'] === 5) {
            $locationsStmt = $conn->prepare('SELECT DISTINCT location_name FROM guard_locations ORDER BY location_name');
            $locationsStmt->execute();
            $locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $profilePic = '../images/default_profile.png';
        if (!empty($user['Profile_Pic']) && file_exists($user['Profile_Pic'])) {
            $profilePic = $user['Profile_Pic'];
        }

        echo "
        <form id='editEmployeeFormInner'>
            <input type='hidden' name='action' value='edit_employee'>
            <input type='hidden' name='user_id' value='" . htmlspecialchars($user['User_ID']) . "'>
            <input type='hidden' name='username' value='" . htmlspecialchars($user['Username']) . "'>
            <div class='row g-3'>
                <div class='col-12 text-center'>
                    <img src='{$profilePic}' alt='Profile Picture' class='rounded-circle' width='100' height='100' style='object-fit: cover;'>
                    <div class='form-text text-muted mt-2'>Profile Picture (View Only)</div>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>First Name <span class='text-danger'>*</span></label>
                    <input type='text' class='form-control' name='first_name' value='" . htmlspecialchars($user['First_Name']) . "' required>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Last Name <span class='text-danger'>*</span></label>
                    <input type='text' class='form-control' name='last_name' value='" . htmlspecialchars($user['Last_Name']) . "' required>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Middle Name</label>
                    <input type='text' class='form-control' name='middle_name' value='" . htmlspecialchars($user['middle_name']) . "'>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Name Extension</label>
                    <select class='form-select' name='name_extension'>
                        <option value=''>None</option>
                        <option value='Jr.' " . ($user['name_extension'] === 'Jr.' ? 'selected' : '') . ">Jr.</option>
                        <option value='Sr.' " . ($user['name_extension'] === 'Sr.' ? 'selected' : '') . ">Sr.</option>
                        <option value='II' " . ($user['name_extension'] === 'II' ? 'selected' : '') . ">II</option>
                        <option value='III' " . ($user['name_extension'] === 'III' ? 'selected' : '') . ">III</option>
                        <option value='IV' " . ($user['name_extension'] === 'IV' ? 'selected' : '') . ">IV</option>
                    </select>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Sex</label>
                    <select class='form-select' name='sex'>
                        <option value=''>Select Sex</option>
                        <option value='Male' " . ($user['sex'] === 'Male' ? 'selected' : '') . ">Male</option>
                        <option value='Female' " . ($user['sex'] === 'Female' ? 'selected' : '') . ">Female</option>
                    </select>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Marital Status</label>
                    <select class='form-select' name='civil_status'>
                        <option value=''>Select Status</option>
                        <option value='Single' " . ($user['civil_status'] === 'Single' ? 'selected' : '') . ">Single</option>
                        <option value='Married' " . ($user['civil_status'] === 'Married' ? 'selected' : '') . ">Married</option>
                        <option value='Widowed' " . ($user['civil_status'] === 'Widowed' ? 'selected' : '') . ">Widowed</option>
                        <option value='Divorced' " . ($user['civil_status'] === 'Divorced' ? 'selected' : '') . ">Divorced</option>
                        <option value='Separated' " . (($user['civil_status'] === 'Separated' || $user['civil_status'] === 'Annulled') ? 'selected' : '') . ">Separated (Annulled)</option>
                    </select>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Phone Number <span class='text-danger'>*</span></label>
                    <input type='tel' class='form-control' name='phone_number' value='" . htmlspecialchars($user['phone_number']) . "' required>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Email Address <span class='text-danger'>*</span></label>
                    <input type='email' class='form-control' name='email' value='" . htmlspecialchars($user['Email']) . "' required>
                </div>
                <div class='col-12'>
                    <label class='form-label'>Address</label>
                    <input type='text' class='form-control' name='address' value='" . htmlspecialchars($user['address'] ?? '') . "' placeholder='House/Street, Barangay, City/Province'>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Birth Date</label>
                    <input type='date' class='form-control' name='birth_date' value='" . htmlspecialchars($user['birthday'] ?? '') . "'>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Hire Date <span class='text-danger'>*</span></label>
                    <input type='date' class='form-control' name='hire_date' value='" . htmlspecialchars($user['hired_date'] ?? '') . "' required>
                </div>";
        if ((int)$user['Role_ID'] === 5) {
            echo "
                <div class='col-12'>
                    <label class='form-label'>Guard Location <span class='text-danger'>*</span></label>
                    <select class='form-select' name='guard_location' required>
                        <option value=''>Select Location</option>";
            foreach ($locations as $location) {
                $selected = $location['location_name'] === $user['guard_location'] ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($location['location_name']) . "' {$selected}>" . htmlspecialchars($location['location_name']) . "</option>";
            }
            echo "
                    </select>
                </div>";
        }
        echo "
                <div class='col-md-6'>
                    <label class='form-label'>Status <span class='text-danger'>*</span></label>
                    <select class='form-select' name='status' required>
                        <option value='Active' " . ($user['status'] === 'Active' ? 'selected' : '') . ">Active</option>
                        <option value='Inactive' " . ($user['status'] === 'Inactive' ? 'selected' : '') . ">Inactive</option>
                    </select>
                </div>
                <div class='col-md-6'>
                    <label class='form-label'>Employee ID <span class='text-danger'>*</span></label>
                    <input type='text' class='form-control' name='employee_id' id='edit_employee_id' value='" . htmlspecialchars($user['employee_id'] ?? '') . "' required>
                    <div class='form-text' id='edit_employee_id_feedback'></div>
                </div>
                <div class='col-12 mt-4'>
                    <h6 class='text-primary mb-3'><i class='material-icons align-middle me-1'>credit_card</i>Government ID Numbers</h6>
                    <div class='row g-3'>
                        <div class='col-md-6'>
                            <label class='form-label'>SSS Number</label>
                            <input type='text' class='form-control govt-id-input' name='sss_number' value='" . (!empty($user['sss_number']) ? formatSSS($user['sss_number']) : '') . "' maxlength='12' data-format='sss' placeholder='34-1234567-8'>
                            <div class='form-text'>Format: ##-#######-# (10 digits)</div>
                        </div>
                        <div class='col-md-6'>
                            <label class='form-label'>TIN Number</label>
                            <input type='text' class='form-control govt-id-input' name='tin_number' value='" . (!empty($user['tin_number']) ? formatTIN($user['tin_number']) : '') . "' maxlength='15' data-format='tin' placeholder='123-456-789-000'>
                            <div class='form-text'>Format: ###-###-###-### (12 digits with branch code)</div>
                        </div>
                        <div class='col-md-6'>
                            <label class='form-label'>PhilHealth Number</label>
                            <input type='text' class='form-control govt-id-input' name='philhealth_number' value='" . (!empty($user['philhealth_number']) ? formatPhilHealth($user['philhealth_number']) : '') . "' maxlength='14' data-format='philhealth' placeholder='12-123456789-0'>
                            <div class='form-text'>Format: ##-#########-# (12 digits)</div>
                        </div>
                        <div class='col-md-6'>
                            <label class='form-label'>Pag-IBIG Number</label>
                            <input type='text' class='form-control govt-id-input' name='pagibig_number' value='" . (!empty($user['pagibig_number']) ? formatPagIbig($user['pagibig_number']) : '') . "' maxlength='14' data-format='pagibig' placeholder='1234-5678-9012'>
                            <div class='form-text'>Format: ####-####-#### (12 digits)</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                <button type='submit' class='btn btn-info'>Update Employee</button>
            </div>
        </form>";
    } catch (Exception $e) {
        error_log('Error loading employee edit form: ' . $e->getMessage());
        echo '<div class="alert alert-danger">An error occurred while loading the employee details</div>';
    }
}

function addUser() {
    global $conn;
    
    try {
        // Get Super Admin user information for logging
        $superAdminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
        $superAdminStmt->execute([$_SESSION['user_id']]);
        $superAdminData = $superAdminStmt->fetch(PDO::FETCH_ASSOC);
        $superAdminName = $superAdminData ? $superAdminData['First_Name'] . ' ' . $superAdminData['Last_Name'] : "Super Admin";
        
        // Get form data
        $role_id = $_POST['role_id'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? '';
        $name_extension = $_POST['name_extension'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        // Sanitize phone number to digits only for consistent validation/storage
        $phone_number = preg_replace('/\D/', '', $phone_number);
        $email = $_POST['email'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        $birth_date = $_POST['birth_date'] ?? '';
        $hire_date = $_POST['hire_date'] ?? '';
        $guard_location = $_POST['guard_location'] ?? '';
        $employee_id = $_POST['employee_id'] ?? '';
        
        // Government ID fields (required)
        $sss_number = unformatGovtId($_POST['sss_number'] ?? '');
        $tin_number = unformatGovtId($_POST['tin_number'] ?? '');
        $philhealth_number = unformatGovtId($_POST['philhealth_number'] ?? '');
        $pagibig_number = unformatGovtId($_POST['pagibig_number'] ?? '');
        
        // Normalize civil status: DB supports Single, Married, Widowed, Divorced, Separated
        if (strcasecmp($civil_status, 'Annulled') === 0) {
            $civil_status = 'Separated';
        }
        $allowedCivil = ['Single','Married','Widowed','Divorced','Separated',''];
        if (!in_array($civil_status, $allowedCivil, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid marital status selected']);
            return;
        }
        
        // Validate required fields
        if (empty($role_id) || empty($first_name) || empty($last_name) || empty($phone_number) || 
            empty($email) || empty($birth_date) || empty($hire_date) || empty($employee_id) ||
            empty($sss_number) || empty($tin_number) || empty($philhealth_number) || empty($pagibig_number)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields including government IDs']);
            return;
        }
        
        // Validate birth date - must be at least 18 years old
        if (!validateAge($birth_date)) {
            echo json_encode(['success' => false, 'message' => 'Employee must be at least 18 years old']);
            return;
        }
        
        // Validate Philippine phone number format
        if (!validatePhilippinePhone($phone_number)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must be in Philippine format: 09XX XXX XXXX']);
            return;
        }
        
        // Validate email format and domain
        if (!validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid email from trusted domains (gmail.com, yahoo.com, outlook.com, etc.)']);
            return;
        }
        
        // Additional validation for guards
        if ($role_id == 5 && empty($guard_location)) {
            echo json_encode(['success' => false, 'message' => 'Guard location is required for security guards']);
            return;
        }
        
        // Validate government IDs format (only if not placeholder values)
        if (!isPlaceholderGovtId($sss_number) && !validateSSS($sss_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid SSS number format. Must be 10 digits.']);
            return;
        }
        if (!isPlaceholderGovtId($tin_number) && !validateTIN($tin_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid TIN number format. Must be 9 or 12 digits.']);
            return;
        }
        if (!isPlaceholderGovtId($philhealth_number) && !validatePhilHealth($philhealth_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid PhilHealth number format. Must be 12 digits.']);
            return;
        }
        if (!isPlaceholderGovtId($pagibig_number) && !validatePagIbig($pagibig_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Pag-IBIG number format. Must be 12 digits.']);
            return;
        }
        
        // Check for duplicate government IDs (only if not placeholder values)
        if (!isPlaceholderGovtId($sss_number) && checkGovtIdExists($conn, 'sss', $sss_number)) {
            echo json_encode(['success' => false, 'message' => 'SSS number already exists for another employee.']);
            return;
        }
        if (!isPlaceholderGovtId($tin_number) && checkGovtIdExists($conn, 'tin', $tin_number)) {
            echo json_encode(['success' => false, 'message' => 'TIN number already exists for another employee.']);
            return;
        }
        if (!isPlaceholderGovtId($philhealth_number) && checkGovtIdExists($conn, 'philhealth', $philhealth_number)) {
            echo json_encode(['success' => false, 'message' => 'PhilHealth number already exists for another employee.']);
            return;
        }
        if (!isPlaceholderGovtId($pagibig_number) && checkGovtIdExists($conn, 'pagibig', $pagibig_number)) {
            echo json_encode(['success' => false, 'message' => 'Pag-IBIG number already exists for another employee.']);
            return;
        }
        
        // Check if employee ID already exists
        $checkEmpId = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE employee_id = ?");
        $checkEmpId->execute([$employee_id]);
        if ($checkEmpId->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Employee ID already exists']);
            return;
        }
        
        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE Email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Email address already exists']);
            return;
        }

        // Check if phone number already exists (active users)
        $checkPhone = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE phone_number = ? AND archived_at IS NULL");
        $checkPhone->execute([$phone_number]);
        if ($checkPhone->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists for another employee']);
            return;
        }
        
        // Generate username (firstName.lastName)
        $baseUsername = strtolower($first_name . '.' . $last_name);
        $username = $baseUsername;
        $counter = 1;
        
        // Check if username exists, if yes, append a number
        $usernameCheckStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Username = ?");
        $usernameCheckStmt->execute([$username]);
        
        while ($usernameCheckStmt->fetchColumn() > 0) {
            $username = $baseUsername . $counter;
            $counter++;
            $usernameCheckStmt->execute([$username]);
        }
        
        // Generate a random password
        $password = generateRandomPassword();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Get role name for logging
        $roleStmt = $conn->prepare("SELECT Role_Name FROM roles WHERE Role_ID = ?");
        $roleStmt->execute([$role_id]);
        $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $role_name = $roleData ? $roleData['Role_Name'] : 'Unknown Role';
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Insert user
            $userStmt = $conn->prepare("
                INSERT INTO users (
                    Username, Email, Password_Hash, Role_ID, hired_date, Profile_Pic, First_Name, Last_Name, name_extension, middle_name, phone_number, birthday, sex, civil_status, status, employee_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
            ");
            
            $userStmt->execute([
                $username,
                $email,
                $hashed_password,
                $role_id,
                $hire_date,
                null, // Profile_Pic
                $first_name,
                $last_name,
                $name_extension,
                $middle_name,
                $phone_number,
                $birth_date ?: null,
                ($sex ?: null),
                ($civil_status ?: null),
                $employee_id
            ]);
            
            $new_user_id = $conn->lastInsertId();
            
            // If it's a guard, add location information
            if ($role_id == 5 && !empty($guard_location)) {
                // Get or create location rate from guard_locations table
                $locationStmt = $conn->prepare("SELECT daily_rate FROM guard_locations WHERE location_name = ? LIMIT 1");
                $locationStmt->execute([$guard_location]);
                $locationData = $locationStmt->fetch(PDO::FETCH_ASSOC);
                $daily_rate = $locationData ? $locationData['daily_rate'] : 0;

                // Coordinate mapping for known locations
                $locationCoords = [
                    'Batangas' => ['lat' => 13.91468260, 'lng' => 121.08765660],
                    'Biñan' => ['lat' => 14.33882590, 'lng' => 121.08418260],
                    'Bulacan' => ['lat' => 15.00000000, 'lng' => 121.08333300],
                    'Cavite' => ['lat' => 14.36394350, 'lng' => 120.86715030],
                    'Laguna' => ['lat' => 14.16964760, 'lng' => 121.33365260],
                    'Manila' => ['lat' => 14.59044920, 'lng' => 120.98036210],
                    'Naga' => ['lat' => 13.62401220, 'lng' => 123.18503180],
                    'NCR' => ['lat' => 14.59044920, 'lng' => 120.98036210],
                    'Pampanga' => ['lat' => 15.05196350, 'lng' => 120.64553980],
                    'Pangasinan' => ['lat' => 15.91666700, 'lng' => 120.33333300],
                    'San Pedro Laguna' => ['lat' => 14.36394350, 'lng' => 121.33365260],
                ];
                $lat = $locationCoords[$guard_location]['lat'] ?? 0.0;
                $lng = $locationCoords[$guard_location]['lng'] ?? 0.0;

                // Insert guard location with coordinates
                $guardLocationStmt = $conn->prepare("INSERT INTO guard_locations (user_id, location_name, daily_rate, is_primary, designated_latitude, designated_longitude, allowed_radius) VALUES (?, ?, ?, 1, ?, ?, 100)");
                $guardLocationStmt->execute([$new_user_id, $guard_location, $daily_rate, $lat, $lng]);
            }
            
            // Insert government details (now required)
            $govtStmt = $conn->prepare("
                INSERT INTO govt_details (user_id, sss_number, tin_number, philhealth_number, pagibig_number) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $govtStmt->execute([
                $new_user_id,
                $sss_number, 
                $tin_number, 
                $philhealth_number, 
                $pagibig_number
            ]);
            
            // Send email with login credentials
            $mail = new PHPMailer(true);
            
            // Configure SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'phpmailer572@gmail.com';
            $mail->Password = 'hbwulibpahbbsuhu';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Set email content
            $mail->setFrom('phpmailer572@gmail.com', 'Green Meadows Security');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Green Meadows Security Agency';
            
            // Email template
            $mail->Body = <<<EOD
            <div style="font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f5f5; padding: 20px; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <div style="background-color: #2a7d4f; color: #ffffff; padding: 30px; text-align: center;">
                        <h1 style="margin: 0; font-size: 26px; font-weight: 600;">Welcome to Green Meadows Security Agency</h1>
                    </div>
                    <div style="padding: 30px; color: #333333;">
                        <p style="font-size: 16px; line-height: 1.6;">Dear <strong>{$first_name} {$last_name}</strong>,</p>
                        <p style="font-size: 16px; line-height: 1.6;">
                            Welcome to Green Meadows Security Agency! Your account has been successfully created.
                            Here are your login credentials:
                        </p>
                        <div style="background-color: #f8f9fa; padding: 20px; border-left: 4px solid #2a7d4f; border-radius: 4px; margin: 25px 0;">
                            <p style="margin: 8px 0; font-size: 16px;"><strong>Email:</strong> {$email}</p>
                            <p style="margin: 8px 0; font-size: 16px;"><strong>Password:</strong> {$password}</p>
                        </div>
                        <p style="font-size: 16px; line-height: 1.6; color: #dc3545;"><strong>Important:</strong> Please change your password after your first login for security purposes.</p>
                        <p style="font-size: 16px; line-height: 1.6;">If you have any questions, feel free to contact the HR department.</p>
                        <p style="font-size: 16px; line-height: 1.6; margin-top: 30px;">Best regards,</p>
                        <p style="font-size: 16px; line-height: 1.6;"><strong>Green Meadows Security Agency Team</strong></p>
                    </div>
                    <div style="background-color: #f5f5f5; text-align: center; padding: 15px; font-size: 14px; color: #666666; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0;">© 2025 Green Meadows Security Agency. All rights reserved.</p>
                    </div>
                </div>
            </div>
            EOD;
            
            // Send email
            $mail->send();
            
            // Log the activity
            $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'User Creation', ?)");
            $logDetails = "{$superAdminName} created new user: {$first_name} {$last_name} (Role: {$role_name})";
            if ($role_id == 5) {
                $logDetails .= " (Location: {$guard_location})";
            }
            $logStmt->execute([$_SESSION['user_id'], $logDetails]);
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "User {$first_name} {$last_name} has been successfully created. Login credentials have been sent to {$email}."
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error creating user: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while creating the user: ' . $e->getMessage()]);
    }
}

function editEmployee() {
    global $conn;
    try {
        // Actor
        $actorStmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
        $actorStmt->execute([$_SESSION['user_id']]);
        $actorData = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = $actorData ? $actorData['First_Name'] . ' ' . $actorData['Last_Name'] : 'Super Admin';

        // Inputs
        $user_id = $_POST['user_id'] ?? '';
        $employee_id = $_POST['employee_id'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? '';
        $name_extension = $_POST['name_extension'] ?? '';
        $phone_number = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        $birth_date = $_POST['birth_date'] ?? '';
        $hire_date = $_POST['hire_date'] ?? '';
        $guard_location = $_POST['guard_location'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        $status = $_POST['status'] ?? '';

        if (strcasecmp($civil_status, 'Annulled') === 0) { $civil_status = 'Separated'; }
        $allowedCivil = ['Single','Married','Widowed','Divorced','Separated',''];
        if (!in_array($civil_status, $allowedCivil, true)) { echo json_encode(['success'=>false,'message'=>'Invalid marital status selected']); return; }
        $allowedStatus = ['Active','Inactive'];
        if ($status !== '' && !in_array($status, $allowedStatus, true)) { echo json_encode(['success'=>false,'message'=>'Invalid status value']); return; }

        if (empty($user_id) || empty($employee_id) || empty($first_name) || empty($last_name) || empty($phone_number) || empty($email) || empty($hire_date) || empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            return;
        }
        if (!empty($birth_date) && !validateAge($birth_date)) { echo json_encode(['success'=>false,'message'=>'Employee must be at least 18 years old']); return; }
        if (!validatePhilippinePhone($phone_number)) { echo json_encode(['success'=>false,'message'=>'Phone number must be in Philippine format: 09XX XXX XXXX']); return; }
        if (!validateEmail($email)) { echo json_encode(['success'=>false,'message'=>'Please provide a valid email from trusted domains (gmail.com, yahoo.com, outlook.com, etc.)']); return; }

        // Current user
        $currentUserStmt = $conn->prepare('SELECT Role_ID, Username, Email, employee_id FROM users WHERE User_ID = ? AND archived_at IS NULL');
        $currentUserStmt->execute([$user_id]);
        $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentUser) { echo json_encode(['success'=>false,'message'=>'Employee not found']); return; }

        // Uniques
        if ($employee_id !== $currentUser['employee_id']) {
            $checkEmployeeId = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE employee_id = ? AND User_ID != ? AND archived_at IS NULL');
            $checkEmployeeId->execute([$employee_id, $user_id]);
            if ($checkEmployeeId->fetch(PDO::FETCH_ASSOC)['count'] > 0) { 
                echo json_encode(['success'=>false,'message'=>'Employee ID already exists for another employee']); 
                return; 
            }
        }
        $checkPhone = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE phone_number = ? AND User_ID != ? AND archived_at IS NULL');
        $checkPhone->execute([$phone_number, $user_id]);
        if ($checkPhone->fetch(PDO::FETCH_ASSOC)['count'] > 0) { echo json_encode(['success'=>false,'message'=>'Phone number already exists for another employee']); return; }
        if ($username !== $currentUser['Username']) {
            $checkUser = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE Username = ? AND User_ID != ?');
            $checkUser->execute([$username, $user_id]);
            if ($checkUser->fetch(PDO::FETCH_ASSOC)['count'] > 0) { echo json_encode(['success'=>false,'message'=>'Username already exists']); return; }
        }
        if ($email !== $currentUser['Email']) {
            $checkEmail = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE Email = ? AND User_ID != ?');
            $checkEmail->execute([$email, $user_id]);
            if ($checkEmail->fetch(PDO::FETCH_ASSOC)['count'] > 0) { echo json_encode(['success'=>false,'message'=>'Email address already exists']); return; }
        }

        $conn->beginTransaction();
        $updateFields = [
            'employee_id = ?', 'First_Name = ?', 'middle_name = ?', 'Last_Name = ?', 'name_extension = ?',
            'phone_number = ?', 'Email = ?', 'address = ?', 'birthday = ?', 'hired_date = ?', 'username = ?', 'sex = ?', 'civil_status = ?'
        ];
        $updateValues = [
            $employee_id, $first_name, $middle_name, $last_name, $name_extension,
            $phone_number, $email, ($address ?: null), $birth_date ?: null, $hire_date, $username, ($sex ?: null), ($civil_status ?: null)
        ];
        $updateFields[] = 'status = ?';
        $updateValues[] = ($status !== '' ? $status : null);
        if (!empty($password)) { $updateFields[] = 'password = ?'; $updateValues[] = password_hash($password, PASSWORD_DEFAULT); }
        $updateValues[] = $user_id;
        $updateQuery = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE User_ID = ?';
        $conn->prepare($updateQuery)->execute($updateValues);

        // Guard location
        if ((int)$currentUser['Role_ID'] === 5 && !empty($guard_location)) {
            $locationStmt = $conn->prepare('SELECT daily_rate FROM guard_locations WHERE location_name = ? LIMIT 1');
            $locationStmt->execute([$guard_location]);
            $locationData = $locationStmt->fetch(PDO::FETCH_ASSOC);
            $daily_rate = $locationData ? $locationData['daily_rate'] : 0;
            $locationCoords = [
                'Batangas' => ['lat' => 13.91468260, 'lng' => 121.08765660],
                'Biñan' => ['lat' => 14.33882590, 'lng' => 121.08418260],
                'Bulacan' => ['lat' => 15.00000000, 'lng' => 121.08333300],
                'Cavite' => ['lat' => 14.36394350, 'lng' => 120.86715030],
                'Laguna' => ['lat' => 14.16964760, 'lng' => 121.33365260],
                'Naga' => ['lat' => 13.62401220, 'lng' => 123.18503180],
                'NCR' => ['lat' => 14.59044920, 'lng' => 120.98036210],
                'Pampanga' => ['lat' => 15.05196350, 'lng' => 120.64553980],
                'Pangasinan' => ['lat' => 15.91666700, 'lng' => 120.33333300],
                'San Pedro Laguna' => ['lat' => 14.36394350, 'lng' => 121.33365260],
            ];
            $lat = $locationCoords[$guard_location]['lat'] ?? 0.0;
            $lng = $locationCoords[$guard_location]['lng'] ?? 0.0;

            $checkLocationStmt = $conn->prepare('SELECT COUNT(*) as count FROM guard_locations WHERE user_id = ? AND is_primary = 1');
            $checkLocationStmt->execute([$user_id]);
            if ($checkLocationStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $conn->prepare('UPDATE guard_locations SET location_name = ?, daily_rate = ?, designated_latitude = ?, designated_longitude = ? WHERE user_id = ? AND is_primary = 1')
                     ->execute([$guard_location, $daily_rate, $lat, $lng, $user_id]);
            } else {
                $conn->prepare('INSERT INTO guard_locations (user_id, location_name, daily_rate, is_primary, designated_latitude, designated_longitude, allowed_radius, created_at) VALUES (?, ?, ?, 1, ?, ?, 100, NOW())')
                     ->execute([$user_id, $guard_location, $daily_rate, $lat, $lng]);
            }
        }

        // Govt details
        $sss_number = unformatGovtId($_POST['sss_number'] ?? '');
        $tin_number = unformatGovtId($_POST['tin_number'] ?? '');
        $philhealth_number = unformatGovtId($_POST['philhealth_number'] ?? '');
        $pagibig_number = unformatGovtId($_POST['pagibig_number'] ?? '');
        if (!isPlaceholderGovtId($sss_number) && !validateSSS($sss_number)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'Invalid SSS number format. Must be 10 digits.']); return; }
        if (!isPlaceholderGovtId($tin_number) && !validateTIN($tin_number)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'Invalid TIN number format. Must be 9 or 12 digits.']); return; }
        if (!isPlaceholderGovtId($philhealth_number) && !validatePhilHealth($philhealth_number)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'Invalid PhilHealth number format. Must be 12 digits.']); return; }
        if (!isPlaceholderGovtId($pagibig_number) && !validatePagIbig($pagibig_number)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'Invalid Pag-IBIG number format. Must be 12 digits.']); return; }
        if (!isPlaceholderGovtId($sss_number) && checkGovtIdExists($conn, 'sss', $sss_number, $user_id)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'SSS number already exists for another employee.']); return; }
        if (!isPlaceholderGovtId($tin_number) && checkGovtIdExists($conn, 'tin', $tin_number, $user_id)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'TIN number already exists for another employee.']); return; }
        if (!isPlaceholderGovtId($philhealth_number) && checkGovtIdExists($conn, 'philhealth', $philhealth_number, $user_id)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'PhilHealth number already exists for another employee.']); return; }
        if (!isPlaceholderGovtId($pagibig_number) && checkGovtIdExists($conn, 'pagibig', $pagibig_number, $user_id)) { $conn->rollBack(); echo json_encode(['success'=>false,'message'=>'Pag-IBIG number already exists for another employee.']); return; }

        $checkGovtStmt = $conn->prepare('SELECT COUNT(*) as count FROM govt_details WHERE user_id = ?');
        $checkGovtStmt->execute([$user_id]);
        if ($checkGovtStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $conn->prepare('UPDATE govt_details SET sss_number = ?, tin_number = ?, philhealth_number = ?, pagibig_number = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')
                 ->execute([$sss_number ?: null, $tin_number ?: null, $philhealth_number ?: null, $pagibig_number ?: null, $user_id]);
        } elseif (!empty($sss_number) || !empty($tin_number) || !empty($philhealth_number) || !empty($pagibig_number)) {
            $conn->prepare('INSERT INTO govt_details (user_id, sss_number, tin_number, philhealth_number, pagibig_number) VALUES (?, ?, ?, ?, ?)')
                 ->execute([$user_id, $sss_number ?: null, $tin_number ?: null, $philhealth_number ?: null, $pagibig_number ?: null]);
        }

        // Log
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'Employee Update', ?)");
        $logDetails = "{$actorName} updated employee: {$first_name} {$last_name}";
        $logStmt->execute([$_SESSION['user_id'], $logDetails]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Employee {$first_name} {$last_name} has been successfully updated"]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        error_log('Error updating employee: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating the employee']);
    }
}

function archiveEmployee() {
    global $conn;
    try {
        $actorStmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
        $actorStmt->execute([$_SESSION['user_id']]);
        $actorData = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = $actorData ? $actorData['First_Name'] . ' ' . $actorData['Last_Name'] : 'Super Admin';

        $employee_id = $_POST['employee_id'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $archived_by = $_SESSION['user_id'];
        if ($employee_id === '') { echo json_encode(['success'=>false,'message'=>'Employee ID is required']); return; }

        $stmt = $conn->prepare('SELECT First_Name, Last_Name, Role_ID FROM users WHERE User_ID = ? AND archived_at IS NULL');
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) { echo json_encode(['success'=>false,'message'=>'Employee not found or already archived']); return; }

        // Don't allow archiving super admin or other HR users (unless you're super admin)
        if ($_SESSION['role_id'] != 1 && ($employee['Role_ID'] == 1 || $employee['Role_ID'] == 3)) {
            echo json_encode(['success' => false, 'message' => 'You cannot archive admin or HR employees']);
            return;
        }

        $employee_name = $employee['First_Name'] . ' ' . $employee['Last_Name'];
        $conn->beginTransaction();
        $conn->prepare('UPDATE users SET archived_at = NOW(), archived_by = ? WHERE User_ID = ?')->execute([$archived_by, $employee_id]);
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'Employee Archive', ?)");
        $logDetails = "{$actorName} archived employee: {$employee_name}" . (!empty($reason) ? " with reason: {$reason}" : '');
        $logStmt->execute([$archived_by, $logDetails]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Employee {$employee_name} has been successfully archived"]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        error_log('Error archiving employee: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while archiving the employee']);
    }
}

function getGuardLocations() {
    global $conn;
    try {
        $stmt = $conn->prepare('SELECT DISTINCT location_name FROM guard_locations ORDER BY location_name');
        $stmt->execute();
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'locations' => $locations]);
    } catch (Exception $e) {
        error_log('Error fetching guard locations: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching locations']);
    }
}

function checkEmployeeIdDuplicate() {
    global $conn;
    try {
        // Ensure we have a database connection
        if (!$conn) {
            echo json_encode(['duplicate' => false, 'message' => 'Database connection error']);
            return;
        }
        
        $employee_id = $_POST['employee_id'] ?? '';
        $exclude_user_id = $_POST['exclude_user_id'] ?? null;
        
        if (empty($employee_id)) {
            echo json_encode(['duplicate' => false, 'message' => 'Employee ID is required']);
            return;
        }
        
        $sql = "SELECT COUNT(*) as count FROM users WHERE employee_id = ?";
        $params = [$employee_id];
        
        if ($exclude_user_id) {
            $sql .= " AND User_ID != ?";
            $params[] = $exclude_user_id;
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['duplicate' => false, 'message' => 'Database prepare error']);
            return;
        }
        
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result === false) {
            echo json_encode(['duplicate' => false, 'message' => 'Database fetch error']);
            return;
        }
        
        echo json_encode([
            'duplicate' => $result['count'] > 0, 
            'message' => $result['count'] > 0 ? 'This Employee ID is already taken' : 'Employee ID is available'
        ]);
    } catch (Exception $e) {
        error_log('Error checking employee ID duplicate: ' . $e->getMessage());
        echo json_encode(['duplicate' => false, 'message' => 'Error checking employee ID: ' . $e->getMessage()]);
    }
}

function checkGovtIdDuplicate() {
    global $conn;
    try {
        $idType = $_POST['id_type'] ?? '';
        $idValue = $_POST['id_value'] ?? '';
        $userId = $_POST['user_id'] ?? null;
        if ($idType === '' || $idValue === '') { echo json_encode(['duplicate'=>false]); return; }
        $columnMap = [ 'sss' => 'sss_number', 'tin' => 'tin_number', 'philhealth' => 'philhealth_number', 'pagibig' => 'pagibig_number' ];
        if (!isset($columnMap[$idType])) { echo json_encode(['duplicate'=>false,'message'=>'Invalid ID type']); return; }
        $column = $columnMap[$idType];
        $sql = "SELECT COUNT(*) as count FROM govt_details WHERE {$column} = ?";
        $params = [$idValue];
        if ($userId) { $sql .= ' AND user_id != ?'; $params[] = $userId; }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['duplicate' => $result['count'] > 0, 'message' => $result['count'] > 0 ? 'This ID number is already registered' : 'ID number available']);
    } catch (Exception $e) {
        error_log('Error checking government ID duplicate: ' . $e->getMessage());
        echo json_encode(['duplicate' => false, 'message' => 'Error checking ID duplicate']);
    }
}

// Validators
function validatePhilippinePhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    return preg_match('/^09\d{9}$/', $digits);
}
function validateEmail($email) {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }
    if (strpos($email, '..') !== false || strpos($email, '@.') !== false || strpos($email, '.@') !== false || substr($email, 0, 1) === '.' || substr($email, -1) === '.') { return false; }
    $allowedDomains = [ 'gmail.com','yahoo.com','outlook.com','hotmail.com','live.com','icloud.com','protonmail.com','aol.com','mail.com','yandex.com','zoho.com' ];
    $domain = substr(strrchr($email, '@'), 1);
    return in_array($domain, $allowedDomains, true);
}
function validateAge($birthDate) {
    $birth = new DateTime($birthDate); $today = new DateTime(); return $today->diff($birth)->y >= 18;
}
/**
 * Check if a government ID is a placeholder value (empty, "0", or all zeros)
 */
function isPlaceholderGovtId($idValue) {
    if (empty($idValue)) {
        return true;
    }
    
    $cleaned = preg_replace('/\D/', '', $idValue);
    
    // Check if it's just "0" or all zeros
    return $cleaned === '0' || (strlen($cleaned) > 0 && str_repeat('0', strlen($cleaned)) === $cleaned);
}
function checkGovtIdExists($conn, $idType, $idValue, $excludeUserId = null) {
    $columnMap = [ 'sss' => 'sss_number', 'tin' => 'tin_number', 'philhealth' => 'philhealth_number', 'pagibig' => 'pagibig_number' ];
    if (!isset($columnMap[$idType])) { return false; }
    $column = $columnMap[$idType];
    $sql = "SELECT COUNT(*) as count FROM govt_details WHERE {$column} = ?";
    $params = [$idValue];
    if ($excludeUserId) { $sql .= ' AND user_id != ?'; $params[] = $excludeUserId; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_';
    $password = '';
    for ($i = 0; $i < $length; $i++) { $password .= $characters[random_int(0, strlen($characters) - 1)]; }
    return $password;
}
?>
