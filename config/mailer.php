<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host        = $_ENV['MAIL_HOST'] ?? $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth    = true;
    $mail->Username    = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
    $mail->Password    = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = (int)($_ENV['MAIL_PORT'] ?? $_ENV['SMTP_PORT'] ?? 587);
    $mail->Timeout     = 5;
    $mail->SMTPKeepAlive = false;
    $mail->setFrom($_ENV['MAIL_FROM'] ?? $_ENV['SMTP_USER'] ?? 'noreply@quizapp.com', $_ENV['MAIL_FROM_NAME'] ?? $_ENV['SMTP_FROM_NAME'] ?? 'QuizApp');
    $mail->isHTML(true);
    return $mail;
}

function sendOTPEmail(string $toEmail, string $toName, int $otp): bool {
    $user = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
    $pass = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';

    // If SMTP credentials not provided, log and save in session for dev testing
    if (empty($user) || empty($pass)) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['dev_otp'] = $otp;
        error_log("DEV MODE OTP for {$toEmail}: {$otp}");
        return true;
    }

    try {
        set_time_limit(10);
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Your QuizApp OTP Code';
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto'>
                <h2 style='color:#1a1a1a'>Verify your email</h2>
                <p>Hi {$toName}, use the OTP below to verify your account.</p>
                <div style='font-size:32px;font-weight:700;letter-spacing:8px;color:#185FA5;margin:24px 0'>{$otp}</div>
                <p style='color:#666;font-size:13px'>This OTP expires in 10 minutes. Do not share it with anyone.</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        // Fallback to dev mode so registration does not fail
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['dev_otp'] = $otp;
        return true;
    }
}

function sendResultEmail(string $toEmail, string $toName, array $result): bool {
    $user = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
    $pass = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';

    if (empty($user) || empty($pass)) {
        return true;
    }

    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "Your Quiz Result — {$result['quiz_title']}";
        $passed  = ($result['score'] >= ($result['pass_mark'] ?? 60)) ? 'Passed' : 'Failed';
        $color   = $passed === 'Passed' ? '#1D9E75' : '#E24B4A';
        $mail->Body = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto'>
                <h2>Quiz Result</h2>
                <p>Hi {$toName}, here are your results for <strong>{$result['quiz_title']}</strong>.</p>
                <div style='font-size:28px;font-weight:700;color:{$color};margin:16px 0'>{$result['score']}%</div>
                <p style='color:{$color};font-weight:600'>{$passed}</p>
                <p style='color:#666;font-size:13px'>Time taken: {$result['time_taken']}</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}

function enqueueEmail(string $toEmail, string $toName, string $subject, string $body): bool {
    $db = getDB();
    $user = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
    $pass = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';

    // If in dev mode with no SMTP, save in dev session if OTP
    if (empty($user) || empty($pass)) {
        if (preg_match('/(\d{6})/', $body, $m)) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['dev_otp'] = (int)$m[1];
        }
    }

    try {
        $stmt = $db->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$toEmail, $toName, $subject, $body]);
        $queueId = $db->lastInsertId();

        // Register shutdown function to process queue asynchronously before terminating connection
        register_shutdown_function(function() {
            try {
                processEmailQueue(5);
            } catch (Exception $e) {}
        });

        return true;
    } catch (Exception $e) {
        error_log('Failed to enqueue email: ' . $e->getMessage());
        return false;
    }
}

function processEmailQueue(int $limit = 10): array {
    $db = getDB();
    $user = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
    $pass = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';

    if (empty($user) || empty($pass)) {
        // Mark pending as sent in dev mode
        $db->exec("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE status = 'pending'");
        return ['processed' => 0, 'sent' => 0, 'dev_mode' => true];
    }

    $stmt = $db->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY id ASC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $queue = $stmt->fetchAll();

    $sentCount = 0;
    foreach ($queue as $item) {
        try {
            $mail = getMailer();
            $mail->addAddress($item['to_email'], $item['to_name']);
            $mail->Subject = $item['subject'];
            $mail->Body    = $item['body'];
            $mail->send();

            $upd = $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?");
            $upd->execute([$item['id']]);
            $sentCount++;
        } catch (Exception $e) {
            $err = $e->getMessage();
            $upd = $db->prepare("UPDATE email_queue SET status = CASE WHEN attempts + 1 >= 3 THEN 'failed' ELSE 'pending' END, attempts = attempts + 1, error_message = ? WHERE id = ?");
            $upd->execute([$err, $item['id']]);
        }
    }

    return ['processed' => count($queue), 'sent' => $sentCount, 'dev_mode' => false];
}

function sendMail(string $toEmail, string $toName, string $subject, string $body): bool {
    return enqueueEmail($toEmail, $toName, $subject, nl2br(htmlspecialchars($body)));
}