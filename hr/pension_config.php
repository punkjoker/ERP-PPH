<?php
/** Employee-level pension enrollment, separate from monthly payroll snapshots. */
function loadPensionConfig(mysqli $conn, int $userId): array {
    $stmt=$conn->prepare('SELECT enabled,tax_qualified FROM employee_pension_settings WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$userId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    return ['enabled'=>(bool)($row['enabled']??false),'tax_qualified'=>(bool)($row['tax_qualified']??false)];
}
function pensionConfigFromPost(array $post): array {
    $enabled=(string)($post['pension_enabled']??'0');
    $qualified=(string)($post['pension_tax_qualified']??'0');
    if(!in_array($enabled,['0','1'],true)||!in_array($qualified,['0','1'],true)) throw new InvalidArgumentException('Invalid pension settings.');
    return ['enabled'=>$enabled==='1','tax_qualified'=>$enabled==='1'&&$qualified==='1'];
}
function storePensionConfig(mysqli $conn,int $userId,array $pension): void {
    $enabled=$pension['enabled']?1:0;$qualified=$pension['tax_qualified']?1:0;
    $stmt=$conn->prepare('INSERT INTO employee_pension_settings(user_id,enabled,tax_qualified) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),tax_qualified=VALUES(tax_qualified)');
    $stmt->bind_param('iii',$userId,$enabled,$qualified);$stmt->execute();$stmt->close();
}
