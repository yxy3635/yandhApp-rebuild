<?php
/**
 * 每日纪念日推送（cron 定时任务）
 *
 * 建议 crontab：每天上午 9:00 执行
 *   0 9 * * * /usr/bin/php /www/wwwroot/HandYapp/api/cron_anniversary_push.php >> /tmp/anniv_push.log 2>&1
 *
 * 逻辑：找到距离今天最近的纪念日（30天内），推送给所有注册了推送设备的用户
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/unipush.php';

// ========== 纪念日计算（仅处理公历，农历暂跳过） ==========

/**
 * 判断是否每年重复（生日类自动识别）
 */
function isRepeatYearly($row) {
    if (!empty($row['repeat_yearly'])) return true;
    $title = $row['title'] ?? '';
    return (bool) preg_match('/生日|诞辰/u', $title);
}

/**
 * 计算下一个触发日期
 */
function computeNextDate($row) {
    $today = new DateTime('today');

    // 农历日期 — PHP 端暂不支持，返回 null（跳过）
    if (!empty($row['is_lunar'])) {
        return null;
    }

    $dateStr = $row['date'] ?? '';
    if (empty($dateStr)) return null;

    $parsed = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$parsed) {
        $parsed = @new DateTime($dateStr);
        if (!$parsed) return null;
    }

    if (isRepeatYearly($row)) {
        // 每年重复：找到今年的日期，过了就取明年
        $next = DateTime::createFromFormat('Y-m-d',
            $today->format('Y') . '-' . $parsed->format('m-d'));
        if (!$next || $next < $today) {
            $next = DateTime::createFromFormat('Y-m-d',
                ($today->format('Y') + 1) . '-' . $parsed->format('m-d'));
        }
        return $next;
    } else {
        // 不重复：检查今年是否还在未来
        $thisYear = DateTime::createFromFormat('Y-m-d',
            $today->format('Y') . '-' . $parsed->format('m-d'));
        if ($thisYear && $thisYear >= $today) return $thisYear;
        return null; // 已过期
    }
}

/**
 * 生成纪念日描述文本
 */
function describeAnniversary($row, $daysLeft, $isToday) {
    $name = $row['title'] ?? '纪念日';

    if ($isToday) {
        return "今天是「{$name}」！";
    } elseif ($daysLeft === 1) {
        return "明天就是「{$name}」了~";
    } else {
        return "还有 {$daysLeft} 天就是「{$name}」";
    }
}

// ========== 主逻辑 ==========

echo "[" . date('Y-m-d H:i:s') . "] 开始执行每日纪念日推送\n";

// 0. 确保去重表存在
$conn->query("CREATE TABLE IF NOT EXISTS push_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    anniversary_id INT NOT NULL,
    push_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_push (user_id, anniversary_id, push_date),
    INDEX idx_push_date (push_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 1. 获取所有纪念日
$result = $conn->query("SELECT id, user_id, title, date, is_lunar, repeat_yearly FROM anniversaries ORDER BY date ASC");
if (!$result) {
    echo "查询纪念日失败: " . $conn->error . "\n";
    $conn->close();
    exit(1);
}

$anniversaries = [];
while ($row = $result->fetch_assoc()) {
    $anniversaries[] = $row;
}
echo "共查询到 " . count($anniversaries) . " 条纪念日\n";

// 2. 计算每个纪念日的剩余天数，找到最近的一个
$today = new DateTime('today');
$nearest = null;
$nearestDays = PHP_INT_MAX;
$nearestIsToday = false;

foreach ($anniversaries as $ann) {
    $next = computeNextDate($ann);
    if (!$next) continue; // 跳过农历或无法计算的

    $diff = (int) $today->diff($next)->format('%r%a');
    if ($diff < 0) continue; // 已过期

    if ($diff === 0) {
        $nearest = $ann;
        $nearestDays = 0;
        $nearestIsToday = true;
        break; // 就是今天，优先
    }

    if ($diff <= 30 && $diff < $nearestDays) {
        $nearest = $ann;
        $nearestDays = $diff;
        $nearestIsToday = false;
    }
}

if (!$nearest) {
    echo "30 天内无纪念日，跳过推送\n";
    $conn->close();
    exit(0);
}

echo "最近纪念日: {$nearest['title']} (还有 {$nearestDays} 天)\n";

// 3. 获取所有注册了推送设备的用户
$result = $conn->query("SELECT DISTINCT user_id FROM push_devices");
$userIds = [];
while ($row = $result->fetch_assoc()) {
    $userIds[] = (int) $row['user_id'];
}
echo "共 " . count($userIds) . " 个设备用户\n";

if (empty($userIds)) {
    echo "无推送设备，跳过\n";
    $conn->close();
    exit(0);
}

// 4. 逐个推送（带每日去重）
$pushTitle = $nearestIsToday ? '今天是「' . $nearest['title'] . '」！' : '纪念日提醒';
$pushContent = describeAnniversary($nearest, $nearestDays, $nearestIsToday);
$pushPayload = ['type' => 'anniversary', 'anniversary_id' => (int) $nearest['id']];
$anniversaryId = (int) $nearest['id'];

$successCount = 0;
$failCount = 0;
$skipCount = 0;

$checkStmt = $conn->prepare("SELECT id FROM push_logs WHERE user_id = ? AND anniversary_id = ? AND push_date = CURDATE()");
$logStmt = $conn->prepare("INSERT INTO push_logs (user_id, anniversary_id, push_date) VALUES (?, ?, CURDATE())");

foreach ($userIds as $uid) {
    // 检查今天是否已推送过该纪念日给该用户
    $checkStmt->bind_param('ii', $uid, $anniversaryId);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $skipCount++;
        $checkStmt->free_result();
        continue;
    }
    $checkStmt->free_result();

    $res = unipushSendToUser($conn, $uid, $pushTitle, $pushContent, $pushPayload);
    $successCount += $res['success_count'];
    $failCount += (count($res['details']) - $res['success_count']);

    // 记录已推送
    if ($res['success_count'] > 0) {
        $logStmt->bind_param('ii', $uid, $anniversaryId);
        $logStmt->execute();
    }
}

$checkStmt->close();
$logStmt->close();

echo "推送完成: 成功 {$successCount} 台，失败 {$failCount} 台，跳过 {$skipCount} 人（今日已推送）\n";
echo "[" . date('Y-m-d H:i:s') . "] 每日纪念日推送结束\n";

$conn->close();
