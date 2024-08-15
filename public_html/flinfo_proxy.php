<?php

$id = $_GET['id'];
$raw = $_GET['raw'];
$repo = $_GET['repo'];
$categories = $_GET['categories'];
$format = $_GET['format'];

$url = 'https://wikipedia.ramselehof.de/flinfo.php?' . http_build_query([
        'id' => $id,
        'raw' => $raw,
        'repo' => $repo,
        'categories' => $categories,
        'format' => $format
    ]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$output = curl_exec($ch);
curl_close($ch);

echo $output;
