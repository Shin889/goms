<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');

$counselor_id = intval($_SESSION['user_id']);
$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get counselor db id
$stmt = $conn->prepare("SELECT c.id as counselor_db_id FROM counselors c WHERE c.user_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$counselor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$counselor) die("Counselor not found.");

// Get session info with appointment and referral
$stmt = $conn->prepare("
    SELECT s.*, a.id as appointment_id, r.id as referral_id
    FROM sessions s
    LEFT JOIN appointments a ON s.appointment_id = a.id
    LEFT JOIN referrals r ON a.referral_id = r.id
    WHERE s.id = ? AND s.counselor_id = ?
");
$stmt->bind_param("ii", $session_id, $counselor['counselor_db_id']);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) die("Session not found.");

// Update all statuses
$conn->begin_transaction();
try {
    // Update session
    $stmt = $conn->prepare("UPDATE sessions SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    // Update appointment if exists
    if ($session['appointment_id']) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $session['appointment_id']);
        $stmt->execute();
    }
    
    // Update referral if exists
    if ($session['referral_id']) {
        $stmt = $conn->prepare("UPDATE referrals SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $session['referral_id']);
        $stmt->execute();
    }
    
    $conn->commit();
    $_SESSION['success'] = "Session marked as completed!";
    header("Location: sessions.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error completing session: " . $e->getMessage();
    header("Location: session_details.php?id=" . $session_id);
    exit;
}
?>