<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/unipush.php'; // 引入推送模块

$data = json_decode(file_get_contents('php://input'), true);

$from_user_id = intval($data['from_user_id'] ?? 0);
$to_user_id = intval($data['to_user_id'] ?? 0);
$content = trim($data['content'] ?? '');

if ($from_user_id && $to_user_id && $content !== '') {
    $stmt = $conn->prepare("INSERT INTO messages (from_user_id, to_user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $from_user_id, $to_user_id, $content);
    $success = $stmt->execute();
    $stmt->close();

    // 推送通知给接收者
    if ($success) {

        // 获取发送者用户名
        $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->bind_param('i', $from_user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $fromUsername = '';
        if ($row = $userResult->fetch_assoc()) {
            $fromUsername = $row['username'];
        }
        $userStmt->close();

        $pushTitle = $fromUsername ?: '新私信';
        $pushContent = mb_strlen($content) > 50 ? mb_substr($content, 0, 50) . '...' : $content;
        $pushPayload = ['type' => 'chat', 'user_id' => $from_user_id, 'username' => $fromUsername];

        unipushSendToUser($conn, $to_user_id, $pushTitle, $pushContent, $pushPayload);
    }

    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
}
$conn->close();
?>