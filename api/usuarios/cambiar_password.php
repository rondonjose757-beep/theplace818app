<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int)($body['id']           ?? 0);
$password = (string)($body['password']  ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
       ->execute([$hash, $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
