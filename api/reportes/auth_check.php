<?php
// Auth guard for reportes endpoints — only administrador and dueño.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$_rolUsuario = $_SESSION['rol'] ?? '';
if (!in_array($_rolUsuario, ['administrador', 'dueño'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$_currentUserId = (int)$_SESSION['user_id'];
