<?php
// includes/auth_check.php
require_once __DIR__ . '/../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user exists and is approved/active
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 AND is_approved = 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    // User not found or not approved/active
    session_destroy();
    header("Location: ../auth/login.php?error=account_not_approved");
    exit();
}

// Store user info in session
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];

// Get current page and directory
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Define allowed directories for each role
$allowed_directories = [
    'admin' => ['admin'],
    'counselor' => ['counselor'],
    'adviser' => ['adviser'],
    'guardian' => ['guardian']
];

// Check if user is in correct directory
$user_role = $_SESSION['role'];
if (isset($allowed_directories[$user_role])) {
    $allowed_dirs = $allowed_directories[$user_role];
    if (!in_array($current_dir, $allowed_dirs) && $current_dir != 'auth' && $current_dir != 'includes') {
        // Redirect to role-specific dashboard
        header("Location: ../{$user_role}/dashboard.php");
        exit();
    }
}

// Optional: restrict page access by role (enhanced version)
function checkRole($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        // Log unauthorized access attempt
        if (isset($_SESSION['user_id'])) {
            global $conn;
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, action_summary, target_table) VALUES (?, 'UNAUTHORIZED_ACCESS', 'Attempted unauthorized access to restricted page', 'system')");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        }
        
        header("Location: ../{$_SESSION['role']}/dashboard.php?error=unauthorized_access");
        exit();
    }
}

// Function to require specific role
function requireRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header("Location: ../{$_SESSION['role']}/dashboard.php");
        exit();
    }
}

// Function to get current user's adviser ID (if applicable)
function getCurrentAdviserId() {
    if ($_SESSION['role'] !== 'adviser') return null;
    
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM advisers WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $adviser = $result->fetch_assoc();
    return $adviser ? $adviser['id'] : null;
}

// Function to get current user's counselor ID (if applicable)
function getCurrentCounselorId() {
    if ($_SESSION['role'] !== 'counselor') return null;
    
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM counselors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $counselor = $result->fetch_assoc();
    return $counselor ? $counselor['id'] : null;
}

// Function to get current user's guardian ID (if applicable)
function getCurrentGuardianId() {
    if ($_SESSION['role'] !== 'guardian') return null;
    
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM guardians WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardian = $result->fetch_assoc();
    return $guardian ? $guardian['id'] : null;
}
?>