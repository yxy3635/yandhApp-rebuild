<?php
/**
 * AI 图片上传端点 — 接收图片文件，返回可公开访问的 URL
 *
 * POST multipart/form-data: image=@file
 * 返回: { success, url }
 */

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

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errMsg = '未接收到图片文件';
    if (isset($_FILES['image'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errMsg = '图片文件过大，请压缩后再试';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errMsg = '文件上传不完整，请重试';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errMsg = '未选择文件';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['image'];

// 校验文件类型
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => '不支持的图片格式，仅限 jpg/png/gif/webp/bmp'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 限制 10MB（松一些，因为客户端已压缩）
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => '图片过大，请选择小于 10MB 的图片'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/ai_images/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => '服务器存储目录创建失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$savedName = uniqid('ai_') . '.' . $ext;
$destPath = $uploadDir . $savedName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => '文件保存失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 构建公开 URL（匹配已有 uploads 目录模式）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url = $protocol . '://' . $host . '/uploads/ai_images/' . $savedName;

// 清理 1 小时前的旧文件
$maxAge = 3600;
foreach (glob($uploadDir . 'ai_*') as $oldFile) {
    if (time() - filemtime($oldFile) > $maxAge) {
        @unlink($oldFile);
    }
}

echo json_encode([
    'success' => true,
    'url' => $url,
], JSON_UNESCAPED_UNICODE);
