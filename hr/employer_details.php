<?php
session_start();
require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
// CSRF token
if (empty($_SESSION['employer_details_csrf'])) {
    $_SESSION['employer_details_csrf'] = bin2hex(random_bytes(32));
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employer'])) {
    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['employer_details_csrf'],
                $_POST['csrf_token']
            )
        ) {
            throw new Exception("Invalid security token. Please refresh the page and try again.");
        }

        $employerName = trim($_POST['employer_name'] ?? '');
        $kraPin       = strtoupper(trim($_POST['kra_pin'] ?? ''));
        $postal       = trim($_POST['postal_address'] ?? '');
        $town         = trim($_POST['town'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $email        = trim($_POST['email'] ?? '');

        if ($employerName === '') {
            throw new Exception("Please enter the employer's registered name.");
        }

        if ($kraPin === '') {
            throw new Exception("Please enter the employer's KRA PIN.");
        }

        if (strlen($kraPin) > 30) {
            throw new Exception("The KRA PIN is too long.");
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        $stmt = $conn->prepare("
            INSERT INTO employer_tax_details
                (id, employer_name, kra_pin, postal_address, town, phone, email)
            VALUES
                (1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                employer_name = VALUES(employer_name),
                kra_pin = VALUES(kra_pin),
                postal_address = VALUES(postal_address),
                town = VALUES(town),
                phone = VALUES(phone),
                email = VALUES(email)
        ");

        $stmt->bind_param(
            "ssssss",
            $employerName,
            $kraPin,
            $postal,
            $town,
            $phone,
            $email
        );

        $stmt->execute();
        $stmt->close();

        // Rotate CSRF token after successful save
        $_SESSION['employer_details_csrf'] = bin2hex(random_bytes(32));

        header("Location: employer_details.php?saved=1");
        exit;

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// Load employer details
$employer = [
    'employer_name' => '',
    'kra_pin' => '',
    'postal_address' => '',
    'town' => '',
    'phone' => '',
    'email' => ''
];

$stmt = $conn->prepare("
    SELECT employer_name, kra_pin, postal_address, town, phone, email
    FROM employer_tax_details
    WHERE id = 1
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $employer = $result->fetch_assoc();
}

$stmt->close();

if (isset($_GET['saved'])) {
    $success = "Employer details have been saved successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Tax Details | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background: #f8fafc;
        }

        .form-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
        }

        .form-input {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: white;
        }

        .form-input:focus {
            border-color: #b88a32;
            box-shadow: 0 0 0 3px rgba(184, 138, 50, 0.12);
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="ml-64 p-6">

    <div class="max-w-5xl mx-auto">

        <div class="flex flex-col md:flex-row md:items-center
                    md:justify-between gap-4 mb-7">

            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    <i class="fas fa-building text-amber-600 mr-2"></i>
                    Employer Tax Details
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Manage the company information used when generating
                    employee KRA P9A forms.
                </p>
            </div>

            <a href="javascript:history.back()"
               class="inline-flex items-center gap-2 text-sm text-slate-600
                      hover:text-amber-700">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>

        </div>

        <?php if ($success): ?>
            <div class="mb-5 p-4 rounded-lg bg-green-50
                        border border-green-200 text-green-800 text-sm">
                <i class="fas fa-check-circle mr-2"></i>
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-lg bg-red-50
                        border border-red-200 text-red-700 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-card shadow-sm overflow-hidden">

            <div class="px-6 py-5 border-b border-slate-200
                        bg-slate-50">
                <h2 class="font-semibold text-slate-800">
                    Company Information
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Enter the details exactly as registered with KRA.
                </p>
            </div>

            <form method="POST" action="employer_details.php"
                  class="p-6">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION['employer_details_csrf']) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="md:col-span-2">
                        <label class="form-label">
                            Registered Employer Name
                            <span class="text-red-500">*</span>
                        </label>

                        <input type="text"
                               name="employer_name"
                               class="form-input"
                               placeholder="Enter registered company name"
                               value="<?= e($employer['employer_name']) ?>"
                               maxlength="255"
                               required>
                    </div>

                    <div>
                        <label class="form-label">
                            Employer KRA PIN
                            <span class="text-red-500">*</span>
                        </label>

                        <input type="text"
                               name="kra_pin"
                               class="form-input uppercase"
                               placeholder="e.g. P051234567X"
                               value="<?= e($employer['kra_pin']) ?>"
                               maxlength="30"
                               required>

                        <p class="text-xs text-slate-400 mt-1">
                            This PIN will appear on employee P9A forms.
                        </p>
                    </div>

                    <div>
                        <label class="form-label">Phone Number</label>

                        <input type="text"
                               name="phone"
                               class="form-input"
                               placeholder="Company phone number"
                               value="<?= e($employer['phone']) ?>"
                               maxlength="30">
                    </div>

                    <div>
                        <label class="form-label">Postal Address</label>

                        <input type="text"
                               name="postal_address"
                               class="form-input"
                               placeholder="e.g. P.O. Box 12345"
                               value="<?= e($employer['postal_address']) ?>"
                               maxlength="255">
                    </div>

                    <div>
                        <label class="form-label">Town / City</label>

                        <input type="text"
                               name="town"
                               class="form-input"
                               placeholder="e.g. Nairobi"
                               value="<?= e($employer['town']) ?>"
                               maxlength="100">
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Company Email</label>

                        <input type="email"
                               name="email"
                               class="form-input"
                               placeholder="company@example.com"
                               value="<?= e($employer['email']) ?>"
                               maxlength="150">
                    </div>

                </div>

                <div class="mt-8 pt-5 border-t border-slate-200
                            flex flex-col sm:flex-row sm:items-center
                            sm:justify-between gap-4">

                    <p class="text-xs text-slate-500">
                        <i class="fas fa-lock mr-1"></i>
                        Only authorised HR users should manage these details.
                    </p>

                    <button type="submit"
                            name="save_employer"
                            class="bg-amber-600 hover:bg-amber-700
                                   text-white px-6 py-3 rounded-lg
                                   font-semibold text-sm transition">

                        <i class="fas fa-save mr-2"></i>
                        Save Employer Details
                    </button>

                </div>

            </form>
        </div>

        <div class="mt-5 p-4 bg-blue-50 border border-blue-100
                    rounded-lg text-sm text-blue-800">

            <i class="fas fa-info-circle mr-2"></i>

            These details will be retrieved automatically when the
            system generates annual employee P9A tax deduction cards.
        </div>

    </div>

</main>

</body>
</html>
