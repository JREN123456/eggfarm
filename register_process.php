<?php
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize incoming inputs
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password_raw = trim($_POST['password']);
    
    // Default system values for new registrations
    $role   = "customer"; // Based on your 'Customer Registration' title
    $status = "active";   // Matches the status check in login_process.php

    // 1. Basic input validation
    if (empty($fullname) || empty($username) || empty($email) || empty($password_raw)) {
        echo "All fields are required.";
        exit();
    }

    // 2. Check if Username or Email already exists to prevent duplicate entries
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
    if ($stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo "Username or Email already registered.";
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_close($stmt);
    }

    // 3. SECURELY HASH PASSWORD (Works perfectly with password_verify)
    $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

    // 4. Insert user record via Prepared Statements
    $insert_sql = "INSERT INTO users (fullname, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $insert_sql)) {
        mysqli_stmt_bind_param($stmt, "ssssss", $fullname, $username, $email, $hashed_password, $role, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            // This exact text triggers the success toast in register.php JavaScript
            echo "Registration Successful"; 
        } else {
            echo "Failed to save user. Please try again.";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Database structure error. Please try again later.";
    }
}
mysqli_close($conn);
?>