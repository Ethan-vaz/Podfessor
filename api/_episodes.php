<?php
// Shared code for episodes: loading them with an ownership check, formatting, and where audio files live.

/** Folder for generated MP3s. It sits outside the public pages; audio is served through api/audio.php. */
function audio_dir(): string
{
    $dir = config('AUDIO_DIR', dirname(__DIR__) . '/storage/audio');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Could not create the audio folder $dir");
    }
    return $dir;
}

/** Load one of the user's episodes, or respond 404 so other users' episodes look like they don't exist. */
function find_own_episode(mixed $id, int $userId): array
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        respond(400, ['error' => 'Choose an episode.']);
    }

    $query = db()->prepare(
        'SELECT e.*, c.code AS course_code
         FROM episodes e LEFT JOIN courses c ON c.id = e.course_id
         WHERE e.id = ? AND e.user_id = ? AND e.deleted_at IS NULL'
    );
    $query->execute([$id, $userId]);
    $episode = $query->fetch();
    if (!$episode) {
        respond(404, ['error' => 'Episode not found.']);
    }
    return $episode;
}

function format_episode(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'prompt' => $row['prompt'],
        'script' => $row['script'],
        'course_id' => $row['course_id'] === null ? null : (int) $row['course_id'],
        'course_code' => $row['course_code'] ?? null,
        'audio_url' => $row['audio_file'] ? 'api/audio.php?episode_id=' . $row['id'] : null,
        'created_at' => $row['created_at'],
    ];
}
