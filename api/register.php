<?php
// POST { name, email, password } -> creates an account and logs the user in.

require __DIR__ . '/_bootstrap.php';

require_method('POST');
$data = read_json();

$name = string_field($data, 'name');
$email = strtolower(string_field($data, 'email'));
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

$errors = [];
if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = 'Enter a name up to 100 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $errors['email'] = 'Enter a valid email address.';
}
// bcrypt only uses the first 72 bytes of a password, so longer ones are rejected.
if (strlen($password) < 8 || strlen($password) > 72) {
    $errors['password'] = 'Use a password between 8 and 72 characters.';
}
if ($errors) {
    respond(422, ['error' => 'Please fix the highlighted fields.', 'fields' => $errors]);
}

$exists = db()->prepare('SELECT 1 FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetchColumn()) {
    respond(409, ['error' => 'An account with that email already exists.', 'fields' => ['email' => 'This email is already registered.']]);
}

$insert = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
$insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
$userId = (int) db()->lastInsertId();

log_in($userId);
respond(201, ['user' => ['id' => $userId, 'name' => $name, 'email' => $email]]);
