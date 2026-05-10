<?php
// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

// Read and decode JSON body
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Read query parameters
$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

function getAllWeeks(PDO $db): void {
    $search = $_GET['search'] ?? '';
    $sort   = $_GET['sort']   ?? 'start_date';
    $order  = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    
    $allowedSort = ['title', 'start_date'];
    if (!in_array($sort, $allowedSort)) { $sort = 'start_date'; }

    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if (!empty($search)) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params['search'] = "%$search%";
    }

    $query .= " ORDER BY $sort $order";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
}

function createWeek(PDO $db, array $data): void {
    $title = sanitizeInput($data['title'] ?? '');
    $start_date = $data['start_date'] ?? '';

    if (empty($title) || empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $desc = sanitizeInput($data['description'] ?? '');
    $links = json_encode($data['links'] ?? []);

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $start_date, $desc, $links])) {
        sendResponse(['success' => true, 'message' => 'Created', 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Server Error'], 500);
    }
}

// ============================================================================
// ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}

// ============================================================================
// HELPERS (Put these at the bottom)
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// NOTE: You still need to implement updateWeek, deleteWeek, getCommentsByWeek, 
// and deleteComment inside this file following the same logic!
