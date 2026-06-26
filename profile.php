<?php
// Start session to safely manage any state across pages if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $first_name = htmlspecialchars($_POST['first_name'] ?? '');
        $last_name  = htmlspecialchars($_POST['last_name'] ?? '');
        $email      = htmlspecialchars($_POST['email'] ?? '');
        $phone      = htmlspecialchars($_POST['phone_number'] ?? '');
        $address    = htmlspecialchars($_POST['address'] ?? '');
        
        // Back-end validation / DB configuration goes here
    }
    
    if (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        
        // Back-end safety encryption and execution goes here
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

    <!-- Parent Wrapper for Sidebar layout -->
    <div class="flex min-h-screen">
        
        <!-- Left Navigation Bar -->
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
            <!-- Top Picture Logo -->
            <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <img src="vdvc.png" alt="Logo" class="w-50 h-50">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
            </div>
            
            <!-- Navigation Links -->
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
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600 font-semibold shadow-inner transition">
                    <i class="fa-solid fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
            </nav>
            
            <div class="p-4 text-center text-xs text-sky-200 border-t border-sky-400/30">
                &copy; 2026 Egg Reservation Systems
            </div>
        </aside>

        <!-- Right Side Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">

            <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-slate-800">👤 My Profile</h1>
            </header>

            <div class="max-w-5xl w-full mx-auto p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Update Profile Card -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Update Profile</h2>
                    <p class="text-xs text-gray-500 mb-6 font-semibold">Account Details & Contact Information</p>
                    
                    <form id="profileForm" action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-20 h-20 rounded-full overflow-hidden border border-gray-300 relative group">
                                <img id="avatarPreview" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150" alt="Profile Photo" class="w-full h-full object-cover">
                            </div>
                            <label class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-medium py-1.5 px-4 rounded text-sm transition">
                                Edit Photo
                                <input type="file" id="avatarInput" name="profile_photo" accept="image/*" class="hidden">
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">First Name</label>
                                <div class="relative">
                                    <input type="text" name="first_name" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Last Name</label>
                                <div class="relative">
                                    <input type="text" name="last_name" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                            <div class="relative">
                                <input type="email" name="email" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Phone Number</label>
                            <div class="relative">
                                <input type="text" name="phone_number" placeholder="(09XX) XXX-XXXX" class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 top-3 text-xs pointer-events-none"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Delivery Address</label>
                            <div class="relative flex border border-gray-300 rounded overflow-hidden">
                                <textarea name="address" rows="2" class="w-2/3 p-3 text-sm resize-none border-r border-gray-200 focus:outline-none" placeholder="Enter delivery address..."></textarea>
                                
                                <div class="w-1/3 bg-blue-50 relative flex items-center justify-center overflow-hidden">
                                    <img src="https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/121.03,14.58,14,0/150x80?access_token=mock" alt="Map Mock" class="w-full h-full object-cover opacity-60 onerror-hide" onerror="this.src='https://placehold.co/150x80?text=Map+Preview'">
                                    <div class="absolute text-red-500 text-lg"><i class="fa-solid fa-location-dot"></i></div>
                                </div>
                                
                                <i class="fa-solid fa-pencil text-gray-400 absolute right-3 bottom-3 text-xs bg-white p-1 rounded shadow-sm pointer-events-none"></i>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="update_profile" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded text-base tracking-wide shadow transition">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Card -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 h-fit">
                    <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
                    <p class="text-xs text-gray-500 mb-6 font-semibold">Security Settings - Change Password</p>
                    
                    <form id="passwordForm" action="profile.php" method="POST" class="space-y-4">
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">New Password</label>
                            <div class="relative">
                                <input type="password" id="newPassword" name="new_password" placeholder="••••••••••••" minlength="8" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 font-medium">Minimum 8 characters</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="confirm_password" placeholder="••••••••••••" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm pr-10 focus:outline-none focus:ring-1 focus:ring-blue-500 password-input">
                                <button type="button" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 toggle-password">
                                    <i class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                            <span id="matchMessage" class="text-xs block mt-1 hidden"></span>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="change_password" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded text-base tracking-wide shadow transition">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>
            
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Password Visibility Toggle Logic
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

            // 2. Avatar Photo Dynamic Preview Engine
            const avatarInput = document.getElementById('avatarInput');
            const avatarPreview = document.getElementById('avatarPreview');

            avatarInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatarPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });

            // 3. Password Match Real-time Client Verification
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

            newPassword.addEventListener('input', checkPasswords);
            confirmPassword.addEventListener('input', checkPasswords);
        });
    </script>
</body>
</html>