<?php
// 画廊管理接口：仅操作 gallery.json（不删除远端图片）
// 功能：delete(按id删除)、setCategory(单条设置分类)、renameCategory(批量重命名)、deleteCategory(批量移至未分类)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求'], JSON_UNESCAPED_UNICODE);
    exit;
}

$galleryFile = __DIR__ . '/gallery.json';
// 简单日志
$manageLogDir = __DIR__ . '/logs';
$manageLogFile = $manageLogDir . '/manage.log';
if (!is_dir($manageLogDir)) { @mkdir($manageLogDir, 0755, true); }
function mlog($msg){ global $manageLogFile; @file_put_contents($manageLogFile, '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND); }

// 解析输入，支持 application/json 与表单
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower($_SERVER['CONTENT_TYPE']) : '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) { $input = []; }
} else {
    $input = $_POST;
}

$action = isset($input['action']) ? trim($input['action']) : '';
if ($action === '') {
    mlog('missing action');
    echo json_encode(['success' => false, 'message' => '缺少action'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 工具函数
function read_gallery($file) {
    if (!file_exists($file)) { return []; }
    $txt = @file_get_contents($file);
    if ($txt === false || $txt === '') { return []; }
    $data = json_decode($txt, true);
    return is_array($data) ? $data : [];
}

function write_gallery($file, $arr) {
    // 写入时加锁，避免并发覆盖
    $tmp = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($tmp === false) {
        return false;
    }
    return @file_put_contents($file, $tmp, LOCK_EX) !== false;
}

function resp($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// 读取现有数据
$list = read_gallery($galleryFile);

switch ($action) {
    case 'delete': {
        $id = isset($input['id']) ? trim($input['id']) : '';
        if ($id === '') resp(false, '缺少id');
        $before = count($list);
        $list = array_values(array_filter($list, function($it) use ($id){ return isset($it['id']) ? ($it['id'] !== $id) : true; }));
        $after = count($list);
        if ($after === $before) { mlog("delete id=$id not found"); resp(false, '未找到该条目'); }
        if (!write_gallery($galleryFile, $list)) resp(false, '写入失败');
        mlog("delete id=$id ok, total=$after");
        resp(true, '已删除', ['removed' => 1, 'total' => $after]);
    }
    case 'setCategory': {
        $id = isset($input['id']) ? trim($input['id']) : '';
        $category = isset($input['category']) ? trim($input['category']) : '未分类';
        if ($id === '') resp(false, '缺少id');
        $found = false;
        foreach ($list as &$it) {
            if (isset($it['id']) && $it['id'] === $id) { $it['category'] = $category === '' ? '未分类' : $category; $found = true; break; }
        }
        unset($it);
        if (!$found) { mlog("setCategory id=$id not found"); resp(false, '未找到该条目'); }
        if (!write_gallery($galleryFile, $list)) resp(false, '写入失败');
        mlog("setCategory id=$id -> '$category'");
        resp(true, '已更新', ['id' => $id, 'category' => $category]);
    }
    case 'renameCategory': {
        $from = isset($input['from']) ? trim($input['from']) : '';
        $to = isset($input['to']) ? trim($input['to']) : '';
        if ($from === '' || $to === '') resp(false, '缺少参数');
        if ($from === '未分类') resp(false, '“未分类”不可重命名');
        if ($from === $to) resp(false, '新旧分类相同，无需重命名');
        $cnt = 0;
        foreach ($list as &$it) {
            $cat = isset($it['category']) ? $it['category'] : '';
            if ($cat === $from) { $it['category'] = $to; $cnt++; }
        }
        unset($it);
        // 即使没有任何条目受影响，也视为成功（便于前端继续同步分类表）
        if (!write_gallery($galleryFile, $list)) resp(false, '写入失败');
        mlog("renameCategory '$from' -> '$to', updated=$cnt");
        resp(true, '已重命名', ['updated' => $cnt]);
    }
    case 'deleteCategory': {
        $name = isset($input['name']) ? trim($input['name']) : '';
        $replacement = isset($input['replacement']) ? trim($input['replacement']) : '未分类';
        if ($name === '') resp(false, '缺少分类名');
        $cnt = 0;
        foreach ($list as &$it) {
            $cat = isset($it['category']) ? $it['category'] : '';
            if ($cat === $name) { $it['category'] = $replacement === '' ? '未分类' : $replacement; $cnt++; }
        }
        unset($it);
        // 即使没有任何条目受影响，也视为成功（便于前端继续同步分类表）
        if ($cnt > 0) {
            if (!write_gallery($galleryFile, $list)) resp(false, '写入失败');
        }
        mlog("deleteCategory '$name' -> '$replacement', moved=$cnt");
        resp(true, '已删除分类', ['moved' => $cnt, 'to' => $replacement]);
    }
    default:
        mlog('unknown action '.$action);
        resp(false, '未知action');
}

?>
