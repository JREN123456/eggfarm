<?php
// Start session to safely manage any state across pages if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Enforcement: Kick back to logging panel if user identifier isn't tracked
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include your database configuration
include 'connection.php'; 

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Customer';

// Status message variables for client-side alerts
$success_msg = "";
$error_msg = "";

// --- 1. FETCH CURRENT USER INFORMATION TO POPULATE THE FORM ---
$query = "SELECT fullname, username, email FROM users WHERE id = ? LIMIT 1";
if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($user = mysqli_fetch_assoc($result)) {
        $db_fullname = $user['fullname'];
        $db_username = $user['username'];
        $db_email    = $user['email'];
        
        // Split fullname back into First and Last name structures for the UI
        $name_parts = explode(" ", $db_fullname, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';
    }
    mysqli_stmt_close($stmt);
}

// --- 2. HANDLE SINGLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_profile_changes'])) {
    
    // Collect and sanitize incoming inputs
    $first_name_input = trim($_POST['first_name'] ?? '');
    $last_name_input  = trim($_POST['last_name'] ?? '');
    $username_input   = trim($_POST['username'] ?? '');
    $email_input      = trim($_POST['email'] ?? '');
    
    // Password input collection
    $current_pass     = $_POST['current_password'] ?? '';
    $new_pass         = $_POST['new_password'] ?? '';
    $confirm_pass     = $_POST['confirm_password'] ?? '';
    
    // Combine inputs back into a single record format matching registration layout
    $new_fullname = $first_name_input . " " . $last_name_input;

    // Basic Validation
    if (empty($first_name_input) || empty($username_input) || empty($email_input)) {
        $error_msg = "First Name, Username, and Email are required.";
    } else {
        
        // Process Profile text updates first
        $update_sql = "UPDATE users SET fullname = ?, username = ?, email = ? WHERE id = ?";
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "sssi", $new_fullname, $username_input, $email_input, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_msg = "Profile changes saved successfully!";
                
                // Refresh local variables for rendering consistency
                $first_name  = $first_name_input;
                $last_name   = $last_name_input;
                $db_username = $username_input;
                $db_email    = $email_input;
                
                // --- OPTIONAL PASSWORD UPDATE LOGIC ---
                // Only process password changes if the user typed inside the password fields
                if (!empty($current_pass) || !empty($new_pass) || !empty($confirm_pass)) {
                    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                        $error_msg = "Profile updated, but password fields must all be completed to change password.";
                    } elseif ($new_pass !== $confirm_pass) {
                        $error_msg = "Profile updated, but new passwords do not match.";
                    } elseif (strlen($new_pass) < 8) {
                        $error_msg = "Profile updated, but new password must be at least 8 characters long.";
                    } else {
                        // Verify current password hash matches
                        $pass_sql = "SELECT password FROM users WHERE id = ? LIMIT 1";
                        if ($pass_stmt = mysqli_prepare($conn, $pass_sql)) {
                            mysqli_stmt_bind_param($pass_stmt, "i", $user_id);
                            mysqli_stmt_execute($pass_stmt);
                            $pass_result = mysqli_stmt_get_result($pass_stmt);
                            
                            if ($pass_row = mysqli_fetch_assoc($pass_result)) {
                                if (password_verify($current_pass, $pass_row['password'])) {
                                    // Securely hash the new entry
                                    $new_hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
                                    
                                    $change_sql = "UPDATE users SET password = ? WHERE id = ?";
                                    if ($change_stmt = mysqli_prepare($conn, $change_sql)) {
                                        mysqli_stmt_bind_param($change_stmt, "si", $new_hashed_password, $user_id);
                                        if (mysqli_stmt_execute($change_stmt)) {
                                            $success_msg = "Profile and password updated successfully!";
                                        } else {
                                            $error_msg = "Profile updated, but failed to change database password entry.";
                                        }
                                        mysqli_stmt_close($change_stmt);
                                    }
                                } else {
                                    $error_msg = "Profile updated, but current password input was incorrect.";
                                }
                            }
                            mysqli_stmt_close($pass_stmt);
                        }
                    }
                }
            } else {
                $error_msg = "Failed to update profile details. Check if the Username/Email is taken.";
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen">
        
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
            <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <img src="vdvc.png" alt="Logo" class="w-50 h-50 object-contain mb-2">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
                <span class="mt-1 text-[10px] bg-sky-600 px-2 py-0.5 rounded-full font-semibold uppercase tracking-wider text-sky-100">
                    <?= htmlspecialchars($user_role) ?> Panel
                </span>
            </div>
            
            <nav class="flex-1 p-4 space-y-2 mt-4">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-chart-pie w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reservation.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-calendar-check w-5"></i>
                    <span>Reservation</span>
                </a>
                <a href="view.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i>
                    <span>Reservation History</span>
                </a>
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600/40 transition font-medium">
                    <i class="fa-solid fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                
                <hr class="border-sky-400/30 my-2">
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-red-600/80 transition font-medium text-sky-100 hover:text-white">
                    <i class="fa-solid fa-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </nav>
            
            <div class="p-4 text-center text-xs text-sky-200 border-t border-sky-400/30">
                &copy; 2026 Egg Reservation Systems
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-slate-800">👤 My Profile</h1>
            </header>

            <div class="max-w-6xl w-full mx-auto p-6">
                <?php if(!empty($success_msg)): ?>
                    <div class="mb-4 p-4 bg-emerald-100 border border-emerald-400 text-emerald-700 rounded-lg font-medium">
                        <i class="fa-solid fa-circle-check mr-2"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>
                <?php if(!empty($error_msg)): ?>
                    <div class="mb-4 p-4 bg-rose-100 border border-rose-400 text-rose-700 rounded-lg font-medium">
                        <i class="fa-solid fa-circle-xmark mr-2"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <form id="unifiedProfileForm" action="profile.php" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-lg shadow-sm border border-gray-200 space-y-6">
                    
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Update Profile Details</h2>
                        <p class="text-xs text-gray-500 mb-4 font-semibold">Account Identity & Contact Information</p>
                    </div>

                    <div class="flex items-center space-x-4 mb-2">
                        <div class="w-20 h-20 rounded-full overflow-hidden border border-gray-300 relative group">
                            <img id="avatarPreview" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150" alt="Profile Photo" class="w-full h-full object-cover">
                        </div>
                        <label class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-medium py-1.5 px-4 rounded text-sm transition">
                            Edit Photo
                            <input type="file" id="avatarInput" name="profile_photo" accept="image/*" class="hidden">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">First Name</label>
                            <div class="relative">
                                <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Last Name</label>
                            <div class="relative">
                                <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
                            <div class="relative">
                                <input type="text" name="username" value="<?= htmlspecialchars($db_username) ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                            <div class="relative">
                                <input type="email" name="email" value="<?= htmlspecialchars($db_email) ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 my-6">

                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
                        <p class="text-xs text-gray-500 mb-4 font-semibold">Leave empty if you do not wish to change your security credentials</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">New Password</label>
                            <div class="relative">
                                <input type="password" id="newPassword" name="new_password" placeholder="••••••••" minlength="8" class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="confirm_password" placeholder="••••••••" class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                            <span id="matchMessage" class="text-xs block mt-1 hidden"></span>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" name="save_profile_changes" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded text-base tracking-wide shadow transition">
                            Save Account Changes
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Password Visibility Toggle Logic
            const toggleButtons = document.querySelectorAll('.toggle-password');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('.password-input');
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Avatar Photo Dynamic Preview Engine
            const avatarInput = document.getElementById('avatarInput');
            const avatarPreview = document.getElementById('avatarPreview');
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) { avatarPreview.src = e.target.result; }
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Password Match Real-time Client Verification
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const matchMessage = document.getElementById('matchMessage');

            function checkPasswords() {
                if (!confirmPassword.value) {
                    matchMessage.classList.add('hidden');
                    return;
                }
                matchMessage.classList.remove('hidden');
                if (newPassword.value === confirmPassword.value) {
                    matchMessage.textContent = "Passwords match";
                    matchMessage.className = "text-xs block mt-1 text-green-600 font-medium";
                    confirmPassword.setCustomValidity("");
                } else {
                    matchMessage.textContent = "Passwords do not match";
                    matchMessage.className = "text-xs block mt-1 text-red-500 font-medium";
                    confirmPassword.setCustomValidity("Passwords must match");
                }
            }

            if (newPassword && confirmPassword) {
                newPassword.addEventListener('input', checkPasswords);
                confirmPassword.addEventListener('input', checkPasswords);
            }
        });
    </script>
</body>
</html>

📋 My Reservation History