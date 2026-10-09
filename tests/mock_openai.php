<?php
// A stand-in for the OpenAI Responses API used by the tests.
// It saves the last request so tests can check what was sent, and returns a fixed reply.

file_put_contents(getenv('MOCK_LOG'), file_get_contents('php://input'));

header('Content-Type: application/json');
echo json_encode([
    'output' => [
        ['type' => 'reasoning', 'summary' => []],
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Mock podcast script.']]],
    ],
]);
