<?php
include_once(__DIR__ . '/../config/db.php');

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'];
}

function logAction($user_id, $action, $target_table, $target_id = null, $details = "") {
    global $conn;
    $ip = getUserIP();
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $target_table, $target_id, $details, $ip);
    $stmt->execute();
}
?>
