<?php
/**
 * Certificate generation + lookup helpers.
 * Requires: config/db.php already loaded, TCPDF via composer.
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('CERT_DIR', __DIR__ . '/../uploads/certificates');
define('CERT_PASS_PERCENT', 60);

/**
 * Generate a certificate PDF for a passed attempt, if one doesn't already exist.
 * Returns the certificates table row (existing or newly created).
 */
function generateCertificateIfEligible(int $attemptId): ?array
{
    $db = getDB();

    // Already issued?
    $existing = $db->prepare("SELECT * FROM certificates WHERE attempt_id = ?");
    $existing->execute([$attemptId]);
    if ($row = $existing->fetch()) {
        return $row;
    }

    $stmt = $db->prepare("
        SELECT a.id, a.user_id, a.score, a.total_marks, a.submitted_at,
               u.name AS user_name, q.title AS quiz_title
        FROM attempts a
        JOIN users u   ON u.id = a.user_id
        JOIN quizzes q ON q.id = a.quiz_id
        WHERE a.id = ? AND a.is_completed = 1
    ");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();

    if (!$attempt || $attempt['total_marks'] <= 0) {
        return null;
    }

    $pct = round($attempt['score'] * 100 / $attempt['total_marks']);
    if ($pct < CERT_PASS_PERCENT) {
        return null; // didn't pass — no certificate
    }

    if (!is_dir(CERT_DIR)) {
        mkdir(CERT_DIR, 0775, true);
    }

    $uniqueCode = strtoupper(bin2hex(random_bytes(8)));
    $fileName   = 'cert_' . $attemptId . '.pdf';
    $filePath   = CERT_DIR . '/' . $fileName;
    $relPath    = 'uploads/certificates/' . $fileName;

    buildCertificatePdf($filePath, $attempt['user_name'], $attempt['quiz_title'], $pct, $uniqueCode, $attempt['submitted_at']);

    $insert = $db->prepare("
        INSERT INTO certificates (user_id, attempt_id, cert_path, unique_code)
        VALUES (?, ?, ?, ?)
    ");
    $insert->execute([$attempt['user_id'], $attemptId, $relPath, $uniqueCode]);

    $newId = $db->lastInsertId();
    $get = $db->prepare("SELECT * FROM certificates WHERE id = ?");
    $get->execute([$newId]);
    return $get->fetch();
}

function buildResultPdf(string $filePath, array $attempt, array $answers): void
{
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('QuizApp');
    $pdf->SetAuthor('QuizApp');
    $pdf->SetTitle('Quiz Result - ' . $attempt['quiz_title']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    $pct = $attempt['total_marks'] > 0 ? round($attempt['score'] * 100 / $attempt['total_marks']) : 0;
    $passed = $pct >= 60;

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(17, 19, 24);
    $pdf->Cell(0, 12, 'Quiz Result', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(0, 7, $attempt['quiz_title'], 0, 1, 'L');
    $pdf->Cell(0, 7, 'Student: ' . $attempt['user_name'], 0, 1, 'L');
    $pdf->Cell(0, 7, 'Date: ' . date('d M Y, h:i A', strtotime($attempt['submitted_at'])), 0, 1, 'L');
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor($passed ? 29 : 220, $passed ? 158 : 38, $passed ? 117 : 38);
    $pdf->Cell(0, 10, "Score: {$pct}% ({$attempt['score']}/{$attempt['total_marks']}) — " . ($passed ? 'Passed' : 'Not passed'), 0, 1, 'L');
    $pdf->Ln(6);

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(17, 19, 24);
    $pdf->Cell(0, 8, 'Answer breakdown', 0, 1, 'L');
    $pdf->Ln(1);

    foreach ($answers as $i => $a) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(17, 19, 24);
        $pdf->MultiCell(0, 6, ($i + 1) . '. ' . $a['question_text'], 0, 'L');

        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetTextColor($a['is_correct'] ? 29 : 220, $a['is_correct'] ? 158 : 38, $a['is_correct'] ? 117 : 38);
        $pdf->MultiCell(0, 5.5, '   Your answer: ' . ($a['selected_text'] ?? 'Skipped') . ($a['is_correct'] ? ' (Correct)' : ' (Incorrect)'), 0, 'L');

        if (!$a['is_correct']) {
            $pdf->SetTextColor(29, 158, 117);
            $pdf->MultiCell(0, 5.5, '   Correct answer: ' . ($a['correct_text'] ?? '-'), 0, 'L');
        }
        $pdf->Ln(2);
    }

    $pdf->Output($filePath, 'F');
}

function buildCertificatePdf(string $filePath, string $userName, string $quizTitle, int $pct, string $code, string $dateStr): void
{
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('QuizApp');
    $pdf->SetAuthor('QuizApp');
    $pdf->SetTitle('Certificate of Completion');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->AddPage();

    $w = $pdf->getPageWidth();
    $h = $pdf->getPageHeight();

    // Border
    $pdf->SetLineStyle(['width' => 1.2, 'color' => [24, 95, 165]]);
    $pdf->Rect(8, 8, $w - 16, $h - 16);
    $pdf->SetLineStyle(['width' => 0.4, 'color' => [24, 95, 165]]);
    $pdf->Rect(12, 12, $w - 24, $h - 24);

    $pdf->SetTextColor(17, 19, 24);
    $pdf->SetFont('helvetica', 'B', 30);
    $pdf->SetY(40);
    $pdf->Cell(0, 12, 'Certificate of Completion', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 13);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Ln(6);
    $pdf->Cell(0, 8, 'This certifies that', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 26);
    $pdf->SetTextColor(24, 95, 165);
    $pdf->Ln(2);
    $pdf->Cell(0, 14, $userName, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 13);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Ln(2);
    $pdf->Cell(0, 8, 'has successfully completed the quiz', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(17, 19, 24);
    $pdf->Ln(2);
    $pdf->Cell(0, 10, $quizTitle, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 14);
    $pdf->SetTextColor(29, 158, 117);
    $pdf->Ln(4);
    $pdf->Cell(0, 8, 'Score: ' . $pct . '%', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Ln(10);
    $pdf->Cell(0, 6, 'Issued on ' . date('d M Y', strtotime($dateStr)), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Certificate ID: ' . $code, 0, 1, 'C');

    $pdf->Output($filePath, 'F');
}