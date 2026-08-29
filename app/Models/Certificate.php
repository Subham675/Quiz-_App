<?php
namespace App\Models;

use App\Core\Model;
use TCPDF;

class Certificate extends Model
{
    public const PASS_PERCENT = 60;
    public const UPLOAD_DIR = __DIR__ . '/../../uploads/certificates';

    public static function getUserCertificates(int $userId): array
    {
        return self::fetchAll("
            SELECT c.*, q.title AS quiz_title, a.score, a.total_marks
            FROM certificates c
            JOIN attempts a ON a.id = c.attempt_id
            JOIN quizzes q  ON q.id = a.quiz_id
            WHERE c.user_id = ?
            ORDER BY c.issued_at DESC
        ", [$userId]);
    }

    public static function getForAttempt(int $attemptId): ?array
    {
        return self::fetchOne("SELECT * FROM certificates WHERE attempt_id = ?", [$attemptId]);
    }

    public static function generateIfEligible(int $attemptId): ?array
    {
        $existing = self::getForAttempt($attemptId);
        if ($existing) {
            $absPath = __DIR__ . '/../../' . $existing['cert_path'];
            if (!file_exists($absPath)) {
                $attempt = Attempt::findById($attemptId);
                if ($attempt && $attempt['total_marks'] > 0) {
                    $pct = round($attempt['score'] * 100 / $attempt['total_marks']);
                    if (!is_dir(self::UPLOAD_DIR)) {
                        mkdir(self::UPLOAD_DIR, 0775, true);
                    }
                    self::buildPdf($absPath, $attempt['user_name'], $attempt['quiz_title'], $pct, $existing['unique_code'], $attempt['submitted_at']);
                }
            }
            return $existing;
        }

        $attempt = Attempt::findById($attemptId);
        if (!$attempt || empty($attempt['is_completed']) || $attempt['total_marks'] <= 0) {
            return null;
        }

        $pct = round($attempt['score'] * 100 / $attempt['total_marks']);
        if ($pct < self::PASS_PERCENT) {
            return null;
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0775, true);
        }

        $uniqueCode = strtoupper(bin2hex(random_bytes(8)));
        $fileName   = 'cert_' . $attemptId . '.pdf';
        $filePath   = self::UPLOAD_DIR . '/' . $fileName;
        $relPath    = 'uploads/certificates/' . $fileName;

        self::buildPdf($filePath, $attempt['user_name'], $attempt['quiz_title'], $pct, $uniqueCode, $attempt['submitted_at']);

        self::query("
            INSERT INTO certificates (user_id, attempt_id, cert_path, unique_code)
            VALUES (?, ?, ?, ?)
        ", [$attempt['user_id'], $attemptId, $relPath, $uniqueCode]);

        $newId = self::lastInsertId();
        return self::fetchOne("SELECT * FROM certificates WHERE id = ?", [$newId]);
    }

    public static function buildPdf(string $filePath, string $userName, string $quizTitle, int $pct, string $code, string $dateStr): void
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('QuizApp');
        $pdf->SetAuthor('QuizApp');
        $pdf->SetTitle('Certificate of Completion');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->SetDrawColor(24, 95, 165);
        $pdf->SetLineWidth(3);
        $pdf->Rect(10, 10, 277, 190);
        $pdf->SetDrawColor(200, 220, 240);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(13, 13, 271, 184);

        $pdf->SetY(28);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(24, 95, 165);
        $pdf->Cell(0, 8, 'QUIZAPP ACADEMY', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(20, 30, 50);
        $pdf->Cell(0, 14, 'Certificate of Completion', 0, 1, 'C');

        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(100, 110, 130);
        $pdf->Cell(0, 6, 'This is proudly presented to', 0, 1, 'C');

        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(24, 95, 165);
        $pdf->Cell(0, 10, $userName, 0, 1, 'C');

        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(80, 90, 110);
        $pdf->Cell(0, 6, 'for successfully completing the assessment', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(20, 30, 50);
        $pdf->Cell(0, 9, $quizTitle, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(29, 158, 117);
        $pdf->Cell(0, 7, 'with a score of ' . $pct . '%', 0, 1, 'C');

        $pdf->SetY(165);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(150, 160, 175);
        $dateFormatted = date('d F Y', strtotime($dateStr));
        $pdf->Cell(0, 5, 'Issued on: ' . $dateFormatted . '  |  Certificate ID: ' . $code, 0, 1, 'C');

        $pdf->Output($filePath, 'F');
    }
}
