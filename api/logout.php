<?php
// POST -> ends the current session.

require __DIR__ . '/_bootstrap.php';

require_method('POST');
start_session();
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

respond(204);
