<?php
/**
 * 推送测试 + 诊断工具
 * 访问: http://38.207.133.8/api/test_push.php?user_id=2
 */

header('Content-Type: application/json; charset=utf-8');

$userId = intval($_GET['user_id'] ?? 0);

require_once __DIR__ . '/db_connect.php';

// 诊断：直接查数据库
$diagnosis = [];
$diagnosis['db_name'] = DB_NAME;

// 检查 push_devices 表是否存在
$result = $conn->query("SHOW TABLES LIKE 'push_devices'");
$diagnosis['table_exists'] = ($result && $result->num_rows > 0);

if ($diagnosis['table_exists']) {
    // 列出该表所有记录
    $result = $conn->query("SELECT * FROM push_devices");
    $all = [];
    while ($row = $result->fetch_assoc()) {
        $all[] = $row;
    }
    $diagnosis['all_devices'] = $all;

    // 查指定用户
    $stmt = $conn->prepare("SELECT cid FROM push_devices WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $r = $stmt->get_result();
    $cids = [];
    while ($row = $r->fetch_assoc()) {
        $cids[] = $row['cid'];
    }
    $stmt->close();
    $diagnosis['cids_for_user'] = $cids;
} else {
    $diagnosis['all_devices'] = [];
    $diagnosis['cids_for_user'] = [];
}

// 尝试推送
require_once __DIR__ . '/unipush.php';

$title   = '测试推送';
$content = '这是来自服务器的测试消息，时间：' . date('H:i:s');
$payload = ['type' => 'chat', 'user_id' => 1];

$result = unipushSendToUser($conn, $userId, $title, $content, $payload);
$count = $result['success_count'];

echo json_encode([
    'success'        => $count > 0,
    'message'        => $count > 0 ? "已向 {$count} 台设备推送" : '推送失败',
    'device_count'   => $count,
    'push_details'   => $result['details'],
    'diagnosis'      => $diagnosis,
    'unipush_config' => [
        'appid_defined'  => defined('UNIPUSH_APPID'),
        'appid_value'    => UNIPUSH_APPID,
        'base_url'       => UNIPUSH_BASE,
        'token_cache_exists' => file_exists(UNIPUSH_TOKEN_CACHE),
    ],
], JSON_UNESCAPED_UNICODE);

$conn->close();
