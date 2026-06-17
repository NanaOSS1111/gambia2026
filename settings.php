<?php
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row['value'] : $default;
    } catch (PDOException) {
        return $default;
    }
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")->execute([$key, $value, $value]);
}

function is_registration_open(PDO $pdo): array {
    $open     = get_setting($pdo, 'registration_open', '1');
    $deadline = get_setting($pdo, 'registration_deadline', '');

    if ($open === '0') {
        return ['open' => false, 'reason' => 'manual'];
    }
    if ($deadline !== '' && ($ts = strtotime($deadline)) && time() > $ts) {
        return ['open' => false, 'reason' => 'deadline', 'deadline' => $deadline];
    }
    return ['open' => true, 'deadline' => $deadline];
}
