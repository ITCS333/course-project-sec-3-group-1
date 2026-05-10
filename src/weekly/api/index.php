<?php
header('Content-Type: application/json');
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

$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

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
        sendResponse(['success' => false], 405);
    }
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

// --- FUNCTIONS ---

function getAllWeeks($db) {
    $search = $_GET['search'] ?? '';
    $query = "SELECT * FROM weeks";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE title LIKE :s OR description LIKE :s";
        $params['s'] = "%$search%";
    }
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks ?: []]);
}

function getWeekById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$week) sendResponse(['success' => false], 404);
    
    $week['links'] = json_decode($week['links'], true) ?? [];
    sendResponse(['success' => true, 'data' => $week]);
}

// REPLACE your old createWeek with this:
function createWeek($db, $data) {
    $title = trim($data['title'] ?? '');
    $start_date = trim($data['start_date'] ?? '');

    if (empty($title) || empty($start_date)) {
        sendResponse(['success' => false], 400);
    }

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $links = json_encode($data['links'] ?? []);
    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $start_date, $data['description'] ?? '', $links]);
    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}


function updateWeek($db, $data) {
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        sendResponse(['success' => false], 400);
    }

    if (isset($data['start_date']) && !validateDate($data['start_date'])) {
        sendResponse(['success' => false], 400);
    }

    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false], 404);
    }

    $stmt = $db->prepare("UPDATE weeks SET title = ?, start_date = ?, description = ?, links = ? WHERE id = ?");
    $links = json_encode($data['links'] ?? []);
    
    $success = $stmt->execute([
        $data['title'] ?? '',
        $data['start_date'] ?? '',
        $data['description'] ?? '',
        $links,
        $id
    ]);

    sendResponse(['success' => $success]);
}

function deleteWeek($db, $id) {
    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) sendResponse(['success' => false], 404);
    sendResponse(['success' => true]);
}

function getCommentsByWeek($db, $weekId) {
    if (!$weekId) sendResponse(['success' => false], 400);
    $stmt = $db->prepare("SELECT * FROM comments_week WHERE week_id = ?");
    $stmt->execute([$weekId]);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
}

function createComment($db, $data) {
    $weekId = $data['week_id'] ?? null;
    $text = trim($data['text'] ?? '');
    
    if (!$weekId || empty($text)) sendResponse(['success' => false], 400);

    
    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$weekId]);
    if (!$check->fetch()) sendResponse(['success' => false], 404);

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$weekId, $data['author'] ?? 'Anonymous', $text]);
    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function deleteComment($db, $id) {
    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) sendResponse(['success' => false], 404);
    sendResponse(['success' => true]);
}
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
