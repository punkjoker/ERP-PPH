<?php
include 'db_con.php';

$employee_id = $_GET['id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skills = $_POST['skills'] ?? '';
    $certifications = $_POST['certifications'] ?? '';
    $expected_salary = $_POST['expected_salary'] ?? 0;
    $approved_salary = $_POST['approved_salary'] ?? 0;

    // Insert or update main record
    $stmt = $conn->prepare("INSERT INTO employee_information 
        (employee_id, skills, certifications, expected_salary, approved_salary)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        skills=VALUES(skills), certifications=VALUES(certifications), 
        expected_salary=VALUES(expected_salary), approved_salary=VALUES(approved_salary)");
    $stmt->bind_param("issdd", $employee_id, $skills, $certifications, $expected_salary, $approved_salary);
    $stmt->execute();
    $stmt->close();

    // Delete old items and reinsert (to handle updates)
    $conn->query("DELETE FROM employee_information_items WHERE employee_id = $employee_id");

    // Education entries
    if (!empty($_POST['school_name'])) {
        foreach ($_POST['school_name'] as $i => $school) {
            if (trim($school) === '') continue;
            $stmt = $conn->prepare("INSERT INTO employee_information_items 
                (employee_id, category, field1, field2, field3, field4, field5)
                VALUES (?, 'education', ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "isssss",
                $employee_id,
                $_POST['school_name'][$i],
                $_POST['level'][$i],
                $_POST['start_year'][$i],
                $_POST['end_year'][$i],
                $_POST['result'][$i]
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    // Experience entries
    if (!empty($_POST['organization_name'])) {
        foreach ($_POST['organization_name'] as $i => $org) {
            if (trim($org) === '') continue;
            $stmt = $conn->prepare("INSERT INTO employee_information_items 
                (employee_id, category, field1, field2, field3)
                VALUES (?, 'experience', ?, ?, ?)");
            $stmt->bind_param(
                "isss",
                $employee_id,
                $_POST['organization_name'][$i],
                $_POST['exp_start_date'][$i],
                $_POST['exp_end_date'][$i]
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<script>alert('Employee information updated successfully'); window.location.href='employee_information.php';</script>";
    exit;
}

// Fetch employee info
$emp = $conn->query("SELECT * FROM employees WHERE employee_id = $employee_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Employee Information</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <div class="max-w-5xl mx-auto bg-white shadow-lg rounded-lg p-6">
    <h1 class="text-2xl font-bold mb-6 text-blue-700">
      Update Employee Information — <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
    </h1>

    <form method="POST" x-data="{ eduCount: 1, expCount: 1 }" class="space-y-6">
      <!-- 📘 Education Section -->
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-3">Education Background</h2>
        <template x-for="i in eduCount" :key="i">
          <div class="grid grid-cols-5 gap-3 mb-2">
            <input type="text" name="school_name[]" placeholder="School Name" class="border p-2 rounded">
            <input type="text" name="level[]" placeholder="Level (e.g. Diploma)" class="border p-2 rounded">
            <input type="text" name="start_year[]" placeholder="Start Year" class="border p-2 rounded">
            <input type="text" name="end_year[]" placeholder="End Year" class="border p-2 rounded">
            <input type="text" name="result[]" placeholder="Result/Award" class="border p-2 rounded">
          </div>
        </template>
        <button type="button" @click="eduCount++" class="bg-blue-500 text-white px-3 py-1 rounded mt-1 hover:bg-blue-600">
          + Add More
        </button>
      </div>

      <!-- 💼 Experience Section -->
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-3">Work Experience</h2>
        <template x-for="i in expCount" :key="i">
          <div class="grid grid-cols-3 gap-3 mb-2">
            <input type="text" name="organization_name[]" placeholder="Organization Name" class="border p-2 rounded">
            <input type="date" name="exp_start_date[]" class="border p-2 rounded">
            <input type="date" name="exp_end_date[]" class="border p-2 rounded">
          </div>
        </template>
        <button type="button" @click="expCount++" class="bg-blue-500 text-white px-3 py-1 rounded mt-1 hover:bg-blue-600">
          + Add More
        </button>
      </div>

      <!-- 🧠 Skills -->
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">Skills</h2>
        <textarea name="skills" rows="3" placeholder="Enter employee skills separated by commas" 
          class="w-full border p-2 rounded"><?= htmlspecialchars($info['skills'] ?? '') ?></textarea>
      </div>

      <!-- 📜 Certifications -->
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">Certifications</h2>
        <textarea name="certifications" rows="3" placeholder="Enter certifications, separated by commas"
          class="w-full border p-2 rounded"><?= htmlspecialchars($info['certifications'] ?? '') ?></textarea>
      </div>

      <!-- 💰 Salary -->
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-3">Salary Information</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-600 mb-1">Expected Salary (Ksh)</label>
            <input type="number" name="expected_salary" step="0.01" class="border p-2 w-full rounded" value="<?= htmlspecialchars($info['expected_salary'] ?? '') ?>">
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Approved Salary (Ksh)</label>
            <input type="number" name="approved_salary" step="0.01" class="border p-2 w-full rounded" value="<?= htmlspecialchars($info['approved_salary'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="pt-4">
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
          Save Information
        </button>
        <a href="employee_information.php" class="ml-3 bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
