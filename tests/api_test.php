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

    /** GET a file such as audio. @return array{0:int,1:string,2:array<string,string>} status, raw body, lower-cased headers */
    public function download(string $path, array $requestHeaders = []): array
    {
        $responseHeaders = [];
        $ch = curl_init($this->baseUrl . '/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $raw = (string) curl_exec($ch);
        return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $raw, $responseHeaders];
    }
}

/** Every request the fake OpenAI server received, oldest first. */
function mock_requests(): array
{
    $lines = file(getenv('MOCK_LOG') ?: '', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return array_map(fn ($line) => json_decode($line, true), $lines);
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
$episode = $body['episode'] ?? [];
check('chat saves the script as an episode', ($episode['script'] ?? '') === 'Mock podcast script.'
    && $episode['prompt'] === 'Explain joins' && $episode['course_code'] === 'DBS311' && $episode['audio_url'] === null, $episode);

$sent = mock_requests()[0]['body'] ?? [];
check('chat sends the course notes to the model', str_contains($sent['instructions'] ?? '', 'Normalization and indexes.'), $sent);

[$status] = $bob->request('POST', 'chat.php', ['prompt' => 'Explain joins', 'course_id' => $aliceCourseId]);
check('chat cannot read another user\'s course', $status === 404);

echo "Episodes and audio\n";
[$status] = $guest->request('GET', 'episodes.php');
check('guest cannot list episodes', $status === 401);

[$status, $body] = $alice->request('GET', 'episodes.php');
check('alice lists her episodes', $status === 200 && array_column($body['episodes'], 'id') === [$episode['id']], $body);

[, $body] = $bob->request('GET', 'episodes.php');
check('bob does not see alice\'s episodes', $body['episodes'] === [], $body);

[$status] = $alice->request('GET', "audio.php?episode_id={$episode['id']}");
check('audio is 404 before it is generated', $status === 404);

[$status] = $bob->request('POST', 'speech.php', ['episode_id' => $episode['id']]);
check('bob cannot generate audio for alice\'s episode', $status === 404);

$requestsBefore = count(mock_requests());
[$status, $body] = $alice->request('POST', 'speech.php', ['episode_id' => $episode['id']]);
$audioUrl = $body['episode']['audio_url'] ?? '';
check('speech returns an audio link', $status === 200 && $audioUrl === "api/audio.php?episode_id={$episode['id']}", $body);

$speechRequest = mock_requests()[$requestsBefore] ?? [];
check('speech sends the script to the TTS endpoint', ($speechRequest['path'] ?? '') === '/audio/speech'
    && $speechRequest['body']['input'] === 'Mock podcast script.' && $speechRequest['body']['response_format'] === 'mp3', $speechRequest);

[$status] = $alice->request('POST', 'speech.php', ['episode_id' => $episode['id']]);
check('pressing Listen again reuses the saved audio', $status === 200 && count(mock_requests()) === $requestsBefore + 1);

[$status, $audio, $headers] = $alice->download(substr($audioUrl, 4));
check('audio streams to its owner as MP3', $status === 200 && $audio === 'MP3[20]'
    && $headers['content-type'] === 'audio/mpeg' && $headers['accept-ranges'] === 'bytes', [$status, $audio, $headers]);

[$status, $audio, $headers] = $alice->download(substr($audioUrl, 4), ['Range: bytes=2-4']);
check('audio supports range requests for seeking', $status === 206 && $audio === '3[2' && $headers['content-range'] === 'bytes 2-4/7', [$status, $audio, $headers]);

[$status, $audio] = $alice->download(substr($audioUrl, 4), ['Range: bytes=-3']);
check('audio supports suffix ranges', $status === 206 && $audio === '20]', [$status, $audio]);

[$status] = $alice->download(substr($audioUrl, 4), ['Range: bytes=50-']);
check('out-of-range request returns 416', $status === 416);

[$status] = $bob->download(substr($audioUrl, 4));
check('bob cannot download alice\'s audio', $status === 404);

$requestsBefore = count(mock_requests());
[, $body] = $alice->request('POST', 'chat.php', ['prompt' => 'LONG lesson please']);
$longEpisode = $body['episode'] ?? [];
[$status, $body] = $alice->request('POST', 'speech.php', ['episode_id' => $longEpisode['id'] ?? 0]);
$speechParts = array_values(array_filter(array_slice(mock_requests(), $requestsBefore), fn ($r) => $r['path'] === '/audio/speech'));
check('long scripts are recorded in parts under the 4,096 character limit', $status === 200 && count($speechParts) === 3
    && max(array_map(fn ($r) => mb_strlen($r['body']['input']), $speechParts)) <= 4096, count($speechParts));

[, $audio] = $alice->download("audio.php?episode_id={$longEpisode['id']}");
check('the parts are joined into one file', $audio === implode('', array_map(fn ($r) => 'MP3[' . mb_strlen($r['body']['input']) . ']', $speechParts)), $audio);

[$status] = $bob->request('DELETE', "episodes.php?id={$longEpisode['id']}");
check('bob cannot delete alice\'s episode', $status === 404);

$audioFiles = glob(getenv('AUDIO_DIR') . '/*.mp3');
[$status] = $alice->request('DELETE', "episodes.php?id={$longEpisode['id']}");
[, $body] = $alice->request('GET', 'episodes.php');
check('alice deletes an episode', $status === 204 && array_column($body['episodes'], 'id') === [$episode['id']], $body);
check('deleting an episode removes its audio file', count(glob(getenv('AUDIO_DIR') . '/*.mp3')) === count($audioFiles) - 1);

echo "Deleting\n";
[$status] = $alice->request('DELETE', "courses.php?id=$aliceCourseId");
[$afterStatus] = $alice->request('GET', "courses.php?id=$aliceCourseId");
check('alice deletes her course', $status === 204 && $afterStatus === 404);

[, $body] = $alice->request('GET', 'episodes.php');
check('episodes survive when their course is deleted', array_column($body['episodes'], 'id') === [$episode['id']]
    && $body['episodes'][0]['course_code'] === null, $body);

echo "Daily limit (set to 3 for the tests)\n";
[$status] = $alice->request('POST', 'chat.php', ['prompt' => 'Third question']);
check('third episode of the day is allowed', $status === 200);
[$status, $body] = $alice->request('POST', 'chat.php', ['prompt' => 'Fourth question']);
check('fourth returns 429, because deleted episodes still count', $status === 429, $body);
[$status] = $bob->request('POST', 'chat.php', ['prompt' => 'Bob\'s first question']);
check('the limit is per user', $status === 200);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
