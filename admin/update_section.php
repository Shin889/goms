<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ✅ Input validation
$adviser_id = intval($_POST['adviser_id'] ?? 0);
$section = trim($_POST['section'] ?? '');

if ($adviser_id <= 0 || $section === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid adviser or section.']);
    exit;
}

// 🧾 Check if adviser exists
$check = $conn->prepare("SELECT id, section FROM advisers WHERE id = ?");
$check->bind_param("i", $adviser_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Adviser not found.']);
    $check->close();
    $conn->close();
    exit;
}

$current = $result->fetch_assoc();
$check->close();

// 🛠 Skip if no change
if ($current['section'] === $section) {
    echo json_encode(['success' => true, 'message' => 'No changes detected.']);
    $conn->close();
    exit;
}

// 🧩 Update section
$stmt = $conn->prepare("UPDATE advisers SET section = ? WHERE id = ?");
$stmt->bind_param("si", $section, $adviser_id);

if ($stmt->execute()) {
    // ✅ Log admin action
    $log = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, target_table, target_id, details, ip_address)
        VALUES (?, 'update_section', 'advisers', ?, ?, ?)
    ");
    $details = "Section changed to '$section'";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log->bind_param("iiss", $_SESSION['user_id'], $adviser_id, $details, $ip);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true, 'message' => 'Section updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
