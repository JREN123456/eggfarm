<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password_input = trim($_POST['password']); // The plain text password from the form

    // 1. Select the user by username and status only (Do not check the password in SQL)
    $sql = "SELECT id, fullname, username, role, password FROM users WHERE username = ? AND status = 'active' LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            $hashed_password_db = $row['password']; // The securely hashed password from your DB

            // 2. Use password_verify() to safely check the hash
            if (password_verify($password_input, $hashed_password_db)) {
                
                // Password is correct! Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['username'] = $row['username']; 
                $_SESSION['role'] = $row['role'];

                // --- REMEMBER ME FUNCTIONALITY ---
                if (isset($_POST['remember'])) {
                    // Set cookie for 30 days (30 days * 24 hours * 60 mins * 60 secs)
                    $cookie_expiration = time() + (30 * 24 * 60 * 60); 
                    // Stores the username safely using HttpOnly flag to prevent XSS reading it
                    setcookie('remember_user', $row['username'], $cookie_expiration, "/", "", false, true);
                } else {
                    // Unchecked: clear the cookie immediately by setting its expiration to the past
                    setcookie('remember_user', '', time() - 3600, "/");
                }
                // ---------------------------------

                // Role-based routing
                if ($row['role'] == "owner") {
                    header("Location: owner_dashboard.php");
                    exit();
                } elseif ($row['role'] == "manager") {
                    header("Location: manager_panel.php");
                    exit();
                } else {
                    header("Location: dashboard.php");
                    exit();
                }
                
            } else {
                // Password did not match the hash
                $_SESSION['login_error'] = "Incorrect password.";
                header("Location: login.php");
                exit();
            }
        } else {
            // Username doesn't exist or status is not 'active'
            $_SESSION['login_error'] = "Username not found or account is inactive.";
            header("Location: login.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['login_error'] = "Database error. Please try again later.";
        header("Location: login.php");
        exit();
    }
}
mysqli_close($conn);
?>