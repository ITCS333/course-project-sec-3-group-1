<?php
// 1. Headers 
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Database Connection
require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

// --- ROUTER ---
try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getComments($db, $resourceId);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }
    } else {
       
        sendResponse(['success' => false], 405);
    }
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Server Error'], 500);
}

// --- FUNCTIONS ---

function getAllResources($db) {
    $search = $_GET['search'] ?? '';
    $query = "SELECT * FROM resources";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE title LIKE :s OR description LIKE :s";
        $params['s'] = "%$search%";
    }
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResourceById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$res) sendResponse(['success' => false], 404);
    sendResponse(['success' => true, 'data' => $res]);
}

function createResource($db, $data) {
    $title = trim($data['title'] ?? '');
    $link = trim($data['link'] ?? '');
  
    if (empty($title) || empty($link) || !filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false], 400);
    }
    $stmt = $db->prepare("INSERT INTO resources (title, link, description) VALUES (?, ?, ?)");
    $stmt->execute([$title, $link, $data['description'] ?? '']);
   
    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function updateResource($db, $data) {
    $id = $data['id'] ?? null;
    $link = $data['link'] ?? '';
    if (!$id) sendResponse(['success' => false], 400);

    if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false], 400);
    }
    $stmt = $db->prepare("UPDATE resources SET title=?, link=?, description=? WHERE id=?");
    $stmt->execute([$data['title'], $link, $data['description'], $id]);
    if ($stmt->rowCount() === 0) sendResponse(['success' => false], 404); 
    sendResponse(['success' => true]);
}

function deleteResource($db, $id) {
    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) sendResponse(['success' => false], 404); 
    sendResponse(['success' => true]);
}

function getComments($db, $resId) {
    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ?");
    $stmt->execute([$resId]);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {
    $resId = $data['resource_id'] ?? null;
    $text = trim($data['text'] ?? '');
    if (!$resId || empty($text)) sendResponse(['success' => false], 400); 
    
    
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$resId]);
    if (!$check->fetch()) sendResponse(['success' => false], 404);

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$resId, $data['author'] ?? 'Anonymous', $text]);
    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function deleteComment($db, $id) {
    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) sendResponse(['success' => false], 404);
    sendResponse(['success' => true]);
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
