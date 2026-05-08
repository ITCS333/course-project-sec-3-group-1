<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'db.php';
$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$order = $_GET['order'] ?? 'asc';


function getUsers($db) {
    global $search, $sort, $order;
    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];
    if (!empty($search)) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params['search'] = "%$search%";
    }
    $allowedSort = ['name', 'email', 'is_admin'];
    if (in_array($sort, $allowedSort)) {
        $direction = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $sort $direction";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse($users, 200);
}
function getUserById($db, $id) {
    $stmt = $db->prepare("
        SELECT id, name, email, is_admin, created_at
        FROM users
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendResponse('User not found', 404);
    }
    sendResponse($user, 200);
}
function createUser($db, $data) {
    if (
        empty($data['name']) ||
        empty($data['email']) ||
        empty($data['password'])
    ) {
        sendResponse('All fields are required', 400);
    }
    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $password = trim($data['password']);
    if (!validateEmail($email)) {
        sendResponse('Invalid email format', 400);
    }
    if (strlen($password) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }
    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        sendResponse('Email already exists', 409);
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;
    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, is_admin)
        VALUES (:name, :email, :password, :is_admin)
    ");
    $success = $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'is_admin' => $is_admin
    ]);
    if ($success) {
        sendResponse([
            'id' => $db->lastInsertId()
        ], 201);
    }
    sendResponse('Failed to create user', 500);
}
function updateUser($db, $data) {
    if (empty($data['id'])) {
        sendResponse('User ID is required', 400);
    }
    $id = (int) $data['id'];
    $check = $db->prepare("SELECT * FROM users WHERE id = :id");
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        sendResponse('User not found', 404);
    }
    $fields = [];
    $params = ['id' => $id];
    if (isset($data['name'])) {
        $fields[] = "name = :name";
        $params['name'] = sanitizeInput($data['name']);
    }
    if (isset($data['email'])) {
        $email = sanitizeInput($data['email']);
        if (!validateEmail($email)) {
            sendResponse('Invalid email format', 400);
        }
        $duplicate = $db->prepare("
            SELECT id FROM users
            WHERE email = :email AND id != :id
        ");
        $duplicate->execute([
            'email' => $email,
            'id' => $id
        ]);
        if ($duplicate->fetch()) {
            sendResponse('Email already in use', 409);
        }
        $fields[] = "email = :email";
        $params['email'] = $email;
    }
    if (isset($data['is_admin'])) {
        $is_admin = $data['is_admin'] == 1 ? 1 : 0;
        $fields[] = "is_admin = :is_admin";
        $params['is_admin'] = $is_admin;
    }
    if (empty($fields)) {
        sendResponse('No fields to update', 400);
    }
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $success = $stmt->execute($params);
    if ($success) {
        sendResponse('User updated successfully', 200);
    }
        sendResponse('Failed to update user', 500);
}
function deleteUser($db, $id) {
    if (!$id) {
        sendResponse('User ID is required', 400);
    }
    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        sendResponse('User not found', 404);
    }
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $success = $stmt->execute(['id' => $id]);
    if ($success) {
        sendResponse('User deleted successfully', 200);
    }
    sendResponse('Failed to delete user', 500);
}
function changePassword($db, $data) {
    if (
        empty($data['id']) ||
        empty($data['current_password']) ||
        empty($data['new_password'])
    ) {
        sendResponse('All password fields are required', 400);
    if (strlen($data['new_password']) < 8) {
        sendResponse('New password must be at least 8 characters', 400);
    }
    $stmt = $db->prepare("
        SELECT password
        FROM users
        WHERE id = :id
    ");
    $stmt->execute(['id' => $data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendResponse('User not found', 404);
    }
    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse('Current password is incorrect', 401);
    }
    $hashedPassword = password_hash(
        $data['new_password'],
        PASSWORD_DEFAULT
    );
    $update = $db->prepare("
        UPDATE users
        SET password = :password
        WHERE id = :id
    ");
    $success = $update->execute([
        'password' => $hashedPassword,
        'id' => $data['id']
    ]);
    if ($success) {
        sendResponse('Password changed successfully', 200);
    }
    sendResponse('Failed to change password', 500);
}
try {
    if ($method === 'GET') {
        if (!empty($id)) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateUser($db, $data);
    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);
    } else {
        sendResponse('Method not allowed', 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse('Database error', 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
    echo json_encode([
            'success' => true,
            'data' => $data
        ]);

    } else {
        echo json_encode([
            'success' => false,
             'message' => $data
        ]);
    }
    exit();
}
function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

?>
