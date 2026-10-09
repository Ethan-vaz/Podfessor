<?php
// REST endpoint for courses.
//
//   GET    courses.php                    list courses (?semester_id= and ?q= filter the list)
//   GET    courses.php?id=1               one course
//   POST   courses.php                    create a course          (login required)
//   PUT    courses.php?id=1               update your own course   (login required)
//   DELETE courses.php?id=1               delete your own course   (login required)
//
// Everyone sees the sample courses (user_id IS NULL). Logged-in users also see their own.

require __DIR__ . '/_bootstrap.php';

$method = require_method('GET', 'POST', 'PUT', 'DELETE');
$userId = current_user_id();
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

if ($id === false) {
    respond(400, ['error' => 'The course id must be a positive whole number.']);
}

switch ($method) {
    case 'GET':
        if ($id !== null) {
            respond(200, ['course' => find_visible_course($id, $userId)]);
        }
        respond(200, ['courses' => list_courses($userId, $_GET['semester_id'] ?? null, $_GET['q'] ?? null)]);

    case 'POST':
        $userId = require_login();
        $course = validate_course(read_json());
        $newId = save_course($course, $userId);
        respond(201, ['course' => find_visible_course($newId, $userId)]);

    case 'PUT':
        $userId = require_login();
        require_owned_course($id, $userId);
        $course = validate_course(read_json());
        save_course($course, $userId, $id);
        respond(200, ['course' => find_visible_course($id, $userId)]);

    case 'DELETE':
        $userId = require_login();
        require_owned_course($id, $userId);
        db()->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
        respond(204);
}

function list_courses(?int $userId, mixed $semesterId, mixed $search): array
{
    $sql = 'SELECT c.*, s.name AS semester_name
            FROM courses c
            JOIN semesters s ON s.id = c.semester_id
            WHERE (c.user_id IS NULL OR c.user_id = :user_id)';
    $params = ['user_id' => $userId];

    if (is_string($semesterId) && $semesterId !== '') {
        $sql .= ' AND c.semester_id = :semester_id';
        $params['semester_id'] = (int) $semesterId;
    }

    if (is_string($search) && trim($search) !== '') {
        $sql .= ' AND (c.name LIKE :q1 OR c.code LIKE :q2
                  OR EXISTS (SELECT 1 FROM course_topics t WHERE t.course_id = c.id AND t.name LIKE :q3))';
        $like = '%' . addcslashes(trim($search), '%_\\') . '%';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }

    $sql .= ' ORDER BY s.start_date DESC, c.code';
    $query = db()->prepare($sql);
    $query->execute($params);
    $rows = $query->fetchAll();

    $topics = topics_for(array_column($rows, 'id'));
    return array_map(fn ($row) => format_course($row, $topics[$row['id']] ?? [], $userId), $rows);
}

/** Load the topics for many courses in one query, grouped by course id. */
function topics_for(array $courseIds): array
{
    if (!$courseIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $query = db()->prepare(
        "SELECT course_id, name FROM course_topics WHERE course_id IN ($placeholders) ORDER BY course_id, position"
    );
    $query->execute(array_values($courseIds));

    $grouped = [];
    foreach ($query->fetchAll() as $topic) {
        $grouped[$topic['course_id']][] = $topic['name'];
    }
    return $grouped;
}

function format_course(array $row, array $topics, ?int $userId): array
{
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'semester_id' => (int) $row['semester_id'],
        'semester' => $row['semester_name'],
        'topics' => $topics,
        'image_url' => $row['image_url'],
        'notes' => $row['notes'],
        'owned' => $userId !== null && (int) $row['user_id'] === $userId,
    ];
}

function find_visible_course(int $id, ?int $userId): array
{
    $query = db()->prepare(
        'SELECT c.*, s.name AS semester_name
         FROM courses c JOIN semesters s ON s.id = c.semester_id
         WHERE c.id = ? AND (c.user_id IS NULL OR c.user_id = ?)'
    );
    $query->execute([$id, $userId]);
    $row = $query->fetch();
    if (!$row) {
        respond(404, ['error' => 'Course not found.']);
    }
    return format_course($row, topics_for([$id])[$id] ?? [], $userId);
}

/** Stop unless the course exists, is visible to this user, and belongs to them. */
function require_owned_course(?int $id, int $userId): void
{
    if ($id === null) {
        respond(400, ['error' => 'Add ?id= to choose a course.']);
    }
    $course = find_visible_course($id, $userId);
    if (!$course['owned']) {
        respond(403, ['error' => 'Sample courses can\'t be changed. Add your own course instead.']);
    }
}

/** Check the submitted fields and return clean values, or respond 422 with a message per field. */
function validate_course(array $data): array
{
    $errors = [];

    $code = strtoupper(string_field($data, 'code'));
    if ($code === '' || mb_strlen($code) > 20) {
        $errors['code'] = 'Enter a course code up to 20 characters.';
    }

    $name = string_field($data, 'name');
    if ($name === '' || mb_strlen($name) > 150) {
        $errors['name'] = 'Enter a course name up to 150 characters.';
    }

    $semesterId = filter_var($data['semester_id'] ?? null, FILTER_VALIDATE_INT);
    $semesterExists = false;
    if ($semesterId) {
        $check = db()->prepare('SELECT 1 FROM semesters WHERE id = ?');
        $check->execute([$semesterId]);
        $semesterExists = (bool) $check->fetchColumn();
    }
    if (!$semesterExists) {
        $errors['semester_id'] = 'Choose a semester.';
    }

    // Topics can arrive as an array or as one comma-separated string.
    $rawTopics = $data['topics'] ?? [];
    if (is_string($rawTopics)) {
        $rawTopics = explode(',', $rawTopics);
    }
    $topics = is_array($rawTopics)
        ? array_values(array_unique(array_filter(array_map(fn ($t) => is_string($t) ? trim($t) : '', $rawTopics), 'strlen')))
        : [];
    if (count($topics) > 20 || array_filter($topics, fn ($t) => mb_strlen($t) > 100)) {
        $errors['topics'] = 'Use up to 20 topics, each up to 100 characters.';
    }

    $imageUrl = string_field($data, 'image_url');
    if ($imageUrl !== '' && (
        strlen($imageUrl) > 500
        || !filter_var($imageUrl, FILTER_VALIDATE_URL)
        || !in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true)
    )) {
        $errors['image_url'] = 'Enter a full image link starting with http:// or https://.';
    }

    $notes = string_field($data, 'notes');
    if (mb_strlen($notes) > 5000) {
        $errors['notes'] = 'Keep notes under 5,000 characters.';
    }

    if ($errors) {
        respond(422, ['error' => 'Please fix the highlighted fields.', 'fields' => $errors]);
    }

    return [
        'code' => $code,
        'name' => $name,
        'semester_id' => $semesterId,
        'topics' => $topics,
        'image_url' => $imageUrl ?: null,
        'notes' => $notes ?: null,
    ];
}

/** Insert or update a course and replace its topics, all in one transaction. Returns the course id. */
function save_course(array $course, int $userId, ?int $id = null): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $values = [$course['semester_id'], $course['code'], $course['name'], $course['image_url'], $course['notes']];

        if ($id === null) {
            $pdo->prepare(
                'INSERT INTO courses (semester_id, code, name, image_url, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([...$values, $userId]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare(
                'UPDATE courses SET semester_id = ?, code = ?, name = ?, image_url = ?, notes = ? WHERE id = ? AND user_id = ?'
            )->execute([...$values, $id, $userId]);
            $pdo->prepare('DELETE FROM course_topics WHERE course_id = ?')->execute([$id]);
        }

        $insertTopic = $pdo->prepare('INSERT INTO course_topics (course_id, name, position) VALUES (?, ?, ?)');
        foreach ($course['topics'] as $position => $topic) {
            $insertTopic->execute([$id, $topic, $position]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $id;
}
