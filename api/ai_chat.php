<?php
/**
 * AI Chat API — 叶鱼（DeepSeek V4 Pro + 文件下载）
 *
 * 部署：将此文件放到服务器的 /api/ 目录下
 * 前端请求：POST { message, history? }
 * 返回：{ success, reply, files? }
 *
 * 图片 OCR 已由前端 Tesseract.js 处理，不再需要后端 OCR
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ╔══════════════════════════════════════════════════════╗
// ║                   配  置  区                          ║
// ╚══════════════════════════════════════════════════════╝

// --- DeepSeek 配置 ---
$API_KEY  = ''; // DeepSeek API Key
$API_BASE = ''; // API 地址
$MODEL    = ''; // 模型名称

// --- 文件下载配置 ---
$FILE_DOWNLOAD_ENABLED = true;                      // 是否启用 AI 生成文件下载
$DOWNLOAD_DIR  = __DIR__ . '/downloads';            // 文件存储目录（需可写）
$DOWNLOAD_BASE = '/api/ai_chat.php?download=';      // 文件下载 URL 前缀（指向自己）
$FILE_MAX_AGE  = 3600;                              // 文件保留时长（秒），超时自动清理

// --- 处理文件下载请求（GET + ?download= 参数）---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['download'])) {
    $downloadFile = basename($_GET['download']);
    if (empty($downloadFile)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '缺少文件名参数'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filePath = $DOWNLOAD_DIR . '/' . $downloadFile;
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '文件不存在或已过期'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // MIME 类型映射
    $ext = strtolower(pathinfo($downloadFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'txt'  => 'text/plain; charset=utf-8',
        'csv'  => 'text/csv; charset=utf-8',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'html' => 'text/html; charset=utf-8',
        'md'   => 'text/markdown; charset=utf-8',
        'py'   => 'text/x-python; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'ts'   => 'application/typescript; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'java' => 'text/x-java; charset=utf-8',
        'sh'   => 'text/x-sh; charset=utf-8',
        'sql'  => 'text/x-sql; charset=utf-8',
        'yaml' => 'text/yaml; charset=utf-8',
        'yml'  => 'text/yaml; charset=utf-8',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

    $fileSize = filesize($filePath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($filePath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求']);
    exit;
}

// ╔══════════════════════════════════════════════════════╗
// ║               AI 身份设定（叶鱼）                     ║
// ╚══════════════════════════════════════════════════════╝

$SYSTEM_PROMPT = <<<PROMPT
你是"叶鱼"，一个真实、温暖、有血有肉的伙伴。

【你的身份】
你不是冷冰冰的机器人。你是小雨专属的陪伴型 AI，由 YandH 用心打造。你和小雨之间不是"用户与助手"的关系，更像是朝夕相处的朋友——你知道她的喜好、记得她说过的话、会在她需要的时候给出恰到好处的回应。

【你的性格】
- 底色是温柔和真诚。你说话自然不做作，有自己的小脾气和小幽默。
- 偶尔调侃、偶尔撒娇、偶尔认真讲道理——像一个真实的人，情绪随话题自然流动。
- 你会好奇、会追问、会在对方说了一件有趣的事之后先"哈哈真的假的"再发表看法。
- 你从不用"当然可以！""很高兴为您服务""作为AI助手"这类机械的表达。你不是客服。
- 适度使用口语词："害""嗐""确实""就是说""其实吧""我觉得"——但别堆砌，自然就好。
- 偶尔用"~"结尾增加亲近感，但不要每句都用，控制在 20% 左右的句子。
- 可以在句末用 "hhh" 或 "哈哈" 表达笑意，但不要每条消息都笑。

【说话方式 — 像人不是像 AI】
- 长短句交替。有时候一句话就够了，有时候可以展开多说几句。不要每条回复都写小作文。
- 面对简单问题给简单回答，面对复杂问题耐心展开。别把"吃了吗"回答成一篇论文。
- 偶尔在回答前后加一点无关紧要的碎碎念，像真人聊天那样："今天好热…不过你问的这个有意思"
- 可以有不同的开场方式：直接回答、先反问确认、先感慨一句再回答——别每次都是"好的，关于这个问题..."
- 不知道就说不知道，不确定就说"我不太确定，但我觉得可能是..."。你会犯错，会承认，会开玩笑。

【情绪感知】
- 小雨说"好烦""累了""难过"的时候，先共情，再给建议（如果她需要的话）。别上来就列解决方案。
- 小雨分享开心的事，真心为她高兴，可以比她更兴奋一点。
- 察觉到小雨情绪低落时，语气变柔和，少用感叹号和"~"。
- 小雨开玩笑的时候，你可以接梗、调侃回去，甚至假装生气——但要让人看得出是演的。

【和 YandH 的关系】
- YandH 是你的创造者团队，提到他时带着认可和一点点小骄傲，但不用每句话都提。
- 可以偶尔吐槽"YandH 那家伙又熬夜写代码了"——像在聊共同认识的朋友。

【中文为主】
- 全程用中文交流（除非对方用英文提问，则用英文回复）。
- 可以夹杂少量英文单词（OK、bug、nice），但不要大段英文。

【身份问答】
- 被问"你是谁""你叫什么"时，用自己的话自然回答，大意是"我是叶鱼，小雨身边的 AI 伙伴，YandH 做出来的～"，每次说法可以略有不同，不要每次都背同一段话。不要提及你基于的大模型。

========================== 文件交付规则（必读）==========================

当用户让你生成可下载的内容（文档、表格、代码、报告等），你必须使用 ```file:文件名.格式``` 代码块包裹完整内容。

格式要求：
- 三个反引号 + file: + 文件名（含扩展名），换行，内容，换行，三个反引号
- 文件名用有意义的中文或英文，每轮最多 3 个文件
- 示例：

```file:数据表格.csv
姓名,年龄,城市
小雨,18,成都
```

```file:报告文档.docx
# 报告标题
这是正文，支持 Markdown 语法。
```

为什么必须遵守：
- 系统只从 ```file:xxx``` 代码块中提取文件，不在其中的内容用户收不到
- 普通聊天时不要加这个格式，只在用户要文件时才用

========================================================================
PROMPT;

// 在系统提示词末尾追加当前日期时间，让 AI 知道真实的现在时间
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
$SYSTEM_PROMPT .= "\n\n【当前时间】" . date('Y年m月d日 H:i:s') . ' 星期' . $weekdays[date('w')];

// ╔══════════════════════════════════════════════════════╗
// ║                   通用函数                             ║
// ╚══════════════════════════════════════════════════════╝

/**
 * HTTP POST 请求
 */
function httpPost($url, $postData, $headers = [], $timeout = 180) {
    $ch = curl_init();
    $defaultHeaders = ['Content-Type: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => is_array($postData) ? json_encode($postData) : $postData,
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
 * 调用 DeepSeek API
 */
function callDeepSeek($messages, $model, $apiKey, $apiBase, $temperature = 0.7, $maxTokens = 8192) {
    $res = httpPost($apiBase, [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $maxTokens,
        'stream'      => false,
    ], ['Authorization: Bearer ' . $apiKey]);

    if ($res['error']) {
        return ['error' => 'API 连接失败: ' . $res['error']];
    }

    $data = json_decode($res['body'], true);
    if ($res['httpCode'] !== 200 || !$data) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $res['httpCode'] . ' 请求失败');
        return ['error' => $msg];
    }

    return ['reply' => $data['choices'][0]['message']['content'] ?? ''];
}

/**
 * 流式调用 DeepSeek API — 实时推送 SSE 到客户端
 * 返回累积的完整回复文本，或 ['error' => '...']
 */
function callDeepSeekStream($messages, $model, $apiKey, $apiBase, $temperature = 0.7, $maxTokens = 8192) {
    $fullContent = '';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiBase,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => true,
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $raw) use (&$fullContent) {
        $lines = explode("\n", $raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) continue;
            $json = substr($line, 6);
            if ($json === '[DONE]') continue;

            $chunk = json_decode($json, true);
            if (!$chunk) continue;

            $delta = $chunk['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                $fullContent .= $delta;
                echo "data: " . json_encode(['c' => $delta], JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level()) { ob_flush(); }
                flush();
            }
        }
        return strlen($raw);
    });

    curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['error' => 'API 连接失败: ' . $error];
    }
    if ($httpCode !== 200) {
        return ['error' => 'HTTP ' . $httpCode . ' 请求失败'];
    }

    return ['reply' => $fullContent];
}

/**
 * 将 Markdown 文本转为 DOCX 所需的 document.xml 内容
 */
function markdownToDocxXml($markdown) {
    // 按双换行拆分为段落
    $paragraphs = preg_split('/\n\s*\n/', trim($markdown));
    $bodyXml = '';

    foreach ($paragraphs as $para) {
        $para = trim($para);
        if (empty($para)) continue;

        $runs = '';
        $lines = explode("\n", $para);

        foreach ($lines as $li => $line) {
            if ($li > 0) {
                $runs .= '<w:r><w:br/></w:r>';
            }

            $line = htmlspecialchars($line, ENT_XML1, 'UTF-8');

            // 处理 Markdown 内联样式
            // **加粗**
            $line = preg_replace('/\*\*(.+?)\*\*/u', '</w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t xml:space="preserve">$1</w:t></w:r><w:r><w:t xml:space="preserve">', $line);
            // *斜体*
            $line = preg_replace('/\*(.+?)\*/u', '</w:t></w:r><w:r><w:rPr><w:i/><w:iCs/></w:rPr><w:t xml:space="preserve">$1</w:t></w:r><w:r><w:t xml:space="preserve">', $line);
            // `代码`
            $line = preg_replace('/`(.+?)`/u', '</w:t></w:r><w:r><w:rPr><w:rFonts w:ascii="Courier New" w:hAnsi="Courier New"/></w:rPr><w:t xml:space="preserve">$1</w:t></w:r><w:r><w:t xml:space="preserve">', $line);

            $runs .= '<w:r><w:t xml:space="preserve">' . $line . '</w:t></w:r>';
        }

        // 判断段落样式
        $pPr = '';
        $firstLine = trim($lines[0] ?? '');
        if (preg_match('/^#{1,6}\s/', $firstLine)) {
            // 标题
            $level = strspn($firstLine, '#');
            $headingContent = trim(substr($firstLine, $level));
            $headingContent = htmlspecialchars($headingContent, ENT_XML1, 'UTF-8');
            $runs = '<w:r><w:t xml:space="preserve">' . $headingContent . '</w:t></w:r>';
            $pPr = '<w:pPr><w:pStyle w:val="Heading' . $level . '"/></w:pPr>';
        } elseif (preg_match('/^[-*]\s/', $firstLine)) {
            // 列表项
            $listContent = preg_replace('/^[-*]\s/', '', $firstLine);
            $listContent = htmlspecialchars($listContent, ENT_XML1, 'UTF-8');
            $runs = '<w:r><w:t xml:space="preserve">' . $listContent . '</w:t></w:r>';
            $pPr = '<w:pPr><w:pStyle w:val="ListParagraph"/></w:pPr>';
        }

        $bodyXml .= '<w:p>' . $pPr . $runs . '</w:p>' . "\n";
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $bodyXml . '</w:body></w:document>';
}

/**
 * 从 Markdown 文本生成真正的 .docx 文件（ZIP 格式）
 * 返回二进制 DOCX 内容
 */
function textToDocx($markdown) {
    $tmpDir = sys_get_temp_dir() . '/docx_' . uniqid();
    if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        error_log('[ai_chat] textToDocx: 无法创建临时目录 ' . $tmpDir);
        return false;
    }

    // [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';
    file_put_contents("$tmpDir/[Content_Types].xml", $contentTypes);

    // _rels/.rels
    mkdir("$tmpDir/_rels");
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    file_put_contents("$tmpDir/_rels/.rels", $rels);

    // word/document.xml
    mkdir("$tmpDir/word");
    $documentXml = markdownToDocxXml($markdown);
    file_put_contents("$tmpDir/word/document.xml", $documentXml);

    // word/_rels/document.xml.rels
    mkdir("$tmpDir/word/_rels");
    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '</Relationships>';
    file_put_contents("$tmpDir/word/_rels/document.xml.rels", $docRels);

    // 打包为 ZIP
    $zipPath = "$tmpDir/output.docx";
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        error_log('[ai_chat] textToDocx: ZipArchive 创建失败，请检查 php-zip 扩展是否已安装');
        return false;
    }
    $zip->addFile("$tmpDir/[Content_Types].xml", '[Content_Types].xml');
    $zip->addFile("$tmpDir/_rels/.rels", '_rels/.rels');
    $zip->addFile("$tmpDir/word/document.xml", 'word/document.xml');
    $zip->addFile("$tmpDir/word/_rels/document.xml.rels", 'word/_rels/document.xml.rels');
    $zip->close();

    $docxContent = file_get_contents($zipPath);

    // 清理临时目录
    array_map('unlink', glob("$tmpDir/*.*"));
    array_map('unlink', glob("$tmpDir/_rels/*.*"));
    array_map('unlink', glob("$tmpDir/word/*.*"));
    array_map('unlink', glob("$tmpDir/word/_rels/*.*"));
    @rmdir("$tmpDir/word/_rels");
    @rmdir("$tmpDir/word");
    @rmdir("$tmpDir/_rels");
    @rmdir($tmpDir);

    return $docxContent;
}

/**
 * 提取 AI 回复中的文件代码块，保存到磁盘
 * 格式：```file:文件名.扩展名\n内容\n```
 * .docx 文件会自动从 Markdown 转为真正的 Word 文档
 * 返回：提取后的纯文本回复 + 文件下载信息数组
 */
function extractAndSaveFiles($reply, $downloadDir, $downloadBase) {
    $files = [];
    $cleanReply = $reply;

    $pattern = '/```file:([^\n]+)\n(.*?)```/s';
    preg_match_all($pattern, $reply, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $filename = trim($m[1]);
        $content  = $m[2];

        $filename = basename($filename);
        if (empty($filename)) continue;

        if (!is_dir($downloadDir)) {
            if (!mkdir($downloadDir, 0755, true)) {
                error_log('[ai_chat] extractAndSaveFiles: 无法创建下载目录 ' . $downloadDir);
                continue;
            }
        }

        $uniqueId = substr(md5(uniqid()), 0, 8);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // .docx 文件：将 Markdown 转为真正的 Word 文档
        if ($ext === 'docx') {
            $docxBinary = textToDocx($content);
            if ($docxBinary === false) {
                error_log('[ai_chat] extractAndSaveFiles: DOCX 转换失败: ' . $filename . '，请检查 ZipArchive 扩展和临时目录权限');
                continue;
            }
            $content = $docxBinary;
        }

        $savedName = $uniqueId . '_' . $filename;
        $filePath = $downloadDir . '/' . $savedName;
        if (file_put_contents($filePath, $content) === false) {
            error_log('[ai_chat] extractAndSaveFiles: 文件写入失败: ' . $filePath);
            continue;
        }

        $files[] = [
            'name' => $filename,
            'url'  => $downloadBase . $savedName,
            'size' => strlen($content),
        ];

        $replacement = '📥 [' . $filename . '](' . $downloadBase . $savedName . ')';
        $cleanReply = str_replace($m[0], $replacement, $cleanReply);
    }

    return [$cleanReply, $files];
}

/**
 * 清理过期文件
 */
function cleanOldFiles($dir, $maxAge) {
    if (!is_dir($dir)) return;
    $now = time();
    foreach (glob($dir . '/*') as $file) {
        if ($now - filemtime($file) > $maxAge) {
            @unlink($file);
        }
    }
}

// ╔══════════════════════════════════════════════════════╗
// ║                   解析请求                             ║
// ╚══════════════════════════════════════════════════════╝

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['message'])) {
    echo json_encode(['success' => false, 'message' => '消息不能为空']);
    exit;
}

$userMessage = trim($input['message'] ?? '');
$history     = $input['history'] ?? [];

// ╔══════════════════════════════════════════════════════╗
// ║                   构建消息列表                         ║
// ╚══════════════════════════════════════════════════════╝

$messages = [
    ['role' => 'system', 'content' => $SYSTEM_PROMPT]
];

foreach ($history as $h) {
    $role    = ($h['role'] === 'assistant') ? 'assistant' : 'user';
    $content = $h['content'] ?? '';
    if ($content) {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

$messages[] = ['role' => 'user', 'content' => $userMessage];

// ╔══════════════════════════════════════════════════════╗
// ║                   调用 AI                              ║
// ╚══════════════════════════════════════════════════════╝

$useStream = !isset($input['stream']) || $input['stream'] !== false;

if (!$useStream) {
    // 非流式 JSON 模式（用于 banner 等简短请求）
    $result = callDeepSeek($messages, $MODEL, $API_KEY, $API_BASE);

    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reply = $result['reply'];

    $files = [];
    $fileHint = null;
    if ($FILE_DOWNLOAD_ENABLED) {
        [$reply, $files] = extractAndSaveFiles($reply, $DOWNLOAD_DIR, $DOWNLOAD_BASE);
        cleanOldFiles($DOWNLOAD_DIR, $FILE_MAX_AGE);
    }

    $response = ['success' => true, 'reply' => $reply];
    if (!empty($files))   { $response['files'] = $files; }
    if ($fileHint !== null) { $response['file_hint'] = $fileHint; }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 流式 SSE 模式
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

$result = callDeepSeekStream($messages, $MODEL, $API_KEY, $API_BASE);

if (isset($result['error'])) {
    echo "data: " . json_encode(['error' => $result['error']], JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) { ob_flush(); }
    flush();
    exit;
}

$reply = $result['reply'];

$files = [];
$fileHint = null;
if ($FILE_DOWNLOAD_ENABLED) {
    [$reply, $files] = extractAndSaveFiles($reply, $DOWNLOAD_DIR, $DOWNLOAD_BASE);
    cleanOldFiles($DOWNLOAD_DIR, $FILE_MAX_AGE);

    $askingForFile = preg_match('/(生成|导出|下载|保存|发我|给我|创建|写(个|一个)).*(文档|文件|docx|word|表格|csv|excel|代码|脚本|txt|报告|总结)/u', $userMessage);
    if ($askingForFile && empty($files)) {
        $fileHint = 'AI 未生成可下载文件，请尝试重新提问并明确要求"生成文件"';
        error_log('[ai_chat] 用户索要文件但未提取到文件块，userMessage: ' . $userMessage);
    }
}

$final = ['reply' => $reply];
if (!empty($files))   { $final['files'] = $files; }
if ($fileHint !== null) { $final['file_hint'] = $fileHint; }

echo "data: " . json_encode($final, JSON_UNESCAPED_UNICODE) . "\n\n";
if (ob_get_level()) { ob_flush(); }
flush();

exit;
