<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f0f7fc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px 0;
        }

        .register-card {
            background: #ffffff;
            width: 100%;
            max-width: 360px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
        }

        .logo-container {
            margin-bottom: 25px;
        }
        
        .logo-img {
            width: 100px;
            height: auto;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .system-title {
            color: #1e4473;
            font-size: 19px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }

        .subtitle {
            color: #5c6b73;
            font-size: 12px;
            font-weight: 500;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group {
            position: relative;
            margin-bottom: 16px;
        }

        .form-group i.input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8fa0a6;
            font-size: 16px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8fa0a6;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #1e4473;
        }

        .form-control {
            width: 100%;
            padding: 12px 40px 12px 45px;
            border: 1.5px solid #d0dfeb;
            border-radius: 10px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #5294e2;
            box-shadow: 0 0 5px rgba(82, 148, 226, 0.3);
        }

        .form-control::placeholder {
            color: #a0b0b5;
        }

        .btn-submit {
            width: 100%;
            background-color: #1e4473;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(30, 68, 115, 0.2);
            transition: background-color 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: #153256;
        }

        .notification {
            visibility: hidden;
            min-width: 280px;
            background-color: #2ec4b6; /* Dynamic fallback */
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 12px;
            position: fixed;
            z-index: 1000;
            left: 50%;
            top: 30px;
            transform: translateX(-50%);
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            opacity: 0;
            transition: opacity 0.5s, top 0.5s, visibility 0.5s;
        }

        .notification.show {
            visibility: visible;
            opacity: 1;
            top: 50px;
        }

        /* Added alert themes for visual feedback */
        .notification.success { background-color: #2ec4b6; }
        .notification.error { background-color: #e63946; }

        .form-footer {
            margin-top: 25px;
            font-size: 12px;
            color: #4a5568;
        }

        .form-footer a {
            color: #1e4473;
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div id="toastNotification" class="notification"></div>

<div class="register-card">
    <div class="logo-container">
        <img src="/EggFarm/vdvc.png" alt="VDVC Logo" class="logo-img">
        <h1 class="system-title">Customer Registration</h1>
        <div class="subtitle">Poultry Farm Management</div>
    </div>

    <form id="registrationForm" action="register_process.php" method="POST">
        <div class="form-group">
            <i class="fa-regular fa-address-card input-icon"></i>
            <input type="text" name="fullname" class="form-control" placeholder="Full Name" required>
        </div>

        <div class="form-group">
            <i class="fa-regular fa-user input-icon"></i>
            <input type="text" name="username" class="form-control" placeholder="Username" required>
        </div>

        <div class="form-group">
            <i class="fa-regular fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
        </div>

        <div class="form-group">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" name="password" id="passwordField" class="form-control" placeholder="Password" required>
            <i class="fa-regular fa-eye toggle-password" id="togglePasswordIcon"></i>
        </div>

        <button type="submit" class="btn-submit">Register</button>
    </form>

    <div class="form-footer">
        Already have an account? <a href="login.php">Log In</a>
    </div>
</div>

<script>
    // 1. SHOW/HIDE PASSWORD FUNCTIONALITY
    const passwordField = document.getElementById('passwordField');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');

    togglePasswordIcon.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // 2. AJAX SUBMISSION WITH RESPONSE VALIDATION
    const registrationForm = document.getElementById('registrationForm');
    const toastNotification = document.getElementById('toastNotification');

    function showToast(message, isSuccess) {
        toastNotification.textContent = message;
        toastNotification.className = 'notification show ' + (isSuccess ? 'success' : 'error');
        
        setTimeout(() => {
            toastNotification.classList.remove('show');
        }, 3000);
    }

    registrationForm.addEventListener('submit', function (e) {
        e.preventDefault(); 
        const formData = new FormData(this);

        fetch('register_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Check exactly what the server responded with
            if (data.trim() === "Registration Successful") {
                showToast("Registered Successfully!", true);
                registrationForm.reset();
                
                // Clear password states
                passwordField.setAttribute('type', 'password');
                togglePasswordIcon.classList.add('fa-eye');
                togglePasswordIcon.classList.remove('fa-eye-slash');
            } else {
                showToast(data || "Registration failed. Try again.", false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast("An error occurred during submission.", false);
        });
    });
</script>

</body>
</html>