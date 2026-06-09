<?php
/**
 * OCR API — 硅基流动 视觉模型 OCR
 *
 * 前端请求：
 *   POST { image_url: "http://host/uploads/ai_images/xxx.jpg" }   ← 推荐
 *   POST { image: "data:image/...;base64,..." }                    ← 备选
 * 返回：JSON { success, text, message? }
 */

// ★ 强制所有输出为 JSON，避免 PHP 报错 HTML 污染响应
error_reporting(0);
ini_set('display_errors', 0);
set_error_handler(function () {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器内部错误，请查看 ocr_debug.log'], JSON_UNESCAPED_UNICODE);
    exit;
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ╔══════════════════════════════════════════════════════╗
// ║                   配  置  区                          ║
// ╚══════════════════════════════════════════════════════╝

$API_KEY = '';
$API_BASE = 'https://api.siliconflow.cn/v1/chat/completions';
$MODEL = 'Qwen/Qwen3-VL-32B-Instruct';

// 调试日志文件（设为空字符串禁用）
$LOG_FILE = __DIR__ . '/ocr_debug.log';

function ocrLog($msg) {
    global $LOG_FILE;
    if (empty($LOG_FILE)) return;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// ====== 不依赖扩展的工具函数 ======

// 根据文件扩展名获取 MIME 类型（替代 mime_content_type，不依赖 fileinfo 扩展）
function getMimeFromExt($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
    ];
    return $map[$ext] ?? 'image/jpeg';
}

// 安全截取字符串（不依赖 mbstring 扩展）
function safeSubstr($str, $start, $length = null) {
    if (function_exists('mb_substr')) {
        return mb_substr($str, $start, $length);
    }
    if ($length === null) {
        return substr($str, $start);
    }
    return substr($str, $start, $length);
}

// ╔══════════════════════════════════════════════════════╗
// ║                   解析请求                             ║
// ╚══════════════════════════════════════════════════════╝

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    ocrLog('请求体 JSON 解析失败，原始数据长度: ' . strlen($rawInput));
    echo json_encode(['success' => false, 'message' => '请求数据格式错误'], JSON_UNESCAPED_UNICODE);
    exit;
}

$imageDataUrl = null;

// 方式1（推荐）：image_url — 已上传到本服务器的图片 URL，本地读文件
if (!empty($input['image_url'])) {
    $imgUrl = $input['image_url'];
    ocrLog("image_url 模式: $imgUrl");

    $urlPath = parse_url($imgUrl, PHP_URL_PATH);
    if (empty($urlPath)) {
        ocrLog("无法解析 URL 路径: $imgUrl");
        echo json_encode(['success' => false, 'message' => '图片 URL 格式错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $localPath = __DIR__ . '/../' . ltrim($urlPath, '/');
    ocrLog("本地文件路径: $localPath");

    if (!file_exists($localPath)) {
        ocrLog("文件不存在，回退到远程 URL 模式: $localPath");
        $imageDataUrl = $imgUrl;
    } else {
        $fileContent = file_get_contents($localPath);
        if ($fileContent === false) {
            ocrLog("读取文件失败: $localPath");
            echo json_encode(['success' => false, 'message' => '读取图片文件失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $mime = getMimeFromExt($localPath);
        $imageDataUrl = 'data:' . $mime . ';base64,' . base64_encode($fileContent);
        ocrLog("本地文件读取成功, MIME: $mime, 大小: " . strlen($fileContent) . " bytes");
    }
}

// 方式2（备选）：image — 前端直接传 base64 data URL
if (empty($imageDataUrl) && !empty($input['image'])) {
    $imageDataUrl = $input['image'];
    ocrLog("image (base64) 模式, 数据长度: " . strlen($imageDataUrl));
}

if (empty($imageDataUrl)) {
    ocrLog("未提供图片数据");
    echo json_encode(['success' => false, 'message' => '缺少图片数据，请提供 image 或 image_url 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 校验格式
if (strpos($imageDataUrl, 'data:image/') !== 0 && strpos($imageDataUrl, 'http') !== 0) {
    ocrLog("图片格式不正确: " . substr($imageDataUrl, 0, 50));
    echo json_encode(['success' => false, 'message' => '图片格式不正确'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 默认提示词：全面描述图片内容（文字 + 视觉信息），让 AI 聊天能理解图片
$prompt = isset($input['prompt']) ? $input['prompt'] : '请仔细观察这张图片，然后分两部分输出：\n\n1. 【图片中的文字】：如果图片中有任何文字、数字、按钮标签、菜单项、代码等，请完整提取出来。如果没有任何文字，写"无文字"。\n\n2. 【图片内容描述】：描述图片中有什么——物体、人物、场景、UI界面、图表、颜色、布局等。尽可能详细，让一个看不到图片的人能通过你的描述理解图片内容。\n\n请直接输出，不要加"好的""我来描述一下"之类的开场白。';

// ╔══════════════════════════════════════════════════════╗
// ║               调用硅基流动 API                          ║
// ╚══════════════════════════════════════════════════════╝

$requestBody = json_encode([
    'model' => $MODEL,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $imageDataUrl,
                        'detail' => 'high',
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => $prompt,
                ],
            ],
        ],
    ],
    'stream' => false,
    'max_tokens' => 4096,
]);

ocrLog("调用模型: $MODEL");

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $API_BASE,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $API_KEY,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$responseBody = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
$curlInfo     = curl_getinfo($ch);
curl_close($ch);

ocrLog("SiliconFlow HTTP: $httpCode, 耗时: {$curlInfo['total_time']}s");

// ╔══════════════════════════════════════════════════════╗
// ║                   处理响应                             ║
// ╚══════════════════════════════════════════════════════╝

if ($curlError) {
    ocrLog("cURL 错误: $curlError");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'OCR API 连接失败: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    ocrLog("HTTP 错误 $httpCode, 响应: " . safeSubstr($responseBody, 0, 500));
    $errData = json_decode($responseBody, true);
    $errMsg = isset($errData['error']['message']) ? $errData['error']['message'] : ('HTTP ' . $httpCode);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseData = json_decode($responseBody, true);

if (!$responseData) {
    ocrLog("响应 JSON 解析失败: " . safeSubstr($responseBody, 0, 500));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'API 响应解析失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ocrText = isset($responseData['choices'][0]['message']['content']) ? $responseData['choices'][0]['message']['content'] : '';

// 过滤 thinking 模型的 <think>...</think> 标签
$ocrText = preg_replace('/<think>.*?<\/think>/s', '', $ocrText);
$ocrText = trim($ocrText);

ocrLog("OCR 结果长度: " . strlen($ocrText) . " 字, 前100字: " . safeSubstr($ocrText, 0, 100));

if (empty($ocrText)) {
    ocrLog("OCR 结果为空");
    echo json_encode(['success' => false, 'message' => 'OCR 结果为空，请重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'text'    => $ocrText,
], JSON_UNESCAPED_UNICODE);
