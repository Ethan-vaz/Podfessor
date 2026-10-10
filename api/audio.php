<?php
// GET audio.php?episode_id=1 -> streams the episode's MP3 to its owner.
// Supports HTTP Range requests so the audio player can seek, which Safari requires.

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_episodes.php';

require_method('GET');
$userId = require_login();
$episode = find_own_episode($_GET['episode_id'] ?? null, $userId);

$path = $episode['audio_file'] ? audio_dir() . '/' . $episode['audio_file'] : null;
if (!$path || !is_file($path)) {
    respond(404, ['error' => 'This episode has no audio yet.']);
}

$size = filesize($path);
$start = 0;
$end = $size - 1;

if (preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'] ?? '', $range)) {
    if ($range[1] === '' && $range[2] !== '') {
        // "bytes=-500" means the last 500 bytes
        $start = max(0, $size - (int) $range[2]);
    } else {
        $start = (int) $range[1];
        $end = $range[2] === '' ? $end : min((int) $range[2], $end);
    }
    if ($start > $end || $start >= $size) {
        header("Content-Range: bytes */$size");
        respond(416, ['error' => 'Requested range not satisfiable.']);
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

header('Content-Type: audio/mpeg');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: private, max-age=86400');

$file = fopen($path, 'rb');
fseek($file, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($file)) {
    $chunk = fread($file, min(8192, $remaining));
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($file);
