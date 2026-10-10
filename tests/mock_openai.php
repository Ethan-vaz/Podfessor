<?php
// A stand-in for the OpenAI API used by the tests. It handles the two endpoints Podfessor calls,
// and appends every request to MOCK_LOG (one JSON line each) so tests can check what was sent.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input'), true);
file_put_contents(getenv('MOCK_LOG'), json_encode(['path' => $path, 'body' => $body]) . "\n", FILE_APPEND);

if ($path === '/audio/speech') {
    header('Content-Type: audio/mpeg');
    if (getenv('MOCK_SILENT_MP3')) {
        // A real, playable MP3 for browser testing: 100 silent MPEG-1 Layer III frames (about 2.6 seconds).
        echo str_repeat("\xFF\xFB\x90\x00" . str_repeat("\0", 413), 100);
        return;
    }
    // Fake MP3 bytes: a marker plus the length of the text, so tests can tell parts apart.
    echo 'MP3[' . mb_strlen($body['input']) . ']';
    return;
}

if ($path === '/responses') {
    // A question containing LONG gets a script long enough to need several speech requests.
    $text = str_contains($body['input'], 'LONG')
        ? trim(str_repeat('This sentence teaches one small idea. ', 250))
        : 'Mock podcast script.';

    header('Content-Type: application/json');
    echo json_encode([
        'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
        ],
    ]);
    return;
}

http_response_code(404);
