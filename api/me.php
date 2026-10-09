<?php
// GET -> the logged-in user, or null for guests.

require __DIR__ . '/_bootstrap.php';

require_method('GET');
$userId = current_user_id();
if ($userId === null) {
    respond(200, ['user' => null]);
}

$query = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
$query->execute([$userId]);
$user = $query->fetch();

respond(200, ['user' => $user ? public_user($user) : null]);
