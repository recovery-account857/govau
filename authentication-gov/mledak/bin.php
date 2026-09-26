<?php

if (!isset($_GET['bin'])) {
    exit('Parameter BIN tidak ditemukan.');
}

// Hapus spasi & karakter selain angka
$bin = preg_replace('/\D/', '', $_GET['bin']);

// Pastikan minimal 6 digit
if (strlen($bin) < 6) {
    exit('Parameter BIN tidak valid.');
}

$cardBIN = substr($bin, 0, 6);

$url = "https://data.handyapi.com/bin/" . $cardBIN;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);

if ($response === false) {
    exit('cURL Error: ' . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    exit("API gagal. HTTP Code: $httpCode");
}

$data = json_decode($response, true);

if ($data === null) {
    exit('JSON tidak valid.');
}

header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
