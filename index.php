
<?php
session_start();

// If the user is already logged in, send them to the appropriate dashboard.
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    // Adjust these dashboard paths if your project uses different filenames.
    if (isset($_SESSION['admin_id'])) {
        header('Location: superadmin/dashboard.php');
        exit;
    }

    if (isset($_SESSION['user_role'])) {
        $role = strtolower(trim($_SESSION['user_role']));

        if ($role === 'hr') {
            header('Location: hr/dashboard.php');
            exit;
        }

        if ($role === 'staff') {
            header('Location: staff/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lynntech Chemicals &amp; Equipment | Management Portal</title>

    <meta
        name="description"
        content="Welcome to the Lynntech Chemicals & Equipment Management Portal."
    >

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

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #071426;
            color: #fff;
        }

        .hero {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: hidden;
            background-image:
                linear-gradient(
                    100deg,
                    rgba(3, 15, 32, 0.94) 0%,
                    rgba(5, 24, 48, 0.84) 48%,
                    rgba(3, 15, 32, 0.60) 100%
                ),
                url("images/home.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(
                    circle at 78% 35%,
                    rgba(28, 122, 190, 0.15),
                    transparent 38%
                );
        }

        .navbar {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 24px 7%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            color: white;
        }

        .brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 13px;
            background: #d5a83e;
            color: #071426;
            font-size: 23px;
            box-shadow: 0 6px 22px rgba(213, 168, 62, 0.22);
        }

        .brand-name {
            font-size: 19px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .brand-subtitle {
            margin-top: 5px;
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #d5a83e;
        }

        .nav-label {
            color: rgba(255, 255, 255, 0.75);
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .main-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex: 1;
            align-items: center;
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 65px 7%;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(310px, 0.7fr);
            align-items: center;
            gap: 75px;
            width: 100%;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding: 9px 15px;
            border: 1px solid rgba(213, 168, 62, 0.38);
            border-radius: 30px;
            background: rgba(213, 168, 62, 0.09);
            color: #f1ce79;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .eyebrow span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #d5a83e;
            box-shadow: 0 0 12px rgba(213, 168, 62, 0.8);
        }

        .hero-title {
            max-width: 760px;
            margin: 0;
            font-size: clamp(38px, 5vw, 69px);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -1.5px;
        }

        .hero-title .gold {
            color: #e1b94f;
        }

        .hero-description {
            max-width: 600px;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 17px;
            line-height: 1.9;
        }

        .feature-list {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            margin-top: 35px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 13px;
        }

        .feature i {
            color: #e1b94f;
            font-size: 15px;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            justify-self: end;
            padding: 38px 34px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.97);
            color: #17243a;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(12px);
        }

        .login-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            margin-bottom: 23px;
            border-radius: 17px;
            background: #eaf1fa;
            color: #124578;
            font-size: 25px;
        }

        .login-title {
            margin: 0;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .login-description {
            margin-top: 9px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .login-divider {
            height: 1px;
            margin: 25px 0;
            background: #e8edf3;
        }

        .login-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 16px 20px;
            border: none;
            border-radius: 10px;
            background: #124578;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 8px 18px rgba(18, 69, 120, 0.19);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .login-button:hover {
            background: #0b345d;
            color: #fff;
            transform: translateY(-2px);
        }

        .login-button .arrow {
            margin-left: auto;
            color: #e1b94f;
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 23px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        .security-note i {
            margin-top: 3px;
            color: #18845d;
        }

        .footer {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 20px 7%;
            border-top: 1px solid rgba(255, 255, 255, 0.13);
            color: rgba(255, 255, 255, 0.65);
            font-size: 11px;
        }

        .footer strong {
            color: #e1b94f;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .main-content {
                padding-top: 50px;
                padding-bottom: 50px;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 45px;
            }

            .hero-copy {
                text-align: center;
            }

            .eyebrow {
                justify-content: center;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .feature-list {
                justify-content: center;
            }

            .login-card {
                justify-self: center;
                max-width: 460px;
            }
        }

        @media (max-width: 500px) {
            .navbar {
                padding: 17px 5%;
            }

            .brand-icon {
                width: 42px;
                height: 42px;
                font-size: 19px;
            }

            .brand-name {
                font-size: 15px;
            }

            .brand-subtitle {
                font-size: 9px;
                letter-spacing: 1.5px;
            }

            .nav-label {
                display: none;
            }

            .main-content {
                padding: 40px 5%;
            }

            .hero-title {
                font-size: 39px;
                letter-spacing: -1px;
            }

            .hero-description {
                font-size: 15px;
            }

            .feature-list {
                gap: 15px;
            }

            .login-card {
                padding: 29px 23px;
            }

            .footer {
                flex-direction: column;
                text-align: center;
                padding: 18px 5%;
            }
        }
    </style>
</head>

<body>

<div class="hero">

    <!-- Navigation -->
    <header class="navbar">
        <a href="index.php" class="brand">
            <div class="brand-icon">
                <i class="fas fa-flask"></i>
            </div>

            <div>
                <div class="brand-name">LYNNTECH</div>
                <div class="brand-subtitle">Chemicals &amp; Equipment</div>
            </div>
        </a>

        <div class="nav-label">
            <i class="fas fa-lock mr-2"></i>
            Official Management Portal
        </div>
    </header>

    <!-- Main content -->
    <main class="main-content">
        <div class="content-grid">

            <!-- Welcome section -->
            <section class="hero-copy">
                <div class="eyebrow">
                    <span></span>
                    Excellence in Every Operation
                </div>

                <h1 class="hero-title">
                    Welcome to
                    <br>
                    <span class="gold">Lynntech</span>
                    Management
                </h1>

                <p class="hero-description">
                    Your central workspace for managing business operations,
                    human resources, staff activities and company information
                    through one convenient platform.
                </p>

                <div class="feature-list">
                    <div class="feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Centralised Management</span>
                    </div>

                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Access</span>
                    </div>

                    <div class="feature">
                        <i class="fas fa-chart-line"></i>
                        <span>Operational Efficiency</span>
                    </div>
                </div>
            </section>

            <!-- Login section -->
            <section class="login-card">
                <div class="login-icon">
                    <i class="fas fa-user-lock"></i>
                </div>

                <h2 class="login-title">Sign in to continue</h2>

                <p class="login-description">
                    Access your account to manage your assigned tasks
                    and company activities.
                </p>

                <div class="login-divider"></div>

                <a href="login.php" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login to Your Account</span>
                    <i class="fas fa-arrow-right arrow"></i>
                </a>

                <div class="security-note">
                    <i class="fas fa-shield-alt"></i>
                    <span>
                        Authorised personnel only. Please use your registered
                        account credentials to access the management system.
                    </span>
                </div>
            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div>
            &copy; <?php echo date('Y'); ?>
            <strong>Lynntech Chemicals &amp; Equipment</strong>.
            All rights reserved.
        </div>

        <div>
            <i class="fas fa-lock mr-1"></i>
            Company Management System
        </div>
    </footer>

</div>

</body>
</html>
