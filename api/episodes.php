<?php
// GET                  -> your 20 most recent episodes, newest first
// DELETE episodes.php?id=1 -> hide one of your episodes and delete its audio file

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_episodes.php';

$method = require_method('GET', 'DELETE');
$userId = require_login();

if ($method === 'GET') {
    $query = db()->prepare(
        'SELECT e.*, c.code AS course_code
         FROM episodes e LEFT JOIN courses c ON c.id = e.course_id
         WHERE e.user_id = ? AND e.deleted_at IS NULL
         ORDER BY e.created_at DESC, e.id DESC
         LIMIT 20'
    );
    $query->execute([$userId]);
    respond(200, ['episodes' => array_map('format_episode', $query->fetchAll())]);
}

$episode = find_own_episode($_GET['id'] ?? null, $userId);
// Kept in the table (hidden) so deleting episodes can't be used to get around the daily limit.
db()->prepare('UPDATE episodes SET deleted_at = NOW(), audio_file = NULL WHERE id = ?')->execute([$episode['id']]);
if ($episode['audio_file']) {
    @unlink(audio_dir() . '/' . $episode['audio_file']);
}
respond(204);
