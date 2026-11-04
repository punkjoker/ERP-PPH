<?php
require 'db_con.php';
require_once 'kenya_payroll_calculator.php';
require_once 'pension_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST required']); exit; }
try {
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) throw new InvalidArgumentException('Invalid employee.');
    $month = (string)($_POST['month'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $stmt = $conn->prepare('SELECT base_salary FROM employee_salary WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId); $stmt->execute();
    $salary = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$salary) throw new InvalidArgumentException('Salary record not found.');
    $allowances = json_decode((string)($_POST['allowances'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $custom = json_decode((string)($_POST['other_deductions'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($allowances) || !is_array($custom)) throw new InvalidArgumentException('Invalid payroll lines.');
    $pension=pensionConfigFromPost($_POST);
    // Enrollment is proposed in the preview and persisted only by save_payroll.php.
    echo json_encode(kenyaStandardPayroll((float)$salary['base_salary'], $allowances, $custom, $month, $year, $pension), JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException | JsonException $e) {
    http_response_code(400); echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Payroll preview: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Unable to calculate payroll.']);
}
