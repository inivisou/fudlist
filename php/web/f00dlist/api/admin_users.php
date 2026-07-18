<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $success = false;
    $newData = [];

    switch ($action) {
        case 'set_admin':
            $success = syncUserRole($userId, 'admin');
            $newData['role'] = 'admin';
            break;
        case 'set_colaborador':
            $success = syncUserRole($userId, 'colaborador');
            $newData['role'] = 'colaborador';
            break;
        case 'remove_role':
            $success = syncUserRole($userId, 'user');
            $newData['role'] = 'user';
            break;
        case 'toggle_active':
            $currentStatus = (int)$_POST['current_status'];
            $newStatus = $currentStatus ? 0 : 1;
            $success = setUserActiveState($userId, $newStatus);
            $newData['active'] = $newStatus;
            break;
    }

    echo json_encode(['success' => $success, 'data' => $newData]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}