<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login & Signup</title>
    <link rel="stylesheet" href="styles.css">
    <script src="scripts.js" defer></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            background-image: url("images/login.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .wrapper {
            width: 90%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 30px 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-left: 500px;
            z-index: 1;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 2000%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }
        @media (max-width: 768px) {
            .wrapper {
                padding: 20px;
                width: 85%;
                margin-left: 0;
            }
            .title-text .title {
                font-size: 28px;
            }
            .field input {
                padding: 12px 15px;
            }
        }
        @media (max-width: 480px) {
            .wrapper {
                width: 90%;
                padding: 15px;
                margin-left: 0;
            }
            .title-text .title {
                font-size: 24px;
            }
            .pass-link a,
            .signup-link a {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <div class="title-text">
            <div class="title login">Login Form</div>
            <div class="title signup">Signup Form</div>
        </div>
        <div class="form-container">
            <div class="slide-controls">
                <input type="radio" name="slide" id="login" checked>
                <input type="radio" name="slide" id="signup">
                <label for="login" class="slide login">Login</label>
                <label for="signup" class="slide signup">Signup</label>
                <div class="slider-tab"></div>
            </div>
            <div class="form-inner">
                <!-- Login Form -->
                <form method="POST" action="auth.php" class="login <?php echo (!isset($_SESSION['show_forgot_form']) && !isset($_SESSION['email_verified'])) ? 'active' : ''; ?>">
                    <div class="field">
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    <div class="field">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="pass-link"><a href="#" onclick="showForgotForm()">Forgot password?</a></div>
                    <div class="field btn">
                        <div class="btn-layer"></div>
                        <input type="submit" name="login" value="Login">
                    </div>
                    <div class="signup-link">Not a member? <a href="#" onclick="document.getElementById('signup').click();">Signup now</a></div>
                </form>
                <!-- Forgot Password Email Verification Form -->
                <form method="POST" action="auth.php" class="login forgot-email <?php echo (isset($_SESSION['show_forgot_form']) && !isset($_SESSION['email_verified'])) ? 'active' : ''; ?>">
                    <div class="field">
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    <div class="field btn">
                        <div class="btn-layer"></div>
                        <input type="submit" name="check_email" value="Verify Email">
                    </div>
                    <div class="pass-link"><a href="#" onclick="showLoginForm()">Back to Login</a></div>
                </form>
                <!-- Change Password Form -->
                <form method="POST" action="auth.php" class="login change-password <?php echo (isset($_SESSION['email_verified']) && $_SESSION['email_verified']) ? 'active' : ''; ?>">
                    <div class="field">
                        <input type="password" name="new_password" placeholder="New Password" required>
                    </div>
                    <div class="field">
                        <input type="password" name="confirm_new_password" placeholder="Confirm New Password" required>
                    </div>
                    <div class="field btn">
                        <div class="btn-layer"></div>
                        <input type="submit" name="change_password" value="Change Password">
                    </div>
                    <div class="pass-link"><a href="#" onclick="showLoginForm()">Back to Login</a></div>
                </form>
                <!-- Signup Form -->
                <form method="POST" action="auth.php" class="signup">
                    <div class="field">
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    <div class="field">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="field">
                        <input type="password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                    <div class="field btn">
                        <div class="btn-layer"></div>
                        <input type="submit" name="signup" value="Signup">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function showForgotForm() {
            window.location.href = 'auth.php?action=show_forgot_form';
        }

        function showLoginForm() {
            window.location.href = 'auth.php?action=show_login_form';
        }

        document.addEventListener("DOMContentLoaded", function () {
            const loginForm = document.querySelector("form.login:not(.forgot-email):not(.change-password)");
            const forgotEmailForm = document.querySelector("form.login.forgot-email");
            const changePasswordForm = document.querySelector("form.login.change-password");
            const signupForm = document.querySelector("form.signup");

            [loginForm, forgotEmailForm, changePasswordForm, signupForm].forEach(form => {
                if (form) form.style.display = "none";
            });

            <?php if (isset($_SESSION['show_forgot_form']) && !isset($_SESSION['email_verified'])): ?>
                if (forgotEmailForm) forgotEmailForm.style.display = "block";
            <?php elseif (isset($_SESSION['email_verified']) && $_SESSION['email_verified']): ?>
                if (changePasswordForm) changePasswordForm.style.display = "block";
            <?php else: ?>
                if (loginForm) loginForm.style.display = "block";
            <?php endif; ?>
        });
    </script>
</body>
</html>