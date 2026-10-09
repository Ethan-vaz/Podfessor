<?php
// Integration tests for the REST API. Run them with tests/run.sh, which sets up a
// fresh test database, a fake OpenAI server and the PHP server, then runs this file.

declare(strict_types=1);

$baseUrl = rtrim(getenv('API_URL') ?: 'http://127.0.0.1:8090/api', '/');
$passed = 0;
$failed = 0;

/** A browser-like client that keeps its own session cookie. */
final class Client
{
    private string $cookieFile;

    public function __construct(private string $baseUrl)
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'podfessor-cookies');
    }

    public function __destruct()
    {
        @unlink($this->cookieFile);
    }

    /** @return array{0:int,1:mixed} status code and decoded JSON body */
    public function request(string $method, string $path, mixed $body = null, ?string $contentType = 'application/json'): array
    {
        $ch = curl_init($this->baseUrl . '/' . $path);
        $headers = $contentType ? ["Content-Type: $contentType"] : [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
        $raw = curl_exec($ch);
        return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode((string) $raw, true)];
    }
}

function check(string $name, bool $ok, mixed $detail = null): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok    $name\n";
    } else {
        $failed++;
        echo "  FAIL  $name\n";
        if ($detail !== null) {
            echo '        got: ' . json_encode($detail) . "\n";
        }
    }
}

$guest = new Client($baseUrl);
$alice = new Client($baseUrl);
$bob = new Client($baseUrl);

echo "Semesters and sample courses\n";
[$status, $body] = $guest->request('GET', 'semesters.php');
check('lists semesters', $status === 200 && count($body['semesters']) === 4, $body);

[$status, $body] = $guest->request('GET', 'courses.php');
check('guest sees the 4 sample courses', $status === 200 && count($body['courses']) === 4, $body);
check('courses include topics in order', ($body['courses'][0]['topics'] ?? null) === ['HTML', 'CSS', 'JavaScript', 'Forms', 'DOM'], $body['courses'][0] ?? null);
check('sample courses are not owned', !array_filter($body['courses'], fn ($c) => $c['owned']));

[$status, $body] = $guest->request('GET', 'courses.php?q=deriv');
check('search matches a topic', $status === 200 && array_column($body['courses'], 'code') === ['MTH330'], $body);

[$status, $body] = $guest->request('GET', 'courses.php?q=bti');
check('search matches course codes', count($body['courses']) === 2, $body);

[$status, $body] = $guest->request('GET', 'courses.php?q=%25');
check('search treats % as a literal character', count($body['courses']) === 0, $body);

[$status, $body] = $guest->request('GET', 'courses.php?semester_id=2');
check('semester filter', $status === 200 && count($body['courses']) === 0, $body);

[$status] = $guest->request('GET', 'courses.php?id=999');
check('missing course returns 404', $status === 404);

[$status] = $guest->request('GET', 'courses.php?id=abc');
check('bad course id returns 400', $status === 400);

[$status] = $guest->request('PATCH', 'courses.php');
check('unsupported method returns 405', $status === 405);

echo "Accounts\n";
[$status, $body] = $guest->request('GET', 'me.php');
check('guest is not logged in', $status === 200 && $body['user'] === null, $body);

[$status, $body] = $alice->request('POST', 'register.php', ['name' => '', 'email' => 'nope', 'password' => 'short']);
check('register validates every field', $status === 422 && array_keys($body['fields']) === ['name', 'email', 'password'], $body);

[$status, $body] = $alice->request('POST', 'register.php', ['name' => 'Alice', 'email' => 'Alice@Example.com', 'password' => 'correct horse']);
check('register creates the account', $status === 201 && $body['user']['email'] === 'alice@example.com', $body);

[$status, $body] = $alice->request('GET', 'me.php');
check('register logs the user in', $body['user']['name'] === 'Alice', $body);

[$status] = $bob->request('POST', 'register.php', ['name' => 'Copy', 'email' => 'alice@example.com', 'password' => 'another one']);
check('duplicate email returns 409', $status === 409);

[$status] = $bob->request('POST', 'register.php', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'bob password']);
check('second user registers', $status === 201);

[$status] = $alice->request('POST', 'logout.php');
[, $body] = $alice->request('GET', 'me.php');
check('logout ends the session', $status === 204 && $body['user'] === null, $body);

[$status] = $alice->request('POST', 'login.php', ['email' => 'alice@example.com', 'password' => 'wrong password']);
check('wrong password returns 401', $status === 401);

[$status] = $alice->request('POST', 'login.php', ['email' => 'alice@example.com', 'password' => 'correct horse'], 'text/plain');
check('non-JSON body returns 415', $status === 415);

[$status, $body] = $alice->request('POST', 'login.php', ['email' => 'ALICE@example.com', 'password' => 'correct horse']);
check('login works with any email case', $status === 200 && $body['user']['name'] === 'Alice', $body);

echo "Creating and changing courses\n";
$newCourse = ['code' => 'dbs311', 'name' => 'Database Systems', 'semester_id' => 4, 'topics' => 'SQL, Joins, , Indexes, SQL', 'notes' => 'Normalization and indexes.'];

[$status] = $guest->request('POST', 'courses.php', $newCourse);
check('guest cannot create a course', $status === 401);

[$status, $body] = $alice->request('POST', 'courses.php', ['code' => '', 'name' => '', 'semester_id' => 99, 'image_url' => 'javascript:alert(1)']);
check('course validation reports each field', $status === 422 && array_keys($body['fields']) === ['code', 'name', 'semester_id', 'image_url'], $body);

[$status, $body] = $alice->request('POST', 'courses.php', $newCourse);
$aliceCourseId = $body['course']['id'] ?? 0;
check('alice creates a course', $status === 201 && $body['course']['owned'] === true, $body);
check('code is uppercased', ($body['course']['code'] ?? '') === 'DBS311', $body);
check('topics are split, trimmed and de-duplicated', ($body['course']['topics'] ?? []) === ['SQL', 'Joins', 'Indexes'], $body);

[, $body] = $alice->request('GET', 'courses.php');
check('alice sees samples plus her course', count($body['courses']) === 5, $body);

[, $body] = $bob->request('GET', 'courses.php');
check('bob does not see alice\'s course', count($body['courses']) === 4, $body);

[$status] = $bob->request('GET', "courses.php?id=$aliceCourseId");
check('bob gets 404 for alice\'s course', $status === 404);

[$status] = $bob->request('PUT', "courses.php?id=$aliceCourseId", $newCourse);
check('bob cannot update alice\'s course', $status === 404);

[$status] = $bob->request('DELETE', "courses.php?id=$aliceCourseId");
check('bob cannot delete alice\'s course', $status === 404);

[$status] = $alice->request('PUT', 'courses.php?id=1', $newCourse);
check('sample courses cannot be edited', $status === 403);

[$status, $body] = $alice->request('PUT', "courses.php?id=$aliceCourseId", ['topics' => ['Transactions']] + $newCourse);
check('alice updates her course', $status === 200 && $body['course']['topics'] === ['Transactions'], $body);

echo "Chat\n";
[$status] = $guest->request('POST', 'chat.php', ['prompt' => 'limits']);
check('guest cannot use the chat', $status === 401);

[$status, $body] = $alice->request('POST', 'chat.php', ['prompt' => '  ']);
check('empty question returns 400', $status === 400, $body);

[$status, $body] = $alice->request('POST', 'chat.php', ['prompt' => 'Explain joins', 'course_id' => $aliceCourseId]);
check('chat returns the model text', $status === 200 && $body['text'] === 'Mock podcast script.', $body);

$sent = json_decode((string) @file_get_contents(getenv('MOCK_LOG') ?: ''), true);
check('chat sends the course notes to the model', str_contains($sent['instructions'] ?? '', 'Normalization and indexes.'), $sent);

[$status] = $bob->request('POST', 'chat.php', ['prompt' => 'Explain joins', 'course_id' => $aliceCourseId]);
check('chat cannot read another user\'s course', $status === 404);

echo "Deleting\n";
[$status] = $alice->request('DELETE', "courses.php?id=$aliceCourseId");
[$afterStatus] = $alice->request('GET', "courses.php?id=$aliceCourseId");
check('alice deletes her course', $status === 204 && $afterStatus === 404);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
