<?php
session_start();
require 'connection.php';

// Helper function to insert notifications
function add_notification($conn, $user_id, $title, $description, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, description, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $description, $type);
    $stmt->execute();
}

if (isset($_POST['id'])) {
    $reservation_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // Perform deletion
    if (strtolower($user_role) === 'customer') {
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $reservation_id, $user_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $reservation_id);
    }

    if ($stmt->execute()) {
        // Log the cancellation notification
        add_notification($conn, $user_id, "Reservation Cancelled", "Reservation #{$reservation_id} has been removed.", "alert");
        header("Location: view.php?status=cancelled");
    }
    exit;
}
?>