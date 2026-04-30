<?php
// Guard exclusivo para administrador.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if (($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo el administrador puede gestionar usuarios']);
    exit;
}

$_currentUserId = (int)$_SESSION['user_id'];
