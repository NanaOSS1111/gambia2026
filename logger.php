<?php
function ensure_logs_table(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id     INT UNSIGNED  DEFAULT NULL,
        admin_name   VARCHAR(100)  NOT NULL DEFAULT '',
        admin_email  VARCHAR(255)  NOT NULL DEFAULT '',
        action       VARCHAR(80)   NOT NULL,
        details      TEXT,
        ip_address   VARCHAR(45)   DEFAULT NULL,
        created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function log_action(PDO $pdo, string $action, string $details = ''): void {
    ensure_logs_table($pdo);
    $pdo->prepare(
        "INSERT INTO admin_logs (admin_id, admin_name, admin_email, action, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $_SESSION['admin_id']    ?? null,
        $_SESSION['admin_name']  ?? 'System',
        $_SESSION['admin_email'] ?? '',
        $action,
        $details,
        $_SERVER['REMOTE_ADDR']  ?? '',
    ]);
}
