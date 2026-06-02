<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_connect.php';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetFootprints();
            break;
        case 'POST':
            handleCreateFootprint();
            break;
        case 'PUT':
            handleUpdateFootprint();
            break;
        case 'DELETE':
            handleDeleteFootprint();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}

// 获取足迹列表或单个足迹
function handleGetFootprints() {
    global $conn;

    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $footprint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少用户ID']);
        return;
    }

    // 获取统计数据（省份列表等）
    if (isset($_GET['stats']) && $_GET['stats'] === '1') {
        $stats_sql = "SELECT DISTINCT province FROM footprints WHERE user_id = ? AND province IS NOT NULL AND province != ''";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param('i', $user_id);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();

        $provinces = [];
        while ($row = $stats_result->fetch_assoc()) {
            $provinces[] = $row['province'];
        }

        $count_sql = "SELECT COUNT(*) as total FROM footprints WHERE user_id = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param('i', $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];

        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => (int)$total,
                'provinces' => $provinces,
                'province_count' => count($provinces)
            ]
        ]);
        return;
    }

    // 获取单个足迹详情
    if ($footprint_id) {
        $sql = "SELECT * FROM footprints WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $footprint_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $footprint = $result->fetch_assoc();

        if (!$footprint) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '足迹不存在']);
            return;
        }

        $footprint['images'] = $footprint['images'] ? json_decode($footprint['images'], true) : [];

        echo json_encode([
            'success' => true,
            'footprints' => [$footprint]
        ]);
        return;
    }

    // 获取所有足迹列表
    $sql = "SELECT * FROM footprints WHERE user_id = ? ORDER BY visited_date DESC, created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $footprints = [];
    while ($row = $result->fetch_assoc()) {
        $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
        $footprints[] = $row;
    }

    echo json_encode([
        'success' => true,
        'footprints' => $footprints
    ]);
}

// 创建足迹
function handleCreateFootprint() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的请求数据']);
        return;
    }

    $user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $location_name = isset($input['location_name']) ? trim($input['location_name']) : '';
    $province = isset($input['province']) ? trim($input['province']) : '';
    $city = isset($input['city']) ? trim($input['city']) : '';
    $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
    $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
    $visited_date = isset($input['visited_date']) ? trim($input['visited_date']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $images = isset($input['images']) ? $input['images'] : [];

    // 验证必填字段
    if (!$user_id || !$title || !$location_name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必填字段（标题和地点）']);
        return;
    }

    // 处理图片上传
    $uploaded_images = [];
    if (!empty($images)) {
        $uploaded_images = handleFootprintImages($images, $user_id);
    }

    // 插入数据库
    $sql = "INSERT INTO footprints (user_id, title, location_name, province, city, latitude, longitude, visited_date, description, images)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $images_json = json_encode($uploaded_images);

    $stmt->bind_param('issssddsss',
        $user_id, $title, $location_name, $province, $city,
        $latitude, $longitude, $visited_date, $description, $images_json
    );

    if ($stmt->execute()) {
        $footprint_id = $conn->insert_id;

        // 获取创建的足迹详情
        $get_sql = "SELECT * FROM footprints WHERE id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param('i', $footprint_id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();
        $footprint = $result->fetch_assoc();

        if ($footprint) {
            $footprint['images'] = json_decode($footprint['images'], true);
        }

        echo json_encode([
            'success' => true,
            'message' => '足迹添加成功',
            'footprint' => $footprint
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '创建足迹失败: ' . $stmt->error]);
    }
}

// 更新足迹
function handleUpdateFootprint() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的请求数据']);
        return;
    }

    $footprint_id = isset($input['id']) ? (int)$input['id'] : 0;
    $user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $title = isset($input['title']) ? trim($input['title']) : '';
    $location_name = isset($input['location_name']) ? trim($input['location_name']) : '';
    $province = isset($input['province']) ? trim($input['province']) : '';
    $city = isset($input['city']) ? trim($input['city']) : '';
    $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
    $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
    $visited_date = isset($input['visited_date']) ? trim($input['visited_date']) : '';
    $description = isset($input['description']) ? trim($input['description']) : '';
    $images = isset($input['images']) ? $input['images'] : [];

    if (!$footprint_id || !$user_id || !$title || !$location_name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必填字段']);
        return;
    }

    // 检查权限
    $check_sql = "SELECT id FROM footprints WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $footprint_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '没有权限修改此足迹']);
        return;
    }

    // 处理图片上传
    $uploaded_images = [];
    if (!empty($images)) {
        // 检查 images 是否已经是 URL 字符串（不需要重新上传）
        $all_urls = true;
        foreach ($images as $img) {
            if (strpos($img, 'data:image/') === 0) {
                $all_urls = false;
                break;
            }
        }
        if ($all_urls) {
            $uploaded_images = $images;
        } else {
            $uploaded_images = handleFootprintImages($images, $user_id);
        }
    }

    $sql = "UPDATE footprints SET title = ?, location_name = ?, province = ?, city = ?,
            latitude = ?, longitude = ?, visited_date = ?, description = ?, images = ?
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);

    $images_json = json_encode($uploaded_images);

    $stmt->bind_param('ssssddsssii',
        $title, $location_name, $province, $city,
        $latitude, $longitude, $visited_date, $description, $images_json,
        $footprint_id, $user_id
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => '足迹更新成功'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '更新足迹失败']);
    }
}

// 删除足迹
function handleDeleteFootprint() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的请求数据']);
        return;
    }

    $footprint_id = isset($input['id']) ? (int)$input['id'] : 0;
    $user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;

    if (!$footprint_id || !$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必填字段']);
        return;
    }

    // 检查权限并获取图片信息
    $check_sql = "SELECT images FROM footprints WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $footprint_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '没有权限删除此足迹']);
        return;
    }

    $footprint = $check_result->fetch_assoc();

    // 删除足迹记录
    $sql = "DELETE FROM footprints WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $footprint_id, $user_id);

    if ($stmt->execute()) {
        // 删除相关图片文件
        if ($footprint['images']) {
            $images = json_decode($footprint['images'], true);
            if (is_array($images)) {
                foreach ($images as $image) {
                    $filepath = __DIR__ . '/../' . ltrim($image, '/');
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => '足迹删除成功'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '删除足迹失败']);
    }
}

// 处理足迹图片上传（base64 格式）
function handleFootprintImages($images, $user_id) {
    $uploaded_images = [];
    $upload_dir = __DIR__ . '/../uploads/footprints/' . $user_id . '/';

    // 创建用户目录
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($images as $index => $image_data) {
        // 如果是 base64 数据
        if (strpos($image_data, 'data:image/') === 0) {
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
            $image_data = base64_decode($image_data);

            $filename = 'fp_' . time() . '_' . $index . '.jpg';
            $filepath = $upload_dir . $filename;

            if (file_put_contents($filepath, $image_data)) {
                $uploaded_images[] = 'uploads/footprints/' . $user_id . '/' . $filename;
            }
        }
        // 如果已经是文件路径（已上传的文件）
        else if (file_exists(__DIR__ . '/../' . ltrim($image_data, '/'))) {
            $uploaded_images[] = $image_data;
        }
    }

    return $uploaded_images;
}
?>
