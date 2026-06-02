<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_connect.php';

// 获取表单数据
$userId = $_POST['user_id'] ?? null;

if ($userId === null || !is_numeric($userId)) {
    echo json_encode(["success" => false, "message" => "用户ID无效"]);
    exit();
}

// 检查是否有文件上传
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "没有上传文件或上传出错"]);
    exit();
}

$file = $_FILES['image'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// 检查文件类型
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedMimes) && !in_array($fileExtension, $allowTypes)) {
    echo json_encode(["success" => false, "message" => "不支持的文件类型"]);
    exit();
}

// 限制文件大小 (10MB)
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(["success" => false, "message" => "文件大小不能超过10MB"]);
    exit();
}

// 创建上传目录
$uploadDir = __DIR__ . '/../uploads/footprints/' . $userId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 生成唯一文件名
$newFileName = 'fp_' . time() . '_' . uniqid() . '.' . $fileExtension;
$targetFilePath = $uploadDir . $newFileName;

if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    $imageUrl = 'uploads/footprints/' . $userId . '/' . $newFileName;
    echo json_encode([
        "success" => true,
        "message" => "图片上传成功",
        "image_url" => $imageUrl
    ]);
} else {
    echo json_encode(["success" => false, "message" => "图片保存失败"]);
}
?>
