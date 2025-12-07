<?php
// admin/update_section.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/db.php');
require_once('../includes/auth_check.php');
require_once('../includes/functions.php');

// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin role required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. POST required.']);
    exit;
}

// ✅ Input validation
$adviser_id = intval($_POST['adviser_id'] ?? 0);
$section_id = intval($_POST['section_id'] ?? 0);
$action = $_POST['action'] ?? 'assign';

if ($adviser_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid adviser ID.']);
    exit;
}

// For assign action, section_id is required
if ($action === 'assign' && $section_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid section ID.']);
    exit;
}

// 🧾 Check if adviser exists and is active
$check = $conn->prepare("
    SELECT a.id, u.full_name, u.username, s.section_name as current_section
    FROM advisers a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN sections s ON s.adviser_id = a.id
    WHERE a.id = ? AND u.is_active = 1 AND u.is_approved = 1
");
$check->bind_param("i", $adviser_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Adviser not found or not active.']);
    $check->close();
    $conn->close();
    exit;
}

$adviser = $result->fetch_assoc();
$check->close();

// 🧾 Check if section exists (for assign action)
if ($action === 'assign') {
    $section_check = $conn->prepare("
        SELECT id, section_code, section_name, level, grade_level, adviser_id
        FROM sections 
        WHERE id = ? AND academic_year = '2024-2025'
    ");
    $section_check->bind_param("i", $section_id);
    $section_check->execute();
    $section_result = $section_check->get_result();
    
    if ($section_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Section not found.']);
        $section_check->close();
        $conn->close();
        exit;
    }
    
    $section = $section_result->fetch_assoc();
    $section_check->close();
    
    // Check if section is already assigned to another adviser
    if ($section['adviser_id'] && $section['adviser_id'] != $adviser_id) {
        $current_adviser_check = $conn->prepare("
            SELECT u.full_name 
            FROM advisers a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.id = ?
        ");
        $current_adviser_check->bind_param("i", $section['adviser_id']);
        $current_adviser_check->execute();
        $current_result = $current_adviser_check->get_result();
        $current_adviser = $current_result->fetch_assoc();
        $current_adviser_check->close();
        
        echo json_encode([
            'success' => false, 
            'message' => "Section is already assigned to: " . ($current_adviser['full_name'] ?? 'Another adviser')
        ]);
        $conn->close();
        exit;
    }
}

// 🛠 Update section assignment
if ($action === 'assign') {
    // Start transaction for atomic update
    $conn->begin_transaction();
    
    try {
        // 1. Clear previous assignment from other sections
        $clear_stmt = $conn->prepare("UPDATE sections SET adviser_id = NULL WHERE adviser_id = ?");
        $clear_stmt->bind_param("i", $adviser_id);
        $clear_stmt->execute();
        
        // 2. Assign new section
        $assign_stmt = $conn->prepare("UPDATE sections SET adviser_id = ? WHERE id = ?");
        $assign_stmt->bind_param("ii", $adviser_id, $section_id);
        
        if (!$assign_stmt->execute()) {
            throw new Exception("Failed to assign section: " . $assign_stmt->error);
        }
        
        // 3. Log the action
        $action_summary = "Assigned section {$section['section_name']} to adviser {$adviser['full_name']}";
        logAction($_SESSION['user_id'], 'ASSIGN_SECTION', $action_summary, 'sections', $section_id, [
            'adviser_id' => $adviser_id,
            'adviser_name' => $adviser['full_name'],
            'section_id' => $section_id,
            'section_name' => $section['section_name'],
            'previous_section' => $adviser['current_section']
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully assigned {$section['section_name']} to {$adviser['full_name']}.",
            'data' => [
                'adviser_name' => $adviser['full_name'],
                'section_name' => $section['section_name'],
                'section_code' => $section['section_code'],
                'level' => $section['level'],
                'grade_level' => $section['grade_level']
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    
    $assign_stmt->close();
    $clear_stmt->close();
    
} elseif ($action === 'unassign') {
    // Unassign all sections from this adviser
    $unassign_stmt = $conn->prepare("UPDATE sections SET adviser_id = NULL WHERE adviser_id = ?");
    $unassign_stmt->bind_param("i", $adviser_id);
    
    if ($unassign_stmt->execute()) {
        // Log the action
        $action_summary = "Unassigned all sections from adviser {$adviser['full_name']}";
        logAction($_SESSION['user_id'], 'UNASSIGN_SECTION', $action_summary, 'advisers', $adviser_id, [
            'adviser_id' => $adviser_id,
            'adviser_name' => $adviser['full_name'],
            'previous_section' => $adviser['current_section']
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully unassigned all sections from {$adviser['full_name']}.",
            'data' => [
                'adviser_name' => $adviser['full_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unassign sections: ' . $unassign_stmt->error]);
    }
    
    $unassign_stmt->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}

$conn->close();
?>