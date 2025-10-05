<?php
// 分类管理接口：读取/新增/重命名/删除分类，独立存储在 categories.json
// 动作：
//  - list: 返回全部分类数组
//  - add: name
//  - rename: from, to
//  - remove: name  （仅从分类表移除，不改动 gallery.json 内已有数据）

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$categoriesFile = __DIR__ . '/categories.json';
// 简单文件日志
$catLogFile = __DIR__ . '/logs/categories.log';
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0755, true); }
function cat_log($msg){
    global $catLogFile;
    $line = '['.date('Y-m-d H:i:s').'] '.$msg."\n";
    @file_put_contents($catLogFile, $line, FILE_APPEND);
}

function read_categories($file){
    if (!file_exists($file)) return ['未分类'];
    $txt = @file_get_contents($file);
    $arr = json_decode($txt, true);
    if (!is_array($arr)) $arr = ['未分类'];
    // 去重+清洗
    $set = [];
    foreach ($arr as $c){
        $c = trim((string)$c);
        if ($c !== '') $set[$c] = true;
    }
    if (!isset($set['未分类'])) $set['未分类'] = true;
    return array_keys($set);
}

function write_categories($file, $arr){
    $arr = array_values(array_unique(array_map(function($s){ return trim((string)$s); }, $arr)));
    if (!in_array('未分类', $arr, true)) array_unshift($arr, '未分类');
    return @file_put_contents($file, json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if ($method === 'POST'){
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw ?: 'null', true) ?: [];
    } else {
        $input = $_POST;
    }
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
cat_log("$method action=$action input=".json_encode($input, JSON_UNESCAPED_UNICODE));
if ($method === 'GET' && ($action === '' || $action === 'list')){
    $data = read_categories($categoriesFile);
    cat_log('list -> '.count($data).' cats');
    echo json_encode(['success'=>true, 'data'=> $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST'){
    http_response_code(405);
    cat_log('405 method not allowed');
    echo json_encode(['success'=>false, 'message'=>'只允许GET(list)与POST操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'add':
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') { echo json_encode(['success'=>false,'message'=>'分类名不能为空'], JSON_UNESCAPED_UNICODE); exit; }
        $list = read_categories($categoriesFile);
        if (!in_array($name, $list, true)) $list[] = $name;
        if (!write_categories($categoriesFile, $list)) { cat_log('add write failed'); echo json_encode(['success'=>false,'message'=>'写入失败'], JSON_UNESCAPED_UNICODE); exit; }
        cat_log("add '$name' ok, total=".count($list));
        echo json_encode(['success'=>true, 'data'=>$list], JSON_UNESCAPED_UNICODE); exit;
    case 'rename':
        $from = trim((string)($input['from'] ?? ''));
        $to = trim((string)($input['to'] ?? ''));
        if ($from === '' || $to === '') { echo json_encode(['success'=>false,'message'=>'缺少参数'], JSON_UNESCAPED_UNICODE); exit; }
        if ($from === '未分类') { echo json_encode(['success'=>false,'message'=>'“未分类”不可重命名'], JSON_UNESCAPED_UNICODE); exit; }
        if ($from === $to) { echo json_encode(['success'=>false,'message'=>'新旧分类相同，无需重命名'], JSON_UNESCAPED_UNICODE); exit; }
    $list = read_categories($categoriesFile);
    // 明确移除原分类（不用 array_filter 以避免某些环境的误报）
    $newList = [];
    foreach ($list as $c) {
        // 统一按字符串比较，避免 JSON 中数字/字符串混用导致对比失败
        if ((string)$c !== (string)$from) { $newList[] = (string)$c; }
    }
    // 添加新分类（如已存在则不重复）
    $toStr = (string)$to;
    if (!in_array($toStr, array_map('strval', $newList), true)) { $newList[] = $toStr; }
    if (!write_categories($categoriesFile, $newList)) { cat_log('rename write failed'); echo json_encode(['success'=>false,'message'=>'写入失败'], JSON_UNESCAPED_UNICODE); exit; }
        cat_log("rename '$from' -> '$to' ok");
    echo json_encode(['success'=>true, 'data'=>$newList], JSON_UNESCAPED_UNICODE); exit;
    case 'remove':
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') { echo json_encode(['success'=>false,'message'=>'缺少分类名'], JSON_UNESCAPED_UNICODE); exit; }
        if ($name === '未分类') { echo json_encode(['success'=>false,'message'=>'“未分类”不可删除'], JSON_UNESCAPED_UNICODE); exit; }
        $list = read_categories($categoriesFile);
        $newList = [];
        foreach ($list as $c) {
            if ((string)$c !== (string)$name) { $newList[] = (string)$c; }
        }
        if (!write_categories($categoriesFile, $newList)) { cat_log('remove write failed'); echo json_encode(['success'=>false,'message'=>'写入失败'], JSON_UNESCAPED_UNICODE); exit; }
        cat_log("remove '$name' ok, total=".count($newList));
        echo json_encode(['success'=>true, 'data'=>$newList], JSON_UNESCAPED_UNICODE); exit;
    default:
        cat_log('unknown action');
        echo json_encode(['success'=>false, 'message'=>'未知action'], JSON_UNESCAPED_UNICODE); exit;
}
