<?php
// POST { email, password } -> starts a session for that user.

require __DIR__ . '/_bootstrap.php';

require_method('POST');
$data = read_json();

$email = strtolower(string_field($data, 'email'));
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

$query = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
$query->execute([$email]);
$user = $query->fetch();

// Use one message for a wrong email or a wrong password.
if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(401, ['error' => 'Incorrect email or password.']);
}

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
}

log_in((int) $user['id']);
respond(200, ['user' => public_user($user)]);
