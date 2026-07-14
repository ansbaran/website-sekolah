<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

if (!enforce_rate_limit('feedback-submit', 5, 600)) {
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak pengiriman. Silakan coba lagi beberapa menit lagi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function feedback_plain_text($value, int $maxLength): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    $text = trim($text);

    if (mb_strlen($text, 'UTF-8') > $maxLength) {
        $text = mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return $text;
}

try {
    $honeypot = trim((string)($_POST['website_url'] ?? ''));
    if ($honeypot !== '') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Terima kasih. Feedback Anda akan ditinjau admin sebelum ditampilkan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $parentName = feedback_plain_text($_POST['parent_name'] ?? '', 120);
    $parentEmail = strtolower(feedback_plain_text($_POST['parent_email'] ?? '', 190));
    $relationLabel = feedback_plain_text($_POST['relation_label'] ?? 'Orang tua siswa', 120);
    $studentLabel = feedback_plain_text($_POST['student_label'] ?? '', 120);
    $message = feedback_plain_text($_POST['message'] ?? '', 700);
    $rating = round((float)($_POST['rating'] ?? 5), 1);
    $consentGiven = isset($_POST['consent_given']) && in_array((string)$_POST['consent_given'], ['1', 'on', 'true', 'yes'], true);

    $errors = [];

    if (mb_strlen($parentName, 'UTF-8') < 2) {
        $errors['parent_name'] = 'Nama minimal 2 karakter.';
    }

    if ($parentEmail === '' || !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['parent_email'] = 'Email aktif wajib diisi dengan format yang benar.';
    }

    if (mb_strlen($relationLabel, 'UTF-8') < 2) {
        $errors['relation_label'] = 'Keterangan orang tua/wali wajib diisi.';
    }

    $messageLength = mb_strlen($message, 'UTF-8');
    if ($messageLength < 20) {
        $errors['message'] = 'Feedback minimal 20 karakter agar konteksnya jelas.';
    } elseif ($messageLength > 700) {
        $errors['message'] = 'Feedback maksimal 700 karakter.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Rating harus berada di antara 1 sampai 5.';
    }

    if (!$consentGiven) {
        $errors['consent_given'] = 'Izin publikasi wajib dicentang sebelum feedback dikirim.';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Mohon periksa kembali data feedback.',
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userAgent = feedback_plain_text($_SERVER['HTTP_USER_AGENT'] ?? '', 255);
    $ipHash = hash('sha256', get_client_ip() . '|' . (defined('APP_KEY') ? APP_KEY : BASE_PATH));

    $stmt = $pdo->prepare(
        'INSERT INTO parent_feedback
            (parent_name, parent_email, relation_label, student_label, message, rating, display_order, is_approved, is_active, consent_given, submitted_ip_hash, submitted_user_agent, created_at)
         VALUES
            (:parent_name, :parent_email, :relation_label, :student_label, :message, :rating, 0, 0, 1, 1, :submitted_ip_hash, :submitted_user_agent, NOW())'
    );
    $stmt->execute([
        'parent_name' => $parentName,
        'parent_email' => $parentEmail,
        'relation_label' => $relationLabel,
        'student_label' => $studentLabel === '' ? null : $studentLabel,
        'message' => $message,
        'rating' => $rating,
        'submitted_ip_hash' => $ipHash,
        'submitted_user_agent' => $userAgent === '' ? null : $userAgent,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Terima kasih. Feedback Anda terkirim dan menunggu persetujuan admin.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server. Silakan coba lagi nanti.'], JSON_UNESCAPED_UNICODE);
}