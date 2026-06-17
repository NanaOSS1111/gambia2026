<?php
function check_rate_limit(PDO $pdo, string $email, string $phone, int $windowMinutes = 60): void {
    try {
        static $ready = false;
        if (!$ready) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255)  NOT NULL DEFAULT '',
                phone      VARCHAR(50)   NOT NULL DEFAULT '',
                created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email_time (email, created_at),
                INDEX idx_phone_time (phone, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ready = true;
        }

        // Check if this email was submitted recently
        if ($email !== '') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([strtolower($email), $windowMinutes]);
            if ((int)$stmt->fetchColumn() >= 1) {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'This email address was used to register recently. Please wait before submitting again, or contact us if you need help.',
                ]);
                exit;
            }
        }

        // Check if this phone number was submitted recently
        if ($phone !== '') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
            );
            $stmt->execute([$phone, $windowMinutes]);
            if ((int)$stmt->fetchColumn() >= 1) {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'This phone number was used to register recently. Please wait before submitting again, or contact us if you need help.',
                ]);
                exit;
            }
        }

        // Record this attempt
        $pdo->prepare("INSERT INTO rate_limits (email, phone) VALUES (?, ?)")
            ->execute([strtolower($email), $phone]);

        // Prune old records
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
            ->execute([$windowMinutes]);

    } catch (PDOException $e) {
        // Rate limit table error — log it but never block a real submission
        error_log('rate_limit error: ' . $e->getMessage());
    }
}
