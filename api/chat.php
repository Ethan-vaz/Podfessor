<?php
// POST { prompt, course_id? } -> a podcast script from the OpenAI Responses API.
// The API key stays on the server. Login is required so the key can't be used by anyone on the internet.

require __DIR__ . '/_bootstrap.php';

require_method('POST');
$userId = require_login();

$apiKey = config('OPENAI_API_KEY');
if (!$apiKey) {
    respond(500, ['error' => 'The server is missing OPENAI_API_KEY.']);
}

$data = read_json();
$prompt = string_field($data, 'prompt');

if ($prompt === '') {
    respond(400, ['error' => 'Please enter a question.']);
}
if (mb_strlen($prompt) > 500) {
    respond(400, ['error' => 'Please keep your question under 500 characters.']);
}

$instructions = 'You are Podfessor. Write a short, friendly podcast script that teaches the student about the topic they ask about.';

// If a course was chosen, give the model its topics and the student's notes as background.
$courseId = filter_var($data['course_id'] ?? null, FILTER_VALIDATE_INT);
if ($courseId) {
    $query = db()->prepare(
        'SELECT c.code, c.name, c.notes, GROUP_CONCAT(t.name ORDER BY t.position SEPARATOR \', \') AS topics
         FROM courses c LEFT JOIN course_topics t ON t.course_id = c.id
         WHERE c.id = ? AND (c.user_id IS NULL OR c.user_id = ?)
         GROUP BY c.id'
    );
    $query->execute([$courseId, $userId]);
    $course = $query->fetch();
    if (!$course) {
        respond(404, ['error' => 'Course not found.']);
    }
    $instructions .= "\n\nThe question is about the course {$course['code']}: {$course['name']}."
        . "\nCourse topics: " . ($course['topics'] ?: 'not listed')
        . "\nStudent's notes: " . ($course['notes'] ?: 'none');
}

$ch = curl_init(config('OPENAI_BASE_URL', 'https://api.openai.com/v1') . '/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => config('OPENAI_MODEL', 'gpt-5'),
        'instructions' => $instructions,
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
