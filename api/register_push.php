<?php
/**
 * 推送 CID 注册接口
 *
 * 前端在获取到 UniPush CID 后调用此接口，将设备与用户绑定
 *
 * 请求：POST { user_id, cid, platform }
 * 返回：{ success, message }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持 POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId   = intval($input['user_id'] ?? 0);
$cid      = trim($input['cid'] ?? '');
$platform = trim($input['platform'] ?? 'android');

if (!$userId || !$cid) {
    echo json_encode(['success' => false, 'message' => '参数缺失']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

// 确保表存在（首次自动建表）
$conn->query("
    CREATE TABLE IF NOT EXISTS push_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        cid VARCHAR(128) NOT NULL,
        platform VARCHAR(20) DEFAULT 'android',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_cid (user_id, cid),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// 插入或更新 CID（同一用户+设备重复上报时更新 platform 和时间）
$stmt = $conn->prepare("
    INSERT INTO push_devices (user_id, cid, platform, updated_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE platform = VALUES(platform), updated_at = NOW()
");
$stmt->bind_param('iss', $userId, $cid, $platform);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'CID 已注册'], JSON_UNESCAPED_UNICODE);
} else {
    error_log('[register_push] 写入失败: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => '写入失败']);
}

$stmt->close();
$conn->close();
