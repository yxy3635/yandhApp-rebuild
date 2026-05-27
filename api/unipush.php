<?php
/**
 * UniPush 2.0 推送发送模块（基于个推 REST API V2）
 *
 * 文档：https://docs.getui.com/getui/server/rest_v2/introduction/
 *
 * 使用方式：
 *   require_once __DIR__ . '/unipush.php';
 *   unipushSendToUser($conn, $toUserId, '标题', '内容', ['type' => 'chat', 'user_id' => 1]);
 */

// ╔══════════════════════════════════════════════════════╗
// ║           UniPush 2.0 配置（DCloud 开发者中心获取）       ║
// ╚══════════════════════════════════════════════════════╝

define('UNIPUSH_APPID',         '0GYY3BTZEP9C8OpKkuv2E7');  // 应用 AppID
define('UNIPUSH_APPKEY',        '请在DCloud后台获取');       // 应用 AppKey
define('UNIPUSH_MASTER_SECRET', '请在DCloud后台获取');       // 推送 MasterSecret

// 个推 REST API V2 基础地址
define('UNIPUSH_BASE', 'https://restapi.getui.com/v2/' . UNIPUSH_APPID);

// Token 缓存文件
define('UNIPUSH_TOKEN_CACHE', __DIR__ . '/.unipush_token');

// ========== 内部函数 ==========

/**
 * SHA256 签名（用于个推鉴权）
 */
function _unipushSign($appkey, $timestamp, $masterSecret) {
    return hash('sha256', $appkey . $timestamp . $masterSecret);
}

/**
 * HTTP POST 请求
 */
function _unipushHttpPost($url, $postData, $headers = [], $timeout = 30) {
    $ch = curl_init();
    $defaultHeaders = ['Content-Type: application/json;charset=utf-8'];
    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($postData),
        CURLOPT_HTTPHEADER     => $allHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return [
        'body'     => $response,
        'httpCode' => $httpCode,
        'error'    => $error,
    ];
}

/**
 * 获取个推 Access Token
 * Token 有效期 1 天，缓存到文件避免频繁请求
 */
function unipushGetToken() {
    // 检查缓存
    if (file_exists(UNIPUSH_TOKEN_CACHE)) {
        $cached = json_decode(file_get_contents(UNIPUSH_TOKEN_CACHE), true);
        if ($cached && isset($cached['token'], $cached['expire_time'])) {
            if ((time() * 1000) < $cached['expire_time'] - 3600000) { // 提前 1 小时刷新
                return $cached['token'];
            }
        }
    }

    // 请求新 token
    $timestamp = strval(intval(microtime(true) * 1000));
    $sign = _unipushSign(UNIPUSH_APPKEY, $timestamp, UNIPUSH_MASTER_SECRET);

    $res = _unipushHttpPost(UNIPUSH_BASE . '/auth', [
        'sign'      => $sign,
        'timestamp' => $timestamp,
        'appkey'    => UNIPUSH_APPKEY,
    ]);

    error_log('[unipush] auth 请求: sign=' . $sign . ' ts=' . $timestamp);
    error_log('[unipush] auth 响应: httpCode=' . $res['httpCode'] . ' body=' . substr($res['body'], 0, 500));

    $data = json_decode($res['body'], true);
    if ($res['httpCode'] !== 200 || !$data || $data['code'] !== 0 || empty($data['data']['token'])) {
        error_log('[unipush] 获取 token 失败');
        return null;
    }

    $token = $data['data']['token'];
    $expireTime = intval($data['data']['expire_time']);

    file_put_contents(UNIPUSH_TOKEN_CACHE, json_encode([
        'token'       => $token,
        'expire_time' => $expireTime,
    ]));

    return $token;
}

// ========== 对外函数 ==========

/**
 * 向单个设备推送消息
 */
function unipushSendToOne($cid, $title, $content, $payload = []) {
    $token = unipushGetToken();
    if (!$token) {
        error_log('[unipush] 无 token，放弃推送');
        return ['ok' => false, 'error' => 'no token'];
    }

    $body = [
        'request_id' => uniqid('', true),
        'settings'   => [
            'ttl' => 3600000,
        ],
        'audience' => [
            'cid' => [$cid],
        ],
        'push_message' => [
            'notification' => [
                'title'      => $title,
                'body'       => $content,
                'click_type' => 'startapp',
            ],
        ],
    ];

    $res = _unipushHttpPost(UNIPUSH_BASE . '/push/single/cid', $body, [
        'token: ' . $token,
    ]);

    $data = json_decode($res['body'], true);
    $ok = ($res['httpCode'] === 200 && $data && $data['code'] === 0);

    error_log('[unipush] push 响应: httpCode=' . $res['httpCode'] . ' body=' . $res['body']);

    return [
        'ok'          => $ok,
        'http_code'   => $res['httpCode'],
        'getui_code'  => $data['code'] ?? null,
        'getui_msg'   => $data['msg'] ?? '',
        'raw_response' => $data,
    ];
}

/**
 * 向某个用户的所有设备推送消息
 */
function unipushSendToUser($conn, $userId, $title, $content, $payload = []) {
    error_log('[unipush] unipushSendToUser: user_id=' . $userId);

    $cids = [];
    $stmt = $conn->prepare("SELECT cid FROM push_devices WHERE user_id = ?");
    if (!$stmt) {
        error_log('[unipush] prepare 失败: ' . $conn->error);
    } else {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cids[] = $row['cid'];
        }
        $stmt->close();
    }

    error_log('[unipush] 查询到 ' . count($cids) . ' 个 CID');

    $results = [];
    $count = 0;
    foreach ($cids as $cid) {
        $r = unipushSendToOne($cid, $title, $content, $payload);
        $results[$cid] = $r;
        if ($r['ok']) {
            $count++;
        }
    }
    return ['success_count' => $count, 'details' => $results];
}
