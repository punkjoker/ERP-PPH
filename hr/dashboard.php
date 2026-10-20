
<?php
session_start();

// Protect the dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once 'db_con.php';
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
        }

        .dashboard-main {
            margin-left: 256px;
            padding: 30px;
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

        @media (max-width: 768px) {
            .dashboard-main {
                margin-left: 0;
                padding: 18px;
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

</main>

</body>
</html>
