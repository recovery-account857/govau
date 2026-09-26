<?php
$ppppaddress = $_SERVER['REMOTE_ADDR'];
$log_file = 'log_ip.txt';

$file = fopen($log_file, 'a');

fwrite($file, date('Y-m-d H:i:s') . ' - ' . $ppppaddress . PHP_EOL);

fclose($file);

$ip = $_SERVER['REMOTE_ADDR'];
$blacklistFile = 'blacklist.txt';

// Daftar domain/link redirect
$redirectLinks = [
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

// Fungsi redirect ke link acak
function redirectToRandomLink($links) {
    $randomLink = $links[array_rand($links)];
    header("Location: $randomLink");
    exit;
}

$blacklistedIps = file_exists($blacklistFile) 
    ? file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) 
    : [];

// Redirect langsung jika IP ada di blacklist
if (in_array($ip, $blacklistedIps)) {
    redirectToRandomLink($redirectLinks);
}

    $ip = $_SERVER['REMOTE_ADDR'];

    $apiUrl = 'http://ip-api.com/json/'. urlencode($ip);

    $ch = curl_init($apiUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error saat melakukan request: ' . curl_error($ch);
    } else {
       
        print_r($response);
    }

    curl_close($ch);

?>
