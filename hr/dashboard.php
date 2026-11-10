
<?php
session_start();

// Protect the dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once 'db_con.php';

function getDashboardCount(mysqli $conn, string $table, string $where, string $countExpression = '*'): int
{
    $result = $conn->query("SELECT COUNT($countExpression) AS total FROM `$table` WHERE $where");
    if (!$result) {
        die("Unable to load dashboard notifications: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }

    return (int) $result->fetch_assoc()['total'];
}

$dashboardNotifications = [
    [
        'label' => 'Pending leave requests',
        'count' => getDashboardCount($conn, 'leaves', "status = 'Pending'"),
        'href' => 'leaves_request.php',
        'icon' => 'fa-plane-departure',
        'color' => 'amber',
    ],
    [
        'label' => 'Pending training requests',
        'count' => getDashboardCount($conn, 'trainings_request', "status = 'Pending'"),
        'href' => 'view_training_requests.php',
        'icon' => 'fa-graduation-cap',
        'color' => 'teal',
    ],
    [
        'label' => 'Pending department requests',
        'count' => getDashboardCount($conn, 'department_requests', "status = 'Pending Approval'"),
        'href' => 'add_department_request.php',
        'icon' => 'fa-boxes-stacked',
        'color' => 'violet',
    ],
    [
        'label' => 'Recent daily reports',
        'count' => getDashboardCount($conn, 'daily_reports', "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'href' => 'all_daily_reports.php',
        'icon' => 'fa-file-lines',
        'color' => 'blue',
    ],
    [
        'label' => 'Scheduled or overdue audits',
        'count' => getDashboardCount($conn, 'audit_logs', "status IN ('Scheduled', 'Overdue')"),
        'href' => 'audit_log.php',
        'icon' => 'fa-clipboard-list',
        'color' => 'slate',
    ],
    [
        'label' => 'Open disciplinary cases',
        'count' => getDashboardCount($conn, 'disciplinary_cases', "outcome = 'Pending'"),
        'href' => 'disciplinary.php',
        'icon' => 'fa-scale-balanced',
        'color' => 'red',
    ],
    [
        'label' => 'Unpaid expenses',
        'count' => getDashboardCount($conn, 'expenses', "status = 'Not Paid'"),
        'href' => 'manage_expenses.php',
        'icon' => 'fa-wallet',
        'color' => 'rose',
    ],
    [
        'label' => 'Items due for return',
        'count' => getDashboardCount($conn, 'issued_items', "status = 'Issued' AND due_date IS NOT NULL AND due_date <= CURDATE()"),
        'href' => 'issued_items.php',
        'icon' => 'fa-box-open',
        'color' => 'orange',
    ],
    [
        'label' => 'Pending field schedules',
        'count' => getDashboardCount($conn, 'field_schedules', "payment_status = 'Pending'", 'DISTINCT schedule_id'),
        'href' => 'admin_field_schedule_report.php',
        'icon' => 'fa-calendar-days',
        'color' => 'lime',
    ],
    [
        'label' => 'Unpaid field schedules',
        'count' => getDashboardCount($conn, 'field_schedules', "LOWER(payment_status) IN ('pending', 'unpaid')", 'DISTINCT schedule_id'),
        'href' => 'admin_field_schedule_report.php',
        'icon' => 'fa-money-bill-wave',
        'color' => 'emerald',
    ],
    [
        'label' => 'Pending sales report activities',
        'count' => getDashboardCount($conn, 'sales_daily_report_activities', "status = 'Pending'", 'DISTINCT report_id'),
        'href' => 'admin_sales_report.php',
        'icon' => 'fa-chart-line',
        'color' => 'indigo',
    ],
    [
        'label' => 'Pending production report activities',
        'count' => getDashboardCount($conn, 'production_daily_report_activities', "status = 'Pending'", 'DISTINCT report_id'),
        'href' => 'admin_production_report.php',
        'icon' => 'fa-industry',
        'color' => 'cyan',
    ],
    [
        'label' => 'Pending purchasing report activities',
        'count' => getDashboardCount($conn, 'purchasing_daily_report_activities', "status = 'Pending'", 'DISTINCT report_id'),
        'href' => 'admin_purchasing_report.php',
        'icon' => 'fa-cart-shopping',
        'color' => 'purple',
    ],
    [
        'label' => 'Vehicle licenses expiring soon',
        'count' => getDashboardCount($conn, 'vehicles', "(
            license_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            OR speed_governor_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            OR ntsa_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        )", 'DISTINCT id'),
        'href' => 'add_vehicle.php',
        'icon' => 'fa-id-card',
        'color' => 'yellow',
    ],
];

$dashboardNotificationTotal = array_sum(array_column($dashboardNotifications, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            background: #f3f6fb;
            font-family: Arial, sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        .dashboard-main {
            margin-left: 256px;
            padding: 30px;
            width: calc(100% - 256px);
            min-width: 0;
        }

        .module-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            min-height: 110px;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #1f2937;
            min-width: 0;
        }

        .module-card:hover {
            transform: translateY(-3px);
            border-color: #2563eb;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        }

        .module-icon {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .section-heading {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 14px;
        }

        .section-block {
            margin-top: 30px;
        }

        .notification-panel {
            background: white;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.06);
        }

        .notification-total {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: white;
            font-weight: 700;
        }

        .notification-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
            gap: 10px;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            color: #374151;
            text-decoration: none;
            transition: background .2s ease, border-color .2s ease;
            min-width: 0;
        }

        .notification-item:hover {
            filter: brightness(0.98);
        }

        .notification-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #2563eb;
        }

        .notification-item--amber { background: #fffbeb; border-color: #fde68a; }
        .notification-item--amber .notification-icon { background: #fef3c7; color: #b45309; }
        .notification-item--teal { background: #f0fdfa; border-color: #99f6e4; }
        .notification-item--teal .notification-icon { background: #ccfbf1; color: #0f766e; }
        .notification-item--violet { background: #f5f3ff; border-color: #ddd6fe; }
        .notification-item--violet .notification-icon { background: #ede9fe; color: #6d28d9; }
        .notification-item--blue { background: #eff6ff; border-color: #bfdbfe; }
        .notification-item--blue .notification-icon { background: #dbeafe; color: #1d4ed8; }
        .notification-item--slate { background: #f8fafc; border-color: #cbd5e1; }
        .notification-item--slate .notification-icon { background: #e2e8f0; color: #475569; }
        .notification-item--red { background: #fef2f2; border-color: #fecaca; }
        .notification-item--red .notification-icon { background: #fee2e2; color: #b91c1c; }
        .notification-item--rose { background: #fff1f2; border-color: #fecdd3; }
        .notification-item--rose .notification-icon { background: #ffe4e6; color: #be123c; }
        .notification-item--orange { background: #fff7ed; border-color: #fed7aa; }
        .notification-item--orange .notification-icon { background: #ffedd5; color: #c2410c; }
        .notification-item--lime { background: #f7fee7; border-color: #d9f99d; }
        .notification-item--lime .notification-icon { background: #ecfccb; color: #4d7c0f; }
        .notification-item--emerald { background: #ecfdf5; border-color: #a7f3d0; }
        .notification-item--emerald .notification-icon { background: #d1fae5; color: #047857; }
        .notification-item--indigo { background: #eef2ff; border-color: #c7d2fe; }
        .notification-item--indigo .notification-icon { background: #e0e7ff; color: #4338ca; }
        .notification-item--cyan { background: #ecfeff; border-color: #a5f3fc; }
        .notification-item--cyan .notification-icon { background: #cffafe; color: #0e7490; }
        .notification-item--purple { background: #faf5ff; border-color: #e9d5ff; }
        .notification-item--purple .notification-icon { background: #f3e8ff; color: #7e22ce; }
        .notification-item--yellow { background: #fefce8; border-color: #fde047; }
        .notification-item--yellow .notification-icon { background: #fef9c3; color: #a16207; }

        .notification-count {
            min-width: 24px;
            text-align: center;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .dashboard-main {
                margin-left: 0;
                width: 100%;
                padding: 72px 16px 24px;
            }

            .module-card {
                padding: 16px;
                gap: 12px;
            }

            .module-icon {
                width: 44px;
                height: 44px;
                min-width: 44px;
                font-size: 18px;
            }

            .notification-panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<?php
// Keep your existing HR navigation/sidebar
include 'navbar.php';
?>

<main class="dashboard-main">

    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-4 mb-8">

        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                HR Dashboard
            </h1>

            <p class="text-gray-500 mt-2">
                Human Resource Management System
            </p>
        </div>

        <div class="bg-white border rounded-xl px-5 py-3">
            <p class="text-xs text-gray-500">SYSTEM</p>
            <p class="font-semibold text-blue-700">
                <i class="fa-solid fa-circle-check mr-2"></i>
                Lynntech Manager
            </p>
        </div>

    </div>

    <section class="notification-panel" aria-labelledby="notification-heading">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 id="notification-heading" class="section-heading mb-1">
                    <i class="fa-solid fa-bell text-blue-600 mr-2"></i>
                    Notifications
                </h2>
                <p class="text-sm text-gray-500">
                    <?= $dashboardNotificationTotal ?> item<?= $dashboardNotificationTotal === 1 ? '' : 's' ?> need<?= $dashboardNotificationTotal === 1 ? 's' : '' ?> your attention.
                </p>
            </div>
            <span class="notification-total" aria-label="<?= $dashboardNotificationTotal ?> unread notifications">
                <?= $dashboardNotificationTotal ?>
            </span>
        </div>

        <div class="notification-list">
            <?php foreach ($dashboardNotifications as $notification): ?>
                <a href="<?= htmlspecialchars($notification['href'], ENT_QUOTES, 'UTF-8') ?>" class="notification-item notification-item--<?= htmlspecialchars($notification['color'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="notification-icon">
                        <i class="fa-solid <?= htmlspecialchars($notification['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <span class="flex-1"><?= htmlspecialchars($notification['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <strong class="notification-count"><?= $notification['count'] ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </section>


    <!-- EMPLOYEE MANAGEMENT -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-users text-blue-600 mr-2"></i>
            Employee Management
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="add_employee.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="font-bold">Add Employee</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Register a new employee
                    </p>
                </div>
            </a>

            <a href="view_employees.php" class="module-card">
                <div class="module-icon bg-indigo-100 text-indigo-700">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <h3 class="font-bold">View Employees</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Browse employee records
                    </p>
                </div>
            </a>

            <a href="employee_information.php" class="module-card">
                <div class="module-icon bg-cyan-100 text-cyan-700">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div>
                    <h3 class="font-bold">Employee Information</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View employee details
                    </p>
                </div>
            </a>

            <a href="jobs_description.php" class="module-card">
                <div class="module-icon bg-violet-100 text-violet-700">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <div>
                    <h3 class="font-bold">Job Descriptions</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage roles and responsibilities
                    </p>
                </div>
            </a>

            <a href="hierarchy.php" class="module-card">
                <div class="module-icon bg-slate-100 text-slate-700">
                    <i class="fa-solid fa-sitemap"></i>
                </div>
                <div>
                    <h3 class="font-bold">Hierarchy</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View the organisation structure
                    </p>
                </div>
            </a>

            <a href="create_staff_account.php" class="module-card">
                <div class="module-icon bg-emerald-100 text-emerald-700">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <h3 class="font-bold">Staff Accounts</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Create staff login accounts
                    </p>
                </div>
            </a>

        </div>
    </section>


    <!-- RECRUITMENT -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-user-tie text-purple-600 mr-2"></i>
            Recruitment
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="short_listed.php" class="module-card">
                <div class="module-icon bg-purple-100 text-purple-700">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h3 class="font-bold">Shortlisted Candidates</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Review shortlisted applicants
                    </p>
                </div>
            </a>
<a href="applicants.php" class="module-card">
                <div class="module-icon bg-purple-100 text-purple-700">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="font-bold">Applicants</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View all job applicants
                    </p>
                </div>
            </a>
        </div>
    </section>


    <!-- ATTENDANCE AND STAFF WELFARE -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-calendar-check text-green-600 mr-2"></i>
            Attendance & Staff Welfare
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="attendance.php" class="module-card">
                <div class="module-icon bg-green-100 text-green-700">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h3 class="font-bold">Attendance</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage staff attendance
                    </p>
                </div>
            </a>

            <a href="leaves_request.php" class="module-card">
                <div class="module-icon bg-amber-100 text-amber-700">
                    <i class="fa-solid fa-plane-departure"></i>
                </div>
                <div>
                    <h3 class="font-bold">Leave Requests</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Review staff leave requests
                    </p>
                </div>
            </a>

            <a href="admin_field_schedule_report.php" class="module-card">
                <div class="module-icon bg-lime-100 text-lime-700">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h3 class="font-bold">Field Schedule</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Review staff field schedules
                    </p>
                </div>
            </a>

            <a href="view_training_requests.php" class="module-card">
                <div class="module-icon bg-teal-100 text-teal-700">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <h3 class="font-bold">Training Requests</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View requested training
                    </p>
                </div>
            </a>

            <a href="record_training.php" class="module-card">
                <div class="module-icon bg-sky-100 text-sky-700">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                
                <div>
                    <h3 class="font-bold">Record Training</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Record completed training
                    </p>
                </div>
            </a>

        </div>
    </section>


    <!-- PERFORMANCE AND COMPLIANCE -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-chart-line text-orange-600 mr-2"></i>
            Performance & Compliance
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="goals.php" class="module-card">
                <div class="module-icon bg-orange-100 text-orange-700">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div>
                    <h3 class="font-bold">Staff Goals</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage employee objectives
                    </p>
                </div>
            </a>

            <a href="employee_appraisal.php" class="module-card">
                <div class="module-icon bg-pink-100 text-pink-700">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div>
                    <h3 class="font-bold">Employee Appraisal</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Review employee performance
                    </p>
                </div>
            </a>

            <a href="all_daily_reports.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="font-bold">Daily Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View staff daily reports
                    </p>
                </div>
            </a>
            <a href="admin_purchasing_report.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="font-bold">Purchasing Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View purchasing daily reports
                    </p>
                </div>

            </a>
            <a href="admin_production_report.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="font-bold">Production Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View production daily reports
                    </p>
                </div>
            </a>
            <a href="sales_daily_report.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="font-bold">Sales Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View sales daily reports
                    </p>
                </div>
            </a>

            <a href="view_all_evaluations.php" class="module-card">
                <div class="module-icon bg-indigo-100 text-indigo-700">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <h3 class="font-bold">Staff Evaluations</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Review submitted evaluations
                    </p>
                </div>
            </a>

            <a href="disciplinary.php" class="module-card">
                <div class="module-icon bg-red-100 text-red-700">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div>
                    <h3 class="font-bold">Disciplinary Records</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage disciplinary matters
                    </p>
                </div>
            </a>

            <a href="audit_log.php" class="module-card">
                <div class="module-icon bg-yellow-100 text-yellow-700">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <h3 class="font-bold">Audit Log</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage audit information and due dates
                    </p>
                </div>
            </a>

        </div>
    </section>


    <!-- PAYROLL AND FINANCE -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-money-bill-wave text-emerald-600 mr-2"></i>
            Payroll & Finance
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="payroll_details.php" class="module-card">
                <div class="module-icon bg-emerald-100 text-emerald-700">
                    <i class="fa-solid fa-money-check-dollar"></i>
                </div>
                <div>
                    <h3 class="font-bold">Payroll Details</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage payroll information
                    </p>
                </div>
            </a>

            <a href="payroll_list.php" class="module-card">
                <div class="module-icon bg-green-100 text-green-700">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="font-bold">Payroll List</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View payroll records
                    </p>
                </div>
            </a>

            <a href="payroll_deductions.php" class="module-card">
                <div class="module-icon bg-red-100 text-red-700">
                    <i class="fa-solid fa-minus-circle"></i>
                </div>
                <div>
                    <h3 class="font-bold">Payroll Deductions</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage payroll deductions
                    </p>
                </div>
            </a>

            <a href="all_deductions.php" class="module-card">
                <div class="module-icon bg-rose-100 text-rose-700">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h3 class="font-bold">All Deductions</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View recorded deductions
                    </p>
                </div>
            </a>

            <a href="manage_expenses.php" class="module-card">
                <div class="module-icon bg-amber-100 text-amber-700">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <h3 class="font-bold">Manage Expenses</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage company expenses
                    </p>
                </div>
            </a>

            <a href="add_lunch_expense.php" class="module-card">
                <div class="module-icon bg-orange-100 text-orange-700">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                    <h3 class="font-bold">Lunch Expenses</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Record lunch expenses
                    </p>
                </div>
            </a>

            <a href="breakfast_expense.php" class="module-card">
                <div class="module-icon bg-yellow-100 text-yellow-700">
                    <i class="fa-solid fa-mug-hot"></i>
                </div>
                <div>
                    <h3 class="font-bold">Breakfast Expenses</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Record breakfast expenses
                    </p>
                </div>
            </a>

        </div>
    </section>


    <!-- RESOURCES AND REPORTS -->
    <section class="section-block mb-10">
        <h2 class="section-heading">
            <i class="fa-solid fa-boxes-stacked text-cyan-600 mr-2"></i>
            Resources & Reports
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

            <a href="issued_items.php" class="module-card">
                <div class="module-icon bg-cyan-100 text-cyan-700">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <h3 class="font-bold">Issued Items</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Track items issued to staff
                    </p>
                </div>
            </a>

            <a href="add_department_request.php" class="module-card">
                <div class="module-icon bg-violet-100 text-violet-700">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div>
                    <h3 class="font-bold">Department Requests</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage department requests
                    </p>
                </div>
            </a>

            <a href="hr_report.php" class="module-card">
                <div class="module-icon bg-slate-100 text-slate-700">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                    <h3 class="font-bold">HR Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        View human resource reports
                    </p>
                </div>
            </a>

        </div>
    </section>

    <!-- DRIVER MANAGEMENT -->
    <section class="section-block">
        <h2 class="section-heading">
            <i class="fa-solid fa-car text-blue-600 mr-2"></i>
            Driver Management
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            <a href="add_vehicle.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700"><i class="fa-solid fa-car"></i></div>
                <div><h3 class="font-bold">Manage Vehicles</h3><p class="text-sm text-gray-500 mt-1">Maintain vehicle records</p></div>
            </a>
            <a href="manage_trips.php" class="module-card">
                <div class="module-icon bg-indigo-100 text-indigo-700"><i class="fa-solid fa-route"></i></div>
                <div><h3 class="font-bold">Manage Trips</h3><p class="text-sm text-gray-500 mt-1">Plan and review trips</p></div>
            </a>
            <a href="vehicle_maintenance.php" class="module-card">
                <div class="module-icon bg-amber-100 text-amber-700"><i class="fa-solid fa-wrench"></i></div>
                <div><h3 class="font-bold">Vehicle Maintenance</h3><p class="text-sm text-gray-500 mt-1">Track maintenance costs</p></div>
            </a>
            <a href="fuel.php" class="module-card">
                <div class="module-icon bg-orange-100 text-orange-700"><i class="fa-solid fa-gas-pump"></i></div>
                <div><h3 class="font-bold">Fuel Section</h3><p class="text-sm text-gray-500 mt-1">Manage fuel records</p></div>
            </a>
            <a href="vehicle_reports.php" class="module-card">
                <div class="module-icon bg-cyan-100 text-cyan-700"><i class="fa-solid fa-chart-area"></i></div>
                <div><h3 class="font-bold">Vehicle Reports</h3><p class="text-sm text-gray-500 mt-1">Review vehicle reports</p></div>
            </a>
        </div>
    </section>

    <!-- SACCO MANAGEMENT -->
    <section class="section-block mb-10">
        <h2 class="section-heading">
            <i class="fa-solid fa-building-columns text-emerald-600 mr-2"></i>
            SACCO Management
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            <a href="manage_saccos.php" class="module-card">
                <div class="module-icon bg-emerald-100 text-emerald-700">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <h3 class="font-bold">SACCO Management</h3>
                    <p class="text-sm text-gray-500 mt-1">Manage SACCO setup and records</p>
                </div>
            </a>
            <a href="sacco_members.php" class="module-card">
                <div class="module-icon bg-cyan-100 text-cyan-700">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <h3 class="font-bold">SACCO Members</h3>
                    <p class="text-sm text-gray-500 mt-1">View and manage members</p>
                </div>
            </a>
            <a href="record_sacco_contribution.php" class="module-card">
                <div class="module-icon bg-blue-100 text-blue-700">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <h3 class="font-bold">SACCO Contributions</h3>
                    <p class="text-sm text-gray-500 mt-1">Record member contributions</p>
                </div>
            </a>
            <a href="sacco_loans.php" class="module-card">
                <div class="module-icon bg-amber-100 text-amber-700">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div>
                    <h3 class="font-bold">SACCO Loans</h3>
                    <p class="text-sm text-gray-500 mt-1">Manage member loans</p>
                </div>
            </a>
            <a href="pension_management.php" class="module-card">
                <div class="module-icon bg-violet-100 text-violet-700">
                    <i class="fa-solid fa-piggy-bank"></i>
                </div>
                <div>
                    <h3 class="font-bold">Pension Management</h3>
                    <p class="text-sm text-gray-500 mt-1">Manage pension contributions</p>
                </div>
            <a href="sacco_reports.php" class="module-card">
                <div class="module-icon bg-violet-100 text-violet-700">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="font-bold">SACCO Reports</h3>
                    <p class="text-sm text-gray-500 mt-1">Review SACCO reports</p>
                </div>
            </a>
        </div>
    </section>


</main>

</body>
</html>
