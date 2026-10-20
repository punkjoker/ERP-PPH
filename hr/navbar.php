
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdn.tailwindcss.com"></script>

<style>
    :root {
        --hr-sidebar: #111827;
        --hr-sidebar-hover: #1f2937;
        --hr-blue: #3b82f6;
        --hr-text: #cbd5e1;
    }

    .hr-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 260px;
        height: 100vh;
        background: var(--hr-sidebar);
        color: var(--hr-text);
        display: flex;
        flex-direction: column;
        z-index: 1000;
        border-right: 1px solid #263244;
        transition: transform .25s ease;
    }

    .hr-brand {
        padding: 24px 20px 20px;
        border-bottom: 1px solid #293548;
    }

    .hr-brand-icon {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        font-size: 20px;
        box-shadow: 0 5px 15px rgba(37, 99, 235, .25);
    }

    .hr-profile {
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid #293548;
    }

    .hr-avatar {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        border-radius: 50%;
        background: #263e65;
        color: #93c5fd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 15px;
        border: 1px solid #3b5275;
    }

    .hr-nav {
        flex: 1;
        overflow-y: auto;
        padding: 16px 12px;
        scrollbar-width: thin;
        scrollbar-color: #475569 transparent;
    }

    .hr-nav::-webkit-scrollbar {
        width: 5px;
    }

    .hr-nav::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 10px;
    }

    .hr-nav-heading {
        padding: 13px 12px 8px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #64748b;
    }

    .hr-nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 12px;
        margin: 3px 0;
        border-radius: 9px;
        color: #cbd5e1;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: background .18s ease, color .18s ease;
    }

    .hr-nav-link i {
        width: 19px;
        text-align: center;
        font-size: 15px;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .hr-nav-link:hover {
        background: var(--hr-sidebar-hover);
        color: white;
    }

    .hr-nav-link:hover i {
        color: #93c5fd;
    }

    .hr-nav-link.active {
        color: white;
        background: linear-gradient(90deg, #2563eb, #1d4ed8);
        box-shadow: 0 4px 12px rgba(37, 99, 235, .2);
        font-weight: 700;
    }

    .hr-nav-link.active i {
        color: white;
    }

    .hr-sidebar-footer {
        padding: 15px 14px;
        border-top: 1px solid #293548;
        background: #0f172a;
    }

    .hr-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 12px;
        border-radius: 9px;
        background: rgba(239, 68, 68, .12);
        color: #fca5a5;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        border: 1px solid rgba(239, 68, 68, .15);
        transition: all .2s ease;
    }

    .hr-logout:hover {
        background: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    .hr-mobile-toggle {
        display: none;
        position: fixed;
        top: 14px;
        left: 14px;
        z-index: 1100;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 10px;
        background: #1e40af;
        color: white;
        font-size: 18px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }

    .hr-sidebar-overlay {
        display: none;
    }

    /* Main content offset for pages using the sidebar */
   @media (min-width: 769px) {
    body.hr-layout-active {
        padding-left: 0;
    }
}

    @media (max-width: 768px) {
        .hr-sidebar {
            width: 260px;
            transform: translateX(-100%);
        }

        .hr-sidebar.mobile-open {
            transform: translateX(0);
        }

        .hr-mobile-toggle {
            display: flex;
        }

        .hr-sidebar-overlay.visible {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            z-index: 900;
        }

        body.hr-layout-active {
            padding-left: 0;
        }
    }
</style>

<button type="button"
        class="hr-mobile-toggle"
        id="hrMobileToggle"
        aria-label="Open navigation"
        aria-controls="hrSidebar"
        aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
</button>

<div class="hr-sidebar-overlay" id="hrSidebarOverlay"></div>

<aside class="hr-sidebar" id="hrSidebar">

    <!-- Brand -->
    <div class="hr-brand">
        <div class="flex items-center gap-3">
            <div class="hr-brand-icon">
                <i class="fa-solid fa-building-user"></i>
            </div>

            <div>
                <h2 class="text-white font-bold text-lg leading-tight">
                    LYNNTECH
                </h2>
                <p class="text-blue-400 text-xs mt-1 tracking-wider">
                    HUMAN RESOURCES
                </p>
            </div>
        </div>
    </div>

    <!-- Profile -->
    <div class="hr-profile">
        <div class="hr-avatar">
            <i class="fa-solid fa-user-tie"></i>
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-white text-sm font-semibold truncate">
                HR Department
            </p>

            <p class="text-slate-400 text-xs mt-1">
                Management Portal
            </p>

            <a href="profile.php"
               class="inline-flex items-center gap-1 text-blue-400
                      hover:text-blue-300 text-xs mt-2">
                <i class="fa-solid fa-pen-to-square"></i>
                Edit Profile
            </a>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="hr-nav" aria-label="HR navigation">

        <p class="hr-nav-heading">Overview</p>

        <a href="dashboard.php" class="hr-nav-link">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>


        <p class="hr-nav-heading">People Management</p>

        <a href="add_employee.php" class="hr-nav-link">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add Employee</span>
        </a>

        <a href="view_employees.php" class="hr-nav-link">
            <i class="fa-solid fa-users"></i>
            <span>View Employees</span>
        </a>

        <a href="employee_information.php" class="hr-nav-link">
            <i class="fa-solid fa-id-card"></i>
            <span>Employee Information</span>
        </a>

        <a href="jobs_description.php" class="hr-nav-link">
            <i class="fa-solid fa-briefcase"></i>
            <span>Job Descriptions</span>
        </a>
        <a href="hierarchy.php" class="hr-nav-link">
            <i class="fa-solid fa-file-lines"></i>
            <span>Hierarchy</span>

        <a href="create_staff_account.php" class="hr-nav-link">
            <i class="fa-solid fa-user-shield"></i>
            <span>Create Staff Account</span>
        </a>

        <a href="short_listed.php" class="hr-nav-link">
            <i class="fa-solid fa-user-check"></i>
            <span>Shortlisted Candidates</span>
        </a>


        <p class="hr-nav-heading">Attendance & Welfare</p>

        <a href="attendance.php" class="hr-nav-link">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <a href="leaves_request.php" class="hr-nav-link">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Leave Requests</span>
        </a>

        <a href="view_training_requests.php" class="hr-nav-link">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Training Requests</span>
        </a>

        <a href="record_training.php" class="hr-nav-link">
            <i class="fa-solid fa-chalkboard-user"></i>
            <span>Record Training</span>
        </a>


        <p class="hr-nav-heading">Performance & Compliance</p>

        <a href="goals.php" class="hr-nav-link">
            <i class="fa-solid fa-bullseye"></i>
            <span>Staff Goals</span>
        </a>

        <a href="employee_appraisal.php" class="hr-nav-link">
            <i class="fa-solid fa-star"></i>
            <span>Employee Appraisal</span>
        </a>

        <a href="all_daily_reports.php" class="hr-nav-link">
            <i class="fa-solid fa-file-lines"></i>
            <span>Daily Reports</span>
        </a>

        <a href="view_all_evaluations.php" class="hr-nav-link">
            <i class="fa-solid fa-list-check"></i>
            <span>Staff Evaluations</span>
        </a>

        <a href="disciplinary.php" class="hr-nav-link">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Disciplinary Records</span>
        </a>

        <a href="audit_log.php" class="hr-nav-link">
            <i class="fa-solid fa-clipboard-list"></i>
            <span>Audit Logs</span>
        </a>


        <p class="hr-nav-heading">Payroll & Finance</p>

        <a href="payroll_details.php" class="hr-nav-link">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Payroll Details</span>
        </a>

        <a href="payroll_deductions.php" class="hr-nav-link">
            <i class="fa-solid fa-minus-circle"></i>
            <span>Payroll Deductions</span>
        </a>

        <a href="payroll_list.php" class="hr-nav-link">
            <i class="fa-solid fa-money-check-dollar"></i>
            <span>Process Payroll</span>
        </a>

        <a href="all_deductions.php" class="hr-nav-link">
            <i class="fa-solid fa-receipt"></i>
            <span>Deductions Reports</span>
        </a>

        <a href="manage_expenses.php" class="hr-nav-link">
            <i class="fa-solid fa-wallet"></i>
            <span>Manage Expenses</span>
        </a>

        <a href="add_lunch_expense.php" class="hr-nav-link">
            <i class="fa-solid fa-utensils"></i>
            <span>Lunch Expenses</span>
        </a>

        <a href="breakfast_expense.php" class="hr-nav-link">
            <i class="fa-solid fa-mug-hot"></i>
            <span>Breakfast Expenses</span>
        </a>


        <p class="hr-nav-heading">Resources & Reports</p>

        <a href="issued_items.php" class="hr-nav-link">
            <i class="fa-solid fa-box-open"></i>
            <span>Issued Items</span>
        </a>

        <a href="add_department_request.php" class="hr-nav-link">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>New Item Request</span>
        </a>

        <a href="hr_report.php" class="hr-nav-link">
            <i class="fa-solid fa-chart-column"></i>
            <span>HR Reports</span>
        </a>

    </nav>

    <!-- Logout -->
    <div class="hr-sidebar-footer">
        <a href="logout.php" class="hr-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>

        <p class="text-center text-slate-600 text-xs mt-3">
            Lynntech Manager
        </p>
    </div>

</aside>

<script>
(function () {
    const sidebar = document.getElementById('hrSidebar');
    const toggle = document.getElementById('hrMobileToggle');
    const overlay = document.getElementById('hrSidebarOverlay');
    const nav = sidebar.querySelector('.hr-nav');

    document.body.classList.add('hr-layout-active');

    // Restore navigation scroll position.
    try {
        const savedScroll = sessionStorage.getItem('hr-sidebar-scroll');

        if (savedScroll !== null) {
            nav.scrollTop = parseInt(savedScroll, 10) || 0;
        }
    } catch (error) {
        // Navigation still works if browser storage is unavailable.
    }

    nav.addEventListener('scroll', function () {
        try {
            sessionStorage.setItem('hr-sidebar-scroll', nav.scrollTop);
        } catch (error) {
            // Ignore storage errors.
        }
    });

    // Highlight the current page.
    const currentPage = window.location.pathname.split('/').pop();

    sidebar.querySelectorAll('.hr-nav-link').forEach(function (link) {
        const linkPage = link.getAttribute('href');

        if (linkPage === currentPage) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        } else {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        }
    });

    // Mobile sidebar controls.
    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('visible');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }

    toggle.addEventListener('click', function () {
        const isOpen = sidebar.classList.toggle('mobile-open');

        overlay.classList.toggle('visible', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.innerHTML = isOpen
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-bars"></i>';
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
})();
</script>
