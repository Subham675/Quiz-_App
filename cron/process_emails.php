<?php
/**
 * Background Email Queue Worker.
 * Can be triggered via CLI (e.g. php cron/process_emails.php) or cron task.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

$res = processEmailQueue(20);
if (php_sapi_name() === 'cli') {
    echo "Processed: {$res['processed']} | Sent: {$res['sent']} | DevMode: " . ($res['dev_mode'] ? 'true' : 'false') . PHP_EOL;
}
