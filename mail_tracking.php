<?php
/**
 * Per-delegate mail tracking and resilient bulk sending.
 *
 * Bulk sends previously looped over every selected delegate inside one request with a
 * single 120-second limit, recorded nothing, and discarded each send's return value. A
 * batch that died partway was indistinguishable from one that succeeded, and re-running
 * it re-sent to everyone who had already been emailed.
 *
 * This records a timestamp per delegate per mail type, so a batch can be re-run safely:
 * anyone already sent is skipped.
 */

/** Timestamp columns, keyed by the bulk action that writes them. */
const MAIL_SENT_COLUMNS = [
    'confirmation_sent_at',
    'approval_sent_at',
    'rejection_sent_at',
    'invite_sent_at',
    'official_invite_sent_at',
];

/**
 * The mail types shown on a delegate's page, in the order they occur.
 * Keyed by the column that records them.
 */
const MAIL_TYPES = [
    'confirmation_sent_at'    => ['label' => 'Registration confirmation', 'resend' => 'confirmation'],
    'approval_sent_at'        => ['label' => 'Approval',                  'resend' => 'approval'],
    'rejection_sent_at'       => ['label' => 'Rejection notice',          'resend' => null],
    'invite_sent_at'          => ['label' => 'Invitation letter',         'resend' => 'invitation'],
    'official_invite_sent_at' => ['label' => 'Official invitation & VISA','resend' => 'official_invitation'],
];

/**
 * Add the tracking columns if they are missing.
 *
 * Done in code rather than a manual migration so deploying this file is sufficient —
 * setup.sql is only run when building a new database, and this one is already live.
 */
function ensure_mail_tracking_columns(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM registrations") as $col) {
            $existing[] = $col['Field'];
        }

        foreach (MAIL_SENT_COLUMNS as $column) {
            if (!in_array($column, $existing, true)) {
                // Nullable with no default: NULL means "never sent", which is what every
                // existing row should read as.
                $pdo->exec("ALTER TABLE registrations ADD COLUMN `$column` DATETIME NULL DEFAULT NULL");
                error_log("mail_tracking: added column $column to registrations");
            }
        }
    } catch (PDOException $e) {
        // Never block a send because tracking could not be set up.
        error_log('mail_tracking error: ' . $e->getMessage());
    }
}

/** Record that a given mail type has been sent to a delegate. */
function mark_mail_sent(PDO $pdo, int $id, string $column): void {
    if (!in_array($column, MAIL_SENT_COLUMNS, true)) {
        return; // never interpolate a column name we did not define
    }
    try {
        $pdo->prepare("UPDATE registrations SET `$column` = NOW() WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) {
        error_log('mail_tracking error: ' . $e->getMessage());
    }
}

/**
 * Send one mail type to many delegates, skipping anyone already sent.
 *
 * @param callable $sender fn(array $row): bool
 * @return array{sent:int,failed:int,skipped:int,failures:string[]}
 */
function send_bulk(PDO $pdo, array $rows, string $column, callable $sender): array {
    ensure_mail_tracking_columns($pdo);

    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $failures = [];
    $processed = 0;

    foreach ($rows as $row) {
        // Already emailed on an earlier run — skip so re-running a partial batch is safe.
        if (!empty($row[$column])) {
            $skipped++;
            continue;
        }

        // Reset the clock per message rather than sharing one budget across the batch.
        // A 115-delegate run needs minutes; a single 120s limit killed it partway with no
        // record of how far it got.
        @set_time_limit(60);

        if ($sender($row)) {
            $sent++;
            mark_mail_sent($pdo, (int) $row['id'], $column);
        } else {
            $failed++;
            $failures[] = $row['email'] ?? ('id ' . ($row['id'] ?? '?'));
        }

        // PDF generation is memory-hungry; reclaim periodically on long runs.
        if (++$processed % 10 === 0) {
            gc_collect_cycles();
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'failures' => $failures];
}

/** One-line summary of a send_bulk() result, for the admin log. */
function bulk_result_summary(array $r): string {
    $parts = ["sent {$r['sent']}"];
    if ($r['failed'])  { $parts[] = "failed {$r['failed']}"; }
    if ($r['skipped']) { $parts[] = "skipped {$r['skipped']} already sent"; }

    $summary = implode(', ', $parts);
    if ($r['failures']) {
        $shown = array_slice($r['failures'], 0, 20);
        $summary .= '. Failed: ' . implode(', ', $shown);
        if (count($r['failures']) > count($shown)) {
            $summary .= ' and ' . (count($r['failures']) - count($shown)) . ' more';
        }
    }
    return $summary;
}

/**
 * Delivery events Brevo recorded for one address, newest first.
 *
 * This is the same history Brevo shows per message — sent, delivered, opened, clicked,
 * bounced — so a delegate's page can answer "did it actually arrive" rather than only
 * "did we try to send it".
 */
function brevo_events_for(string $email, int $limit = 25): array {
    if (!defined('BREVO_API_KEY') || BREVO_API_KEY === '' || $email === '') {
        return [];
    }

    $url = 'https://api.brevo.com/v3/smtp/statistics/events?' . http_build_query([
        'email' => $email,
        'limit' => $limit,
        'sort'  => 'desc',
        'days'  => 90,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
    ]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($status < 200 || $status >= 300) {
        return [];
    }
    return json_decode((string) $raw, true)['events'] ?? [];
}
