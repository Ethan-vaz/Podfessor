<?php
// POST { prompt, course_id? } -> writes a podcast script with the OpenAI Responses API and saves it as an episode.
// The API key stays on the server. Login is required so the key can't be used by anyone on the internet.

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_episodes.php';

require_method('POST');
$userId = require_login();

$data = read_json();
$prompt = string_field($data, 'prompt');

if ($prompt === '') {
    respond(400, ['error' => 'Please enter a question.']);
}
if (mb_strlen($prompt) > 500) {
    respond(400, ['error' => 'Please keep your question under 500 characters.']);
}

// Cap how many scripts one account can generate per day, to keep API costs predictable.
$dailyLimit = (int) config('DAILY_EPISODE_LIMIT', '30');
$recent = db()->prepare('SELECT COUNT(*) FROM episodes WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY');
$recent->execute([$userId]);
if ((int) $recent->fetchColumn() >= $dailyLimit) {
    respond(429, ['error' => "You've reached today's limit of $dailyLimit episodes. Please try again tomorrow."]);
}

$instructions = 'You are Podfessor, a friendly podcast host who teaches university students. '
    . 'Write a podcast script, under 400 words, that teaches the topic the student asks about. '
    . 'It will be read aloud by a single narrator, so write plain spoken sentences only: '
    . 'no headings, bullet points, markdown, stage directions or speaker names.';

// If a course was chosen, give the model its topics and the student's notes as background.
$courseId = filter_var($data['course_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
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

$raw = openai_post('/responses', [
    'model' => config('OPENAI_MODEL', 'gpt-5'),
    'instructions' => $instructions,
    'input' => $prompt,
]);

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
$text = trim($text);

if ($text === '') {
    error_log('OpenAI returned no text: ' . substr($raw, 0, 1000));
    respond(502, ['error' => 'The AI service returned an empty answer. Please try again.']);
}

$insert = db()->prepare('INSERT INTO episodes (user_id, course_id, prompt, script) VALUES (?, ?, ?, ?)');
$insert->execute([$userId, $courseId, $prompt, $text]);

$episode = format_episode(find_own_episode((int) db()->lastInsertId(), $userId));
respond(200, ['text' => $text, 'episode' => $episode]);
