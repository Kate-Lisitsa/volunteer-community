<?php
/**
 * JSON API: подсказки адреса (Nominatim). Только для авторизованных.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/nominatim.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method', 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Лимит Nominatim: не чаще ~1 обращения/сек (по IP)
$now = microtime(true);
$last = isset($_SESSION['nominatim_suggest_at']) ? (float)$_SESSION['nominatim_suggest_at'] : 0.0;
if ($now - $last < 1.05) {
    echo json_encode(['items' => [], 'wait' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION['nominatim_suggest_at'] = $now;

if (mb_strlen($q, 'UTF-8') < 3) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = nominatimSearchSuggestions($q);
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
