<?php
// Proxies chat prompts to the OpenAI Responses API so the API key stays on the server.

header('Content-Type: application/json');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST.']);
}

$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    respond(500, ['error' => 'The server is missing OPENAI_API_KEY.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = is_array($input) && is_string($input['prompt'] ?? null) ? trim($input['prompt']) : '';

if ($prompt === '') {
    respond(400, ['error' => 'Please enter a question.']);
}
if (mb_strlen($prompt) > 500) {
    respond(400, ['error' => 'Please keep your question under 500 characters.']);
}

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-5',
        'instructions' => 'You are Podfessor. Write a short, friendly podcast script that teaches the student about the topic they ask about.',
        'input' => $prompt,
    ]),
]);

$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($raw === false || $status !== 200) {
    error_log("OpenAI request failed with status $status: $raw");
    respond(502, ['error' => 'The AI service is unavailable right now. Please try again.']);
}

// Collect the text parts of the model's message output.
$text = '';
foreach (json_decode($raw, true)['output'] ?? [] as $item) {
    if (($item['type'] ?? '') !== 'message') {
        continue;
    }
    foreach ($item['content'] ?? [] as $part) {
        if (($part['type'] ?? '') === 'output_text') {
            $text .= $part['text'];
        }
    }
}

respond(200, ['text' => $text]);
