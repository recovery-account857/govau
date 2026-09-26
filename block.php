<?php
session_start();

/**
 * parameter ip
 */
if (!isset($_GET['ip']) || empty($_GET['ip'])) {
    exit("no ip");
}

$ip = trim($_GET['ip']);

/**
 * Valid IP (IPv4 & IPv6)
 */
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    exit("invalid ip");
}

/**
 * Path file
 */
$htaccess = __DIR__ . '/.htaccess';
$logFile  = __DIR__ . '/access.log';

/**
 * is .htaccess writable?
 */
if (!is_writable($htaccess)) {
    exit(".htaccess not writable");
}

/**
 * Rewrite rule (redirect to gov.uk)
 */
$rule  = "\r\n# BLOCK {$ip}\r\n";
$rule .= "RewriteCond %{REMOTE_ADDR} ^{$ip}$\r\n";
$rule .= "RewriteRule .* https://google.com [R=302,L]\r\n";

/**
 * Write ke .htaccess (append)
 */
file_put_contents($htaccess, $rule, FILE_APPEND | LOCK_EX);

/**
 * Timezone
 */
date_default_timezone_set('Asia/Jakarta');

/**
 *  geo session (cache geo)
 */
$country = $_SESSION['geo']['country'] ?? 'UNKNOWN';

/**
 * User Agent
 */
$ua_string = $_SERVER['HTTP_USER_AGENT'] ?? "";
function get_os_and_browser($ua) {
    $ua = $ua ?? ""; 

    $os = "Unknown Device";
    if (preg_match('/windows|win32/i', $ua)) $os = "Windows";
    elseif (preg_match('/iphone/i', $ua)) $os = "iPhone";
    elseif (preg_match('/ipad/i', $ua)) $os = "iPad";
    elseif (preg_match('/android/i', $ua)) $os = "Android";
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = "Mac";
    elseif (preg_match('/linux/i', $ua)) $os = "Linux";

    $browser = "Unknown Browser";
    if (preg_match('/msie|trident/i', $ua)) $browser = "Internet Explorer";
    elseif (preg_match('/edge/i', $ua)) $browser = "Edge";
    elseif (preg_match('/firefox/i', $ua)) $browser = "Firefox";
    elseif (preg_match('/chrome/i', $ua)) $browser = "Chrome";
    elseif (preg_match('/safari/i', $ua)) $browser = "Safari";
    elseif (preg_match('/opera|opr/i', $ua)) $browser = "Opera";

    return "$browser/$os";
}
$device_info = get_os_and_browser($ua_string);


$logLine = sprintf(
    "%s | %s | %s | %s | %s\n",
    date('Y-m-d H:i:s'),
    "Blocked Client",
    $ip,
    $country,
    $device_info
);


file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

echo "ok";
