<?php
header("Content-Type: application/json");

$userAgent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "User Agent not available";

function detectDevice($userAgent) {
    if (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|Windows Phone/i', $userAgent)) {
        return "Mobile";
    } elseif (preg_match('/Tablet|iPad/i', $userAgent)) {
        return "Tablet";
    } else {
        return "Desktop";
    }
}

function detectBrowser($userAgent) {
    if (preg_match('/MSIE/i', $userAgent)) {
        return 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        return 'Firefox';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        return 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        return 'Safari';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        return 'Opera';
    } else {
        return 'Unknown';
    }
}

$response = array(
    "device" => detectDevice($userAgent),
    "browser" => detectBrowser($userAgent),
    "user_agent" => $userAgent
);

echo json_encode($response);
?>