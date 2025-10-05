<?php
// 通过接口更新 config.php 中的 COOKIE2_VALUE（便于每周更换）
// 仅修改本地配置，不做任何外部请求

// 确保任何情况下都只输出合法 JSON，避免前端解析失败
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('html_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

set_error_handler(function($severity, $message, $file, $line){
    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'message' => '服务器内部错误，请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
});

set_exception_handler(function($e){
    if (ob_get_level()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'message' => '服务器异常，请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: 'null', true);
} else {
    $body = $_POST;
}

$cookie = isset($body['cookie2']) ? trim($body['cookie2']) : '';
if ($cookie === '') {
    echo json_encode(['success' => false, 'message' => 'cookie2 不能为空'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strlen($cookie) < 8 || strlen($cookie) > 2048) {
    echo json_encode(['success' => false, 'message' => 'cookie2 长度不合法'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = __DIR__ . '/config.php';
if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
    echo json_encode(['success' => false, 'message' => 'config.php 无法读写，请检查权限'], JSON_UNESCAPED_UNICODE);
    exit;
}

$src = file_get_contents($file);
if ($src === false) {
    echo json_encode(['success' => false, 'message' => '读取配置失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 构造安全的 PHP 单引号字符串
$safe = str_replace(["\\", "'"], ["\\\\", "\\'"], $cookie);

$pattern = "/define\(\s*'COOKIE2_VALUE'\s*,\s*'[^']*'\s*\)\s*;\s*/";
$replacement = "define('COOKIE2_VALUE', '" . $safe . "');\n";

if (preg_match($pattern, $src)) {
    $newSrc = preg_replace($pattern, $replacement, $src, 1);
} else {
    // 回退：尝试在 GOOFISH_UPLOAD_URL 定义后插入一行，或在文件末尾追加
    $insPattern = "/define\(\s*'GOOFISH_UPLOAD_URL'[^;]*;\s*/";
    if (preg_match($insPattern, $src, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        $newSrc = substr($src, 0, $pos) . "\n" . $replacement . substr($src, $pos);
    } else {
        $newSrc = rtrim($src) . "\n" . $replacement . "\n";
    }
}

$ok = file_put_contents($file, $newSrc, LOCK_EX) !== false;
if (!$ok) {
    echo json_encode(['success' => false, 'message' => '写入配置失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 返回脱敏后的 cookie2
$mask = (strlen($cookie) > 6) ? (str_repeat('*', max(0, strlen($cookie) - 6)) . substr($cookie, -6)) : $cookie;

if (ob_get_level()) { ob_clean(); }
echo json_encode(['success' => true, 'message' => '已更新', 'cookie2' => $mask], JSON_UNESCAPED_UNICODE);
exit;
?>
