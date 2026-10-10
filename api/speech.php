<?php
// POST { episode_id } -> turns an episode's script into MP3 audio with OpenAI text-to-speech.
// The audio is saved and reused, so pressing Listen again never calls the API twice.

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_episodes.php';

require_method('POST');
$userId = require_login();
$episode = find_own_episode(read_json()['episode_id'] ?? null, $userId);

if ($episode['audio_file'] && is_file(audio_dir() . '/' . $episode['audio_file'])) {
    respond(200, ['episode' => format_episode($episode)]);
}

// The speech API accepts up to 4,096 characters per request, so long scripts are recorded in parts.
// MP3 files can be joined end to end, so the parts are simply concatenated.
$audio = '';
foreach (split_for_speech($episode['script'], 4000) as $part) {
    $audio .= openai_post('/audio/speech', [
        'model' => config('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'voice' => config('OPENAI_TTS_VOICE', 'coral'),
        'input' => $part,
        'instructions' => 'Speak like a warm, upbeat podcast host explaining a topic to a student.',
        'response_format' => 'mp3',
    ], 120);
}

// Random file names, so a file's name never reveals whose it is or how many exist.
$fileName = bin2hex(random_bytes(16)) . '.mp3';
$tempPath = audio_dir() . "/$fileName.tmp";
if (file_put_contents($tempPath, $audio) === false || !rename($tempPath, audio_dir() . "/$fileName")) {
    throw new RuntimeException("Could not save audio file $fileName");
}

db()->prepare('UPDATE episodes SET audio_file = ? WHERE id = ?')->execute([$fileName, $episode['id']]);
$episode['audio_file'] = $fileName;

respond(200, ['episode' => format_episode($episode)]);

/** Split text into parts of at most $max characters, breaking between sentences where possible. */
function split_for_speech(string $text, int $max): array
{
    $pieces = [];
    foreach (preg_split('/(?<=[.!?])\s+/u', trim($text)) as $sentence) {
        // A sentence longer than the limit is cut at word boundaries.
        while (mb_strlen($sentence) > $max) {
            $cut = mb_strrpos(mb_substr($sentence, 0, $max), ' ') ?: $max;
            $pieces[] = mb_substr($sentence, 0, $cut);
            $sentence = ltrim(mb_substr($sentence, $cut));
        }
        $pieces[] = $sentence;
    }

    $parts = [];
    $current = '';
    foreach ($pieces as $piece) {
        if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($piece) > $max) {
            $parts[] = $current;
            $current = '';
        }
        $current = $current === '' ? $piece : "$current $piece";
    }
    if ($current !== '') {
        $parts[] = $current;
    }
    return $parts;
}
