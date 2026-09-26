<?php
/*
     _              _           _                _   
    | |            | |         | |              | |  
 ___| |_ ___  _ __ | |__   ___ | |_   _ __   ___| |_ 
/ __| __/ _ \| '_ \| '_ \ / _ \| __| | '_ \ / _ \ __|
\__ \ || (_) | |_) | |_) | (_) | |_ _| | | |  __/ |_ 
|___/\__\___/| .__/|_.__/ \___/ \__(_)_| |_|\___|\__|
             | |                                     
             |_|                                     
                      [Example Code Blocker]

Guide   : https://docs.stopbot.net/service-guides/stopbot/blocker
Website : stopbot.net
contact : t.me @stopbotnet
*/

/* START CONFIGURATION */

#Put your Apikey here.
$Apikey = "e4efad9037309d8d74af0c9e498745c7";

# 0. Turn off
# 1. Turn on
$BotControl = 1;

# RedirectURL
# Leave it blank for http_code 404 response
$RedirectURL = uriRand();

/* END CONFIGURATION */
function uriRand()
{
    $uri = [
        'https://www.google.com',
        'https://bbc.com',
        'https://cnn.com',
        'https://nytimes.com',
        'https://reuters.com',
        'https://aljazeera.com',
        'https://theguardian.com',
        'https://wsj.com',
        'https://forbes.com',
        'https://bloomberg.com',
        'https://npr.org',
        'https://abcnews.go.com',
        'https://cnbc.com',
        'https://marketwatch.com',
        'https://time.com',
        'https://usatoday.com',
        'https://msnbc.com',
        'https://huffpost.com',
        'https://newsweek.com',
        'https://thehill.com',
        'https://latimes.com',
        'https://news.ycombinator.com',
        'https://vox.com',
        'https://dailybeast.com',
        'https://businessinsider.com',
        'https://investing.com',
        'https://theverge.com',
        'https://buzzfeednews.com',
        'https://example.com',
        'https://openai.com',
        'https://github.com',
        'https://stackoverflow.com',
        'https://reddit.com',
        'https://twitter.com',
        'https://linkedin.com',
        'https://medium.com',
        'https://quora.com',
        'https://producthunt.com',
        'https://techcrunch.com',
        'https://entrepreneur.com',
        'https://mashable.com',
        'https://buzzfeed.com',
        'https://lifehacker.com',
        'https://cnet.com',
        'https://wired.com',
        'https://theatlantic.com',
        'https://fortune.com',
        'https://wsj.com',
        'https://cnbc.com',
        'https://npr.org'
    ];

    shuffle($uri);
    return $uri[0];
}

if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
    $_SERVER['REMOTE_ADDR']    = $_SERVER["HTTP_CF_CONNECTING_IP"];
    $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
}
$client  = @$_SERVER['HTTP_CLIENT_IP'];
$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
$remote  = $_SERVER['REMOTE_ADDR'];

switch (true) {
    case (filter_var($client, FILTER_VALIDATE_IP)):
        $Ip = $client;
        break;
    case (filter_var($forward, FILTER_VALIDATE_IP)):
        $Ip = $forward;
        break;
    default:
        $Ip = $remote;
        break;
}

function blockIPInHTAccess($ipAddress)
{
    $htaccessPath = './.htaccess';
    $blockRule    = "Deny from $ipAddress\n";

    // Append the block rule to the .htaccess file
    file_put_contents($htaccessPath, $blockRule, FILE_APPEND | LOCK_EX);
}
function logBotIP($ipAddress)
{
    $logFile    = __DIR__ . '/log_bot.txt'; // Path to the log file
    $logMessage = date('Y-m-d H:i:s') . " - Bot detected: $ipAddress\n";

    // Append the log message to log_bot.txt
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://stopbot.net/api/blocker?apikey=" . $Apikey . "&ip=" . $Ip . "&ua=" . urlencode($_SERVER['HTTP_USER_AGENT']) . "&url=" . urlencode($_SERVER['REQUEST_URI']) . "&" . rand(1, 1000000));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
$response = curl_exec($ch);
switch (true) {
    case ! $response:
        file_put_contents("stopbot.txt", "[" . date("D/m/y H:i:s") . "] -> Request Timeout\r\n", FILE_APPEND);
        break;
    default:
        $resp = json_decode($response, true);
        switch ($resp['status']) {
            case "error":
                file_put_contents("stopbot.txt", "[" . date("D/m/y H:i:s") . "] -> " . $resp['message'] . "\r\n", FILE_APPEND);
                break;
            case "success":
                switch (true) {
                    case $resp['IPStatus']['BlockAccess'] == 1 && ! empty($RedirectURL):
                        // Block IP in .htaccess
                        blockIPInHTAccess($Ip);

                        // Log the bot detection
                        logBotIP($Ip);
                        header("Location: " . $RedirectURL);
                        die();
                        break;
                    case $resp['IPStatus']['BlockAccess'] == 1 && empty($RedirectURL):
                        // Block IP in .htaccess
                        blockIPInHTAccess($Ip);

                        // Log the bot detection
                        logBotIP($Ip);
                        http_response_code(404);
                        die();
                        break;
                }
                break;
            default:
                file_put_contents("stopbot.txt", "[" . date("D/m/y H:i:s") . "] -> Unknown error\r\n", FILE_APPEND);
                break;

        }
        break;
}
