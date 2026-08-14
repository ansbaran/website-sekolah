<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
header('Cache-Control: no-store, no-cache, must-revalidate');
if (!enforce_rate_limit('public-staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 24;
if ($limit < 1 || $limit > 100) {
    $limit = 24;
}

function normalize_public_staff_category($value): string
{
    $category = (string)$value;
    return in_array($category, ['pimpinan', 'guru', 'staf'], true) ? $category : '';
}

try {
    $statement = $pdo->prepare('SELECT id, nama, kategori, jabatan, foto, deskripsi, email, whatsapp, instagram, facebook, tiktok, youtube, website, urutan FROM staff WHERE aktif = 1 ORDER BY urutan ASC, nama ASC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $staff = $statement->fetchAll();

    $data = array_map(static function (array $item): array {
        $email = normalize_optional_email((string)($item['email'] ?? ''));
        $whatsapp = normalize_whatsapp_number((string)($item['whatsapp'] ?? ''));
        $instagram = normalize_social_link((string)($item['instagram'] ?? ''), 'instagram');
        $facebook = normalize_social_link((string)($item['facebook'] ?? ''), 'facebook');
        $tiktok = normalize_social_link((string)($item['tiktok'] ?? ''), 'tiktok');
        $youtube = normalize_social_link((string)($item['youtube'] ?? ''), 'youtube');
        $website = normalize_public_url((string)($item['website'] ?? ''));

        return [
            'id' => (int)$item['id'],
            'nama' => html_entity_decode((string)$item['nama'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'kategori' => normalize_public_staff_category($item['kategori'] ?? ''),
            'jabatan' => html_entity_decode((string)$item['jabatan'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'foto' => $item['foto'] ? build_upload_url('staff', (string)$item['foto']) : '',
            'deskripsi' => html_entity_decode((string)($item['deskripsi'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'email' => $email === false ? '' : ($email ?? ''),
            'whatsapp' => $whatsapp === false ? '' : ($whatsapp ?? ''),
            'instagram' => $instagram === false ? '' : ($instagram ?? ''),
            'facebook' => $facebook === false ? '' : ($facebook ?? ''),
            'tiktok' => $tiktok === false ? '' : ($tiktok ?? ''),
            'youtube' => $youtube === false ? '' : ($youtube ?? ''),
            'website' => $website === false ? '' : ($website ?? ''),
            'urutan' => (int)$item['urutan'],
        ];
    }, $staff);

    echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    log_exception($e);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
