<?php 
session_start(); 

// Check if the "Remember me" cookie exists
$remembered_username = "";
$remember_checked = "";

if (isset($_COOKIE['remember_user'])) {
    $remembered_username = htmlspecialchars($_COOKIE['remember_user']);
    $remember_checked = "checked";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=400;500;600;700&display=swap" rel="stylesheet">
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
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 360px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
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

        /* Error Alert Box */
        .alert-danger {
            background-color: #fce8e6;
            color: #a80000;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 15px;
            border: 1px solid #f5c2c2;
            text-align: center;
        }

        .form-group {
            position: relative;
            margin-bottom: 16px;
        }

        .form-group > i:not(.toggle-password) {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8fa0a6;
            font-size: 16px;
            pointer-events: none;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8fa0a6;
            font-size: 16px;
            cursor: pointer;
            transition: color 0.2s ease;
            z-index: 2;
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

        .form-utilities {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #4a5568;
            margin-bottom: 25px;
            padding: 0 2px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .remember-me input {
            cursor: pointer;
            accent-color: #1e4473;
        }

        .forgot-link {
            color: #4a5568;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
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
        }

        .btn-submit:hover {
            background-color: #153256;
        }

        .form-footer {
            margin-top: 20px;
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

<div class="login-card">
    
    <div class="logo-container">
        <img src="/EggFarm/vdvc.png" alt="VDVC Logo" class="logo-img">
        <h1 class="system-title">Egg Farm<br>Management System</h1>
    </div>

    <?php if (isset($_SESSION['login_error'])): ?>
        <div class="alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
        </div>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
        
        <div class="form-group">
            <i class="fa-regular fa-user"></i>
            <input type="text" 
                   name="username" 
                   class="form-control" 
                   placeholder="Username" 
                   value="<?php echo $remembered_username; ?>"
                   required>
        </div>

        <div class="form-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" 
                   name="password" 
                   id="password"
                   class="form-control" 
                   placeholder="Password" 
                   required>
            <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <div class="form-utilities">
            <label class="remember-me">
                <input type="checkbox" name="remember" <?php echo $remember_checked; ?>> Remember me
            </label>
            <a href="#" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit">Log In</button>

    </form>

    <div class="form-footer">
        Don't have an account? <a href="register.php">Sign Up</a>
    </div>

</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>