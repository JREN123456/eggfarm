<?php
require 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['id'])) {

        // This contains "1,2,3,4"
        $ids = $_POST['id'];

        // Convert to array
        $idArray = explode(',', $ids);

        // Prepare placeholders (?, ?, ?, ...)
        $placeholders = implode(',', array_fill(0, count($idArray), '?'));

        // Prepare statement
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id IN ($placeholders)");

        // Bind params dynamically
        $types = str_repeat('i', count($idArray));
        $stmt->bind_param($types, ...$idArray);

        // Execute delete
        $stmt->execute();

        $stmt->close();
    }
}

header("Location: view.php");
exit;
?>