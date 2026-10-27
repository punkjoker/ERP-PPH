
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Lynntech Chemicals &amp; Equipment</title>

    <meta name="description" content="Secure login to the Lynntech Chemicals & Equipment Management System.">

    <link
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #071426;
        }

        .login-page {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-image:
                linear-gradient(
                    110deg,
                    rgba(3, 15, 32, 0.91),
                    rgba(5, 24, 48, 0.78),
                    rgba(3, 15, 32, 0.89)
                ),
                url("images/home.jpg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 22px 7%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
        }

        .brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #d5a83e;
            color: #071426;
            font-size: 22px;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .brand-subtitle {
            margin-top: 3px;
            color: #e1b94f;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .back-home {
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-home:hover {
            color: #e1b94f;
        }

        .main-area {
            display: flex;
            flex: 1;
            align-items: center;
            justify-content: center;
            padding: 45px 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 38px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.97);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
        }

        .login-symbol {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 65px;
            height: 65px;
            margin: 0 auto 20px;
            border-radius: 18px;
            background: #eaf1fa;
            color: #124578;
            font-size: 27px;
        }

        .heading {
            margin: 0;
            color: #10243e;
            font-size: 29px;
            font-weight: 800;
            text-align: center;
        }

        .subheading {
            margin-top: 10px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
            text-align: center;
        }

        .gold-line {
            width: 55px;
            height: 4px;
            margin: 20px auto 27px;
            border-radius: 10px;
            background: #d5a83e;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            color: #25364b;
            font-size: 13px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            color: #8291a5;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 13px 44px 13px 43px;
            border: 1px solid #d9e1eb;
            border-radius: 10px;
            outline: none;
            background: #f8fafc;
            color: #10243e;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            border-color: #124578;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(18, 69, 120, 0.12);
        }

        .form-input::placeholder {
            color: #9aa6b5;
        }

        .field-group {
            margin-bottom: 20px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            padding: 5px;
            border: none;
            background: transparent;
            color: #718096;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .password-toggle:hover {
            color: #124578;
        }

        .login-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            margin-top: 25px;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: #124578;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(18, 69, 120, 0.2);
            transition: background 0.2s, transform 0.2s;
        }

        .login-button:hover {
            background: #0b345d;
            transform: translateY(-1px);
        }

        .signup-area {
            margin-top: 25px;
            padding-top: 21px;
            border-top: 1px solid #e7edf4;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        .signup-area a {
            color: #124578;
            font-weight: 700;
            text-decoration: none;
        }

        .signup-area a:hover {
            color: #d5a83e;
            text-decoration: underline;
        }

        .security-message {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 21px;
            color: #64748b;
            font-size: 11px;
            text-align: center;
        }

        .security-message i {
            color: #16815d;
        }

        .footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.13);
            color: rgba(255, 255, 255, 0.68);
            font-size: 11px;
            text-align: center;
        }

        .footer strong {
            color: #e1b94f;
        }

        @media (max-width: 480px) {
            .topbar {
                padding: 17px 5%;
            }

            .brand-name {
                font-size: 15px;
            }

            .brand-subtitle {
                font-size: 9px;
                letter-spacing: 1px;
            }

            .brand-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .back-home {
                font-size: 12px;
            }

            .main-area {
                padding: 30px 15px;
            }

            .login-card {
                padding: 29px 23px;
                border-radius: 17px;
            }

            .heading {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="login-page">

    <!-- Header -->
    <header class="topbar">
        <a href="index.php" class="brand">
            <div class="brand-icon">
                <i class="fas fa-flask"></i>
            </div>

            <div>
                <div class="brand-name">LYNNTECH</div>
                <div class="brand-subtitle">Chemicals &amp; Equipment</div>
            </div>
        </a>

        <a href="index.php" class="back-home">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Home
        </a>
    </header>

    <!-- Login form -->
    <main class="main-area">
        <section class="login-card">

            <div class="login-symbol">
                <i class="fas fa-user-lock"></i>
            </div>

            <h1 class="heading">Welcome Back</h1>

            <p class="subheading">
                Sign in to access the Lynntech management system.
            </p>

            <div class="gold-line"></div>

            <form action="login_process.php" method="POST">

                <!-- Email -->
                <div class="field-group">
                    <label for="email" class="field-label">
                        Email Address
                    </label>

                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="Enter your email address"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="field-group">
                    <label for="password" class="field-label">
                        Password
                    </label>

                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Login button -->
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In Securely
                </button>

                <!-- Signup link -->
                <div class="signup-area">
                    Don't have an account?
                    <a href="signup.php">Create an account</a>
                </div>

            </form>

            <div class="security-message">
                <i class="fas fa-shield-alt"></i>
                <span>Secure access for authorised personnel</span>
            </div>

        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        &copy; <?php echo date('Y'); ?>
        <strong>Lynntech Chemicals &amp; Equipment</strong>.
        All rights reserved.
    </footer>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');

    toggleButton.addEventListener('click', function () {
        const showPassword = passwordInput.type === 'password';

        passwordInput.type = showPassword ? 'text' : 'password';

        eyeIcon.classList.toggle('fa-eye', !showPassword);
        eyeIcon.classList.toggle('fa-eye-slash', showPassword);

        toggleButton.setAttribute(
            'aria-label',
            showPassword ? 'Hide password' : 'Show password'
        );

        toggleButton.setAttribute(
            'aria-pressed',
            showPassword ? 'true' : 'false'
        );
    });
</script>

</body>
</html>
