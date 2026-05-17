<?php
/**
 * Assignment Management API
 *
 * RESTful API for CRUD operations on course assignments and their
 * discussion comments. Uses PDO to interact with the MySQL database
 * defined in schema.sql.
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

if (!is_array($data)) {
    $data = [];
}

$action       = $_GET['action']        ?? null;
$id           = $_GET['id']            ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null;


// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

function getAllAssignments(PDO $db): void
{
    $query = "SELECT id, title, description, due_date, files, created_at, updated_at
              FROM assignments";

    $search = $_GET['search'] ?? null;

    if ($search !== null && trim($search) !== '') {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort = $_GET['sort'] ?? 'due_date';

    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'due_date';
    }

    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'asc');

    if (!in_array($order, $allowedOrder, true)) {
        $order = 'asc';
    }

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    if ($search !== null && trim($search) !== '') {
        $stmt->bindValue(':search', '%' . trim($search) . '%');
    }

    $stmt->execute();

    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse([
        'success' => true,
        'data' => $assignments
    ]);
}


function getAssignmentById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment id.'
        ], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, description, due_date, files, created_at, updated_at
         FROM assignments
         WHERE id = ?"
    );

    $stmt->execute([(int)$id]);

    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];

        sendResponse([
            'success' => true,
            'data' => $assignment
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Assignment not found.'
    ], 404);
}


function createAssignment(PDO $db, array $data): void
{
    if (
        empty($data['title']) ||
        empty($data['description']) ||
        empty($data['due_date'])
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Title, description, and due date are required.'
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $due_date = trim($data['due_date']);

    if (!validateDate($due_date)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid due date format. Use YYYY-MM-DD.'
        ], 400);
    }

    if (isset($data['files']) && is_array($data['files'])) {
        $files = json_encode($data['files']);
    } else {
        $files = json_encode([]);
    }

    $stmt = $db->prepare(
        "INSERT INTO assignments (title, description, due_date, files)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $title,
        $description,
        $due_date,
        $files
    ]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment created successfully.',
            'id' => (int)$db->lastInsertId()
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to create assignment.'
    ], 500);
}


function updateAssignment(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment id is required.'
        ], 400);
    }

    $id = (int)$data['id'];

    $checkStmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $clauses = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $clauses[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }

    if (array_key_exists('description', $data)) {
        $clauses[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }

    if (array_key_exists('due_date', $data)) {
        $due_date = trim($data['due_date']);

        if (!validateDate($due_date)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid due date format. Use YYYY-MM-DD.'
            ], 400);
        }

        $clauses[] = "due_date = ?";
        $values[] = $due_date;
    }

    if (array_key_exists('files', $data)) {
        if (is_array($data['files'])) {
            $clauses[] = "files = ?";
            $values[] = json_encode($data['files']);
        } else {
            $clauses[] = "files = ?";
            $values[] = json_encode([]);
        }
    }

    if (count($clauses) === 0) {
        sendResponse([
            'success' => false,
            'message' => 'No fields provided to update.'
        ], 400);
    }

    $values[] = $id;

    $query = "UPDATE assignments SET " . implode(', ', $clauses) . " WHERE id = ?";

    $stmt = $db->prepare($query);
    $success = $stmt->execute($values);

    if ($success) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment updated successfully.'
        ], 200);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to update assignment.'
    ], 500);
}


function deleteAssignment(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment id.'
        ], 400);
    }

    $id = (int)$id;

    $checkStmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment deleted successfully.'
        ], 200);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to delete assignment.'
    ], 500);
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if ($assignmentId === null || !is_numeric($assignmentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment id.'
        ], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC"
    );

    $stmt->execute([(int)$assignmentId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}


function createComment(PDO $db, array $data): void
{
    if (
        empty($data['assignment_id']) ||
        empty($data['author']) ||
        empty(trim($data['text'] ?? ''))
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment id, author, and text are required.'
        ], 400);
    }

    if (!is_numeric($data['assignment_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment id.'
        ], 400);
    }

    $assignmentId = (int)$data['assignment_id'];
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $checkStmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $checkStmt->execute([$assignmentId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_assignment (assignment_id, author, text)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $assignmentId,
        $author,
        $text
    ]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();

        $fetchStmt = $db->prepare(
            "SELECT id, assignment_id, author, text, created_at
             FROM comments_assignment
             WHERE id = ?"
        );

        $fetchStmt->execute([$newId]);
        $comment = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $newId,
            'data' => $comment
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to create comment.'
    ], 500);
}


function deleteComment(PDO $db, $commentId): void
{
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment id.'
        ], 400);
    }

    $commentId = (int)$commentId;

    $checkStmt = $db->prepare("SELECT id FROM comments_assignment WHERE id = ?");
    $checkStmt->execute([$commentId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ], 200);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to delete comment.'
    ], 500);
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id !== null) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createAssignment($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateAssignment($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
        }

    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method Not Allowed.'
        ], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'A database error occurred.'
    ], 500);

} catch (Exception $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'A server error occurred.'
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}


function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
