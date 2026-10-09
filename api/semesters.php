<?php
// GET -> all semesters, oldest first.

require __DIR__ . '/_bootstrap.php';

require_method('GET');
$semesters = db()->query('SELECT id, name FROM semesters ORDER BY start_date')->fetchAll();

respond(200, ['semesters' => array_map(
    fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']],
    $semesters
)]);
